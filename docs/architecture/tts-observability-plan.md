# FP Publisher SLA Discovery & Observability Plan

*Author: Francesco Passeri – [francescopasseri.com](https://francescopasseri.com) – [info@francescopasseri.com](mailto:info@francescopasseri.com)*

Applies to plugin version: **1.0.0** and above  
Documentation refresh: **1.1.0**

## 1. Stakeholder Workshops & Interviews
| Session | Participants | Format & When | Objectives | Key Questions (capture in shared notes) | Deliverables | Session Owner |
| --- | --- | --- | --- | --- | --- | --- |
| Business SLA Deep-Dive | Business owner, Product manager, Customer success | 60 min interview (Week 1, Day 1) | Document contractual SLAs, expected turnaround for social post publication, escalation windows | • What publication time guarantees are promised?  • Expected daily/peak publication volumes per channel? • Mission critical integrations whose outage breaks SLA? | SLA requirement summary, list of mission-critical integrations, prioritised use cases | Product manager |
| IT Operations Capacity Workshop | IT lead, DevOps, Scheduler maintainer | 90 min workshop (Week 1, Day 2) | Map technical dependencies, scheduler throughput, resiliency controls | • Current scheduler cadence & backlog tolerance? • Recovery expectations during API outages? • Existing monitoring/logging pain points? | System architecture map, integration health checklist, backlog thresholds | Engineering manager |
| Compliance & Audit Session | Compliance officer, Legal, Security | 60 min interview (Week 1, Day 3) | Identify regulatory obligations for telemetry retention & access logging | • Required retention period for admin activity logs? • Reporting cadence toward audits? • Sensitive data handling constraints for analytics exports? | Compliance requirements log, audit reporting template | Compliance officer |
| Cross-Team Playback | Representatives from all above | 60 min workshop (Week 1, Day 5) | Align on consolidated SLA targets and prioritised telemetry roadmap | • Are proposed KPIs acceptable? • What automation is needed for reporting? • Sign-off on instrumentation phases? | Approved SLA baseline, prioritised backlog, follow-up actions | Program manager |

**Shared documentation**: Meeting notes, captured answers, and final SLA/KPI decisions will be stored in this document and linked from Confluence for ongoing visibility. Owners listed above are responsible for updating their sections within two business days after each session.

## 2. Current Telemetry Inventory (`includes/class-tts-analytics.php`)
| Channel | Method | External API Fields | Storage | Notes |
| --- | --- | --- | --- | --- |
| Facebook | `fetch_facebook_metrics` | `engagement` aggregate from Graph API | Saved under post meta `_tts_metrics['facebook']` | Requires valid page access token; failure returns WP_Error. |
| Instagram | `fetch_instagram_metrics` | `like_count`, `comments_count` via Graph API | Saved under post meta `_tts_metrics['instagram']` | No aggregation beyond raw fields. |
| YouTube | `fetch_youtube_metrics` | `statistics` (views, likes, comments, etc.) from YouTube Data API v3 | Saved under post meta `_tts_metrics['youtube']` | Accepts API key or OAuth token; aggregates nested stats. |
| TikTok | `fetch_tiktok_metrics` | TikTok `video/.../metrics` payload | Saved under post meta `_tts_metrics['tiktok']` | Requires OAuth token; no post-processing. |
| Aggregation | `fetch_all` | Iterates published `tts_social_post` entries, sums numeric metrics via `count_interactions` | Updates `_tts_metrics` per post | Focused only on social engagement; no operational metrics. |

### Observed Gaps Versus Plugin Usage Insights
- **Admin access visibility**: No telemetry on which administrators configure clients, trigger publications, or modify scheduler settings.
- **Scheduler observability**: Job creation, queue backlog, execution time, retries, and failures are not instrumented.
- **Publication outcomes**: Only successful publications update `_published_status` metadata; failures, retries, and external API error codes lack structured tracking for SLA verification.
- **Volume analytics**: There is no aggregation of posts created/published per time window, so peak load vs. capacity cannot be validated.
- **Integration health**: Token expiry, API rate limiting, and channel-specific downtime are not surfaced through metrics or alerts.

## 3. Instrumentation Plan Covering SLA Requirements
### 3.1 Metrics & KPIs
| Metric | Description | Owner | KPI Target | Collection Approach |
| --- | --- | --- | --- | --- |
| Publication Success Rate | Ratio of successful posts vs. total attempts per channel & day | Product Operations Lead | ≥ 99.5% success per channel over rolling 7 days | Emit counter events on publish attempt/success/failure; aggregate via metrics backend (e.g., Prometheus, Datadog). |
| Publication Latency | Time from job creation to external API acknowledgment | Engineering Manager | p95 ≤ 2 minutes; p99 ≤ 5 minutes | Capture timestamps at job enqueue, API request, and callback; expose histogram metric. |
| Scheduler Backlog Depth | Number of pending jobs per queue | DevOps Lead | ≤ 20 pending jobs sustained; alert at 50 | Instrument scheduler loop to emit gauge. |
| Scheduler Retry Rate | Percentage of jobs needing retry | Engineering Manager | ≤ 2% per day | Count retries via structured events; compare to total jobs. |
| Admin Configuration Activity | Count of unique admin actions (login, settings change) | Compliance Officer | 100% of admin changes logged within 5 minutes | Hook into admin actions to emit audit events to log pipeline. |
| API Error Rate | Failed external API calls by channel & error class | Engineering Manager | ≤ 1% error rate per hour; escalate at 3% | Wrap API calls with outcome logging and counter increments. |
| Token Expiry Lead Time | Time between first expiry warning and actual failure | Customer Success Lead | ≥ 7 days average lead time | Track token validation jobs and warnings issued. |
| Throughput vs. Forecast | Actual posts published vs. forecast volumes collected in workshops | Product Manager | Stay within ±10% of forecast weekly | Aggregate job counts per channel/day and compare to forecast dataset. |

### 3.2 Event Tracking
| Event | Trigger Point | Payload Highlights | Owner | Destination |
| --- | --- | --- | --- | --- |
| `admin_login` | WordPress admin authentication success | User ID, roles, IP, timestamp | Compliance Officer | Audit log store (secure, append-only) |
| `settings_update` | Plugin settings saved | Setting category, client ID, user ID | Product Operations Lead | Metrics/event bus for change history |
| `job_enqueued` | Scheduler adds publication job | Job ID, channel, scheduled time | Engineering Manager | Metrics backend + debugging logs |
| `job_executed` | Scheduler executes job | Job ID, duration, outcome status, retry count | DevOps Lead | Metrics backend |
| `job_failed` | Publication attempt fails | Job ID, error code/message, external API | Engineering Manager | Metrics backend + alerting |
| `token_warning_issued` | Token near expiration | Client ID, channel, days remaining | Customer Success Lead | Notification service + metric counter |

### 3.3 Logging Enhancements
- Adopt structured JSON logs for scheduler and API clients including correlation IDs for each job, enabling traceability through retries and alerts.
- Tag logs with client, channel, and environment to satisfy compliance reporting and accelerate root-cause analysis.
- Retain admin activity logs according to compliance session decisions (target: ≥ 24 months storage, encrypted at rest).
- Implement log sampling rules to balance volume with retention constraints, escalating full-detail logs during incidents.

### 3.4 Dashboards & Alerting
- **Operations Dashboard**: Queue depth, latency histograms, error rate trends with alert thresholds aligned to KPI targets.
- **Business Insights Dashboard**: Publication throughput vs. forecast, success rate by channel, engagement metrics from `_tts_metrics` to contextualise SLA adherence.
- **Compliance Dashboard**: Admin activity timeline, token expiry alerts, audit trail completeness.
- Configure alerts (Slack/Email) for KPI breaches: backlog > 50, success rate < 99.5%, latency p95 > target, or repeated token expiries.

### 3.5 Data Lifecycle & Ownership
- Owners listed above are responsible for weekly KPI review and monthly report-out during the cross-team playback meeting.
- Instrumentation backlog is tracked in Jira under the "Observability" epic with sprint commitments reviewed by the Engineering Manager.
- Data retention policies follow compliance requirements captured in Section 1; alignment with security for access reviews every quarter.

## 4. Implementation Roadmap
1. **Discovery (Week 1-2)**: Complete workshops/interviews, validate KPI targets, document integration dependencies, and sign off on compliance requirements.
2. **Instrumentation Sprint (Week 3-4)**: Implement scheduler metrics, publication events, admin logging, and API error tracking; deploy dashboards in staging.
3. **Validation (Week 5)**: Run load tests against forecast volumes, verify alerts fire at thresholds, and hold stakeholder playback for go/no-go.
4. **Rollout & Continuous Improvement (Week 6+)**: Enable production logging, monitor KPIs weekly, adjust thresholds, and feed insights into quarterly roadmap.

## 5. Next Actions
- Circulate this document before the Business SLA Deep-Dive to collect pre-work data (current SLAs, recent incidents, forecast volumes).
- Assign note-takers for each session to update sections within 24 hours post-meeting.
- Engineering Manager to create observability Jira tickets referencing metric/event requirements above.
- Compliance Officer to validate logging retention strategy once requirements are confirmed in Session 3.

## References
- [README.md](../../README.md)
- [SECURITY_IMPROVEMENTS.md](../../SECURITY_IMPROVEMENTS.md)
- [OPTIMIZATION_SUMMARY.md](../../OPTIMIZATION_SUMMARY.md)
- [CHANGELOG.md](../../CHANGELOG.md)

