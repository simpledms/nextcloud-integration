# SimpleDMS Nextcloud Integration

This app adds a file context-menu action in Nextcloud Files to export a file to SimpleDMS.

## Supported Nextcloud Versions

- 32
- 33

## Install

1. Place the app folder as `custom_apps/simpledms_integration` in your Nextcloud installation.
2. Enable the app from the Apps screen.
3. Open **Administration settings** and set **SimpleDMS base URL**.

## Admin Configuration

- Go to: **Administration settings** -> **Additional settings** -> **SimpleDMS Integration**
- Enter the base URL, for example: `https://simpledms.example.com`
- Base URL requires HTTPS (HTTP accepted only for localhost development)

## How It Works

1. User right-clicks a file in Nextcloud Files and clicks **Upload to SimpleDMS**.
2. The app asks Nextcloud backend to create a signed one-time download URL for that file.
3. The app opens SimpleDMS in a new tab with `GET /open-file/from-url?url=...`.
4. The user has to confirm the URL in SimpleDMS.
5. SimpleDMS reads the URL, fetches the file, and continues to space selection/open-file flow.

## Notes

- Signed URLs are one-time tokens and expire after a short TTL.
- SimpleDMS backend must be able to reach the Nextcloud public URL to fetch the signed download URL.
