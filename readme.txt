=== DHH TV Display ===
Author: NP Consulting Group
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0

Self-contained reception TV kiosk for DHH Panel Products.

== What it does ==

* Registers the REST endpoint:  /wp-json/dhh-display/v1/posts
* Serves the kiosk display at:   /tv-display/   (fallback: /?dhh_display=1)
* Pulls the latest news posts and rotates them between static brand slides.

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

== Changelog ==

= 1.7.0 =
- Pinned posts: search and pin specific articles from the dashboard — pinned posts always appear first in the rotation, remaining slots fill with the latest articles automatically
- Slide reorder: Products now shows after About, news slides follow Products
- Excerpt fix: manual excerpts now display in full (up to 300 chars); auto-generated excerpts used as fallback
- Updated defaults: 4 news slides, revised durations (20/30/20/30/20/20), GitHub repo URL pre-populated
- Content updates: "UK's Oldest Independent Importer", "Over 3,000+ Product Lines", live community image URLs

= 1.6.0 =
* Remote updates via GitHub (Plugin Update Checker). Repo URL configurable from
  the admin dashboard — no wp-config.php access needed for public repos.
  Private repo token support retained as optional.

= 1.5.0 =
* Added Community slide (Corringham Athletic FC + Rayleigh Cricket Club) after
  Products, with its own configurable duration in Settings.

= 1.4.0 =
* Per-slide durations: set cover / about / news / products / ending times
  independently in Settings. Progress bar now matches the actual slide time.
* News slides redesigned as a clean image/text split that alternates sides
  (first post image-left); dropped the gradient overlay. Graceful fallback
  panel when a post has no featured image.
* Security: REST payload now escaped (esc_html / esc_url).
* Cache only clears for actual news-post changes, not every post type.
* Added uninstall cleanup (options + downloaded fonts).
* Type scaled up and spacing eased across all slides; white clock; indicator
  dots on a translucent capsule so they read on light and dark slides; Products
  retinted to brand green with an accent edge.

= 1.3.0 =
* Design pass for 10-foot TV legibility: larger headings, weights and spacing
  across all slides.
* Slide 2 (About) rebuilt as a full-bleed image with a white-to-transparent
  overlay, plus expanded "Why choose DHH?" copy and certifications.
* Slide 6 (Products) moved to a light background so the green top bar reads
  clearly, with tightened, more consistent spacing.

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
