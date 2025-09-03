=== Twitch + VATSIM Stats ===
Contributors: Sav Monzac
Tags: twitch, vatsim, followers, subscribers, hours, streaming
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.1
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your Twitch channel followers, subs, and VATSIM pilot/controller hours with a simple shortcode.

== Description ==
This plugin integrates with both the **Twitch API** and the official **VATSIM API** to display your streaming + flying stats anywhere on your WordPress site.

Use the `[twitch_vatsim_stats]` shortcode to show:
- Twitch Followers (via Twitch API)
- Twitch Subscribers (placeholder, requires OAuth)
- Twitch Live Status
- VATSIM Hours (fetched from `https://api.vatsim.net/v2/members/{cid}/stats`)
- Manual fallback values (for when the API is down or to override)

Includes a **debug mode** that dumps the raw Twitch and VATSIM API JSON for troubleshooting.

== Installation ==
1. Upload `twitch-vatsim-stats.zip` to WordPress.
2. Activate the plugin.
3. Go to **Settings → Twitch + VATSIM Stats** and enter:
   - Twitch Channel Name
   - Twitch Client ID
   - Twitch OAuth Token (optional for advanced data)
   - VATSIM CID
   - Fallback hours (if needed)
4. Add `[twitch_vatsim_stats]` to any page or post.

== Frequently Asked Questions ==
= Do I need a Twitch API key? =
Yes. You need a **Twitch Client ID** (and optionally an OAuth token) to fetch follower/sub counts.

= Do I need a VATSIM API key? =
No. You only need your **VATSIM CID**. The plugin calls the official public stats API.

= Can I show both pilot and controller hours? =
Yes. The plugin pulls both fields (`pilot` and `atc`) and displays them.

= What is Debug Mode? =
Add `debug="1"` to the shortcode (e.g. `[twitch_vatsim_stats debug="1"]`) to dump the raw API JSON responses. Useful for testing.

== Changelog ==
= 1.1 =
* Updated to fetch hours from official VATSIM API v2 (`/members/{cid}/stats`).
* Added fallback fields in settings for pilot + controller hours.
* Added debug output for VATSIM API.

= 1.0 =
* Initial release with Twitch API + manual VATSIM entry.
