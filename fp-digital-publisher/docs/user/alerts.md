# Alerts and notifications

Alerts keep stakeholders informed about pending approvals, publishing failures, and expiring credentials.

## Configuring alerts

1. Navigate to **FP Publisher → Settings → Alerts**.
2. Choose the delivery channels: email, Slack webhook, or both.
3. Set the notification rules for approvals, failures, expiring tokens, and housekeeping outcomes.
4. Save the configuration; test messages can be sent from the same screen.

## Alert types

| Type | Trigger |
| --- | --- |
| Approval reminder | A draft remains in review longer than the configured SLA. |
| Publish failure | A job failed after all retries. The alert includes error logs and replay options. |
| Token expiry | Connector credentials are due to expire within the grace period. |
| Cleanup summary | Daily housekeeping job archives links or deletes expired assets. |

## Managing recipients

* Add multiple email addresses separated by commas.
* For Slack, create an incoming webhook and paste the URL into the integration field.
* Use channel-specific overrides to send alerts to different teams based on the connector.

## Muting alerts

Mute specific drafts from the card menu if the team is already addressing the issue. Mutes expire automatically once the card moves to the published state.
