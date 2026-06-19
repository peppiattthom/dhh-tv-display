=== DHH TV Display ===
Author: NP Consulting Group Limited
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0

Self-contained reception TV kiosk for DHH Panel Products.

== What it does ==

* Registers the REST endpoint:  /wp-json/dhh-display/v1/posts
* Serves the kiosk display at:   /tv-display/   (fallback: /?dhh_display=1)
* Pulls the latest news posts and rotates them between static brand slides
* The display is a standalone document

== Install ==

1. Zip the `dhh-tv-display` folder.
2. WP Admin > Plugins > Add New > Upload Plugin > choose the zip > Activate.
   (Activation flushes permalinks automatically so /tv-display/ works straight
   away. If it ever 404s, just re-save Settings > Permalinks once.)

== Admin (TV Display menu) ==

After activating, a "TV Display" item appears in the left admin menu:

* Status   — kiosk URL (with an "Open display" link), REST endpoint, how many
             posts are published, font state, and news-cache state.
* Settings — number of news slides, seconds per slide, refresh interval, logo
             URL. No code needed; the display picks these up on its next refresh.
* Tools    — Install font, Clear news cache, Re-flush permalinks.

== The font ==

The display uses self-hosted Nunito Sans so it never waits on Google's font CDN.
Easiest way: TV Display > Tools > "Install font" — your server downloads the six
weights (300/400/600/700/800/900, latin subset) into
/wp-content/uploads/dhh-tv-display/fonts/. One click, done.

Manual fallback (for a locked-down host that can't reach Google): drop these into
the plugin's /assets/fonts/ folder instead —

    nunito-sans-300.woff2  nunito-sans-400.woff2  nunito-sans-600.woff2
    nunito-sans-700.woff2  nunito-sans-800.woff2  nunito-sans-900.woff2

Source: https://gwfh.mranftl.com/fonts/nunito-sans (rename to match).

== Remote updates (GitHub) ==

Once configured, the site checks your GitHub repo for new releases. Updates
appear on the Plugins screen and can be applied with one click, or set to
auto-update. No wp-config.php access needed for public repos.

== Changelog ==

= 1.6.1 to 1.6.5 =
* Stylesheet changes and fixes.

= 1.6.0 =
* Remote updates via GitHub (Plugin Update Checker). 

= 1.5.0 =
* Added Community slide, with its own configurable duration in Settings.

= 1.4.0 =
* Per-slide durations: set cover / about / news / products / ending times
  independently in Settings. 
* Cache only clears for actual news-post changes, not every post type.
* Added uninstall cleanup (options + downloaded fonts).

= 1.3.0 =
* Design updates for static slides.

= 1.2.0 =
* Display now scales the whole 1920×1080 stage to fit any 16:9 screen (4K
  included) — text, progress bar and dots scale as one unit, so no per-element
  sizing is needed.
* Dashboard: fixed card layout (Status + Tools left, Settings right), added a
  "Now showing" list of posts in rotation, and a live scaled preview of the kiosk.

= 1.1.0 =
* Added the "TV Display" admin dashboard: status, settings (post count, timing,
  refresh, logo) and tools.
* One-click self-hosting font installer; @font-face now injected by PHP so it
  works whether the font lives in uploads or the plugin folder.

= 1.0.0 =
* Initial release. REST API + standalone /tv-display/ kiosk, self-hosted font,
  resilient fetch-then-swap, publish-invalidated server cache.
