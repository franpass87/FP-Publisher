# Replay and recovery

Replay allows operators to re-dispatch posts that failed or that must be published again to specific channels.

## When to use replay

* The remote API returned a transient error and the automatic retries were exhausted.
* An approver edited a draft after publishing and you need to push the new copy live.
* A channel token was refreshed and you want to republish the latest failed posts.

## Replaying a post

1. Open **FP Publisher → Logs** and filter by the affected channel or campaign.
2. Select the entries you want to replay and click **Replay selected**.
3. Choose whether to keep the original schedule or dispatch immediately.
4. Confirm; the worker will enqueue a new job and reference the original attempt for auditing.

## Limitations

* Replay keeps the original attachments. Upload replacements before triggering the action if assets changed.
* For channels that forbid duplicates (e.g. Google Business) the system will ask you to edit the copy first.
* Replay operations are logged and require the `fp_pub_replay` capability.
