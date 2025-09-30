# Connector setup guide

This guide walks administrators through connecting FP Publisher to the supported social networks and CMS targets. Each connector shares a common pattern: gather credentials, configure channel specific options, and verify the connection from the dashboard.

## Before you start

1. Ensure your WordPress install is running over HTTPS and can reach the external APIs.
2. Grant the user configuring the integration the `fp_pub_manage_connectors` capability.
3. From **FP Publisher → Settings → Connectors** click **Add channel** and choose the service you need to configure.

## Meta (Facebook & Instagram)

1. Generate a long lived system user token from the Meta Business Suite with permissions for the Facebook Page or Instagram account you will manage.
2. In FP Publisher enter the App ID, App Secret, and the generated token.
3. Select the target Page and Instagram profile from the drop-down list and save.
4. Use **Test connection** to ensure the token scopes cover publishing and content insights.

## TikTok Business

1. Create a TikTok Business developer application and enable the Content Posting scope.
2. From the TikTok connector card click **Connect** and authorise the plugin using OAuth.
3. Copy the refresh token shown at the end of the flow into WordPress together with the client ID and secret.
4. Tick **Enable auto-refresh** so the worker can rotate the token when running scheduled jobs.

## YouTube

1. Generate OAuth credentials from the Google Cloud Console and add your WordPress domain as an authorised redirect.
2. Enter the client ID and secret in the YouTube connector form then click **Connect** to open Google’s consent screen.
3. Approve the scopes requested for uploading and managing videos.
4. Pick the default channel and playlist to target for auto-publishing.

## Google Business Profile

1. In Google Cloud enable the Business Profile API and create a service account with the `businessmessages.businessmessagesagent` role.
2. Download the JSON key and upload it through the connector form.
3. Select the primary location and customise defaults such as call-to-action labels.
4. Verify the connection and adjust the default publish window if you need more than one retry.

## WordPress (cross-site)

1. Generate an application password for a user with editor rights on the destination site.
2. Enter the site URL, username, and application password within the connector.
3. Toggle the **Media proxy** option if the remote site cannot fetch private assets from the origin instance.
4. Run the test to confirm authentication and REST routes are reachable.

## Troubleshooting

* **Authentication failed** – confirm the credentials are still valid and the clock on your WordPress server is synchronised.
* **Missing permissions** – review the capability map in [the developer documentation](../dev/architecture.md#capabilities) and add the required role assignment.
* **Rate limits** – enable queue backoff in the connector advanced settings to avoid transient bans.
