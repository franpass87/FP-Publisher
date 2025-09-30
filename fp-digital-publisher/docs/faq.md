# Frequently asked questions

## Why did my connector token expire?

Tokens expire when the remote platform rotates credentials or when scopes change. Refresh the token from **Settings → Connectors** and ensure the user or app still has publishing permissions.

## A post was not published, what should I check?

Open the **Logs** page and inspect the job entry. Common causes are invalid media, exceeded rate limits, or missing capabilities. Retry the job if the error is transient, otherwise edit the draft and reschedule.

## How do I replay a failed publication?

Select the failed job in the logs and click **Replay selected**. Choose the channels to replay and decide whether to dispatch immediately or respect the original schedule.

## Why can’t I see the approvals panel?

You need the `fp_pub_approve` capability. Ask an administrator to assign the role or review your user permissions under **Users → Capabilities**.

## Can I disable alerts temporarily?

Yes. From **Settings → Alerts** disable the delivery channels or mute specific drafts directly from the calendar. Muted alerts re-enable automatically after publishing.
