# Approval workflows

FP Publisher includes a lightweight approval system so stakeholders can review posts before they go live.

## Assigning approvers

* Each draft can have one or more approvers. Use the **Assign** button within the composer to pick team members.
* Users must have the `fp_pub_approve` capability to receive approval requests.
* If an approver rejects a post the author will receive an alert with the rejection message.

## Approval queue

The **Approvals** panel lists every draft waiting for review. Cards show:

* The draft owner and scheduled time.
* The channels the post will be published to.
* Comments from approvers and the latest activity.

Approvers can **Approve**, **Request changes**, or **Pass** the card to another reviewer. Approving immediately promotes the draft to the scheduled state.

## Commenting

Use the comment thread on each card to discuss changes. Mention teammates with `@username` to send them a notification. Comments are stored with timestamps so audit trails remain available after publishing.

## Escalations

If a post has not been approved within the SLA configured in **Settings → Alerts**, the system will send a reminder to approvers and optionally notify the channel owner via email or Slack.
