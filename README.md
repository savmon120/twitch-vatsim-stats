# Twitch + VATSIM Stats (WordPress Plugin)

Display your Twitch channel followers, subscribers, and VATSIM pilot/controller hours on your WordPress site.

## Features
- ✅ Twitch Followers (via Twitch API)
- ✅ Twitch Subscribers (via OAuth)
- ✅ Twitch Live Status
- ✅ VATSIM Hours (via official [VATSIM API v2](https://vatsim.dev/api/))
- ✅ Fallback manual hours in settings
- 🔧 Debug mode to show raw API JSON

## Installation
1. Download or clone this repo into `wp-content/plugins/twitch-vatsim-stats`
2. Activate in WordPress Admin
3. Go to **Settings → Twitch + VATSIM Stats** and configure:
   - Twitch Client ID & Secret
   - VATSIM CID
   - (Optional) fallback hours
4. Use the shortcode:
   ```php
   [twitch_vatsim_stats]

[*SCREENSHOTS TO BE ADDED*]