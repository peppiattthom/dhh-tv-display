=== DHH TV Display ===
Author: Blackwater Creative
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0

Self-contained reception TV kiosk for DHH Panel Products. Bundles the REST API
and the full-screen slideshow into one plugin — no theme page or code snippets
required.

== What it does ==

* Registers the REST endpoint:  /wp-json/dhh-display/v1/posts
* Serves the kiosk display at:   /tv-display/   (fallback: /?dhh_display=1)
* Pulls the latest news posts and rotates them between static brand slides.
* The display is a standalone document — the active theme never touches it, so
  it looks identical on the sandbox and on the live site with zero editing.

== Install ==

1. Zip the `dhh-tv-display` folder.
2. WP Admin > Plugins > Add New > Upload Plugin > choose the zip > Activate.
   (Activation flushes permalinks automatically so /tv-display/ works straight
   away. If it ever 404s, just re-save Settings > Permalinks once.)
3. Point the Raspberry Pi's Chromium at:  https://YOURSITE/tv-display/

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

If no font is installed the display still works — it falls back to the system
sans-serif, and no font CDN is ever called.

== Remote updates (GitHub) ==

Once configured, the site checks your GitHub repo for new releases. Updates
appear on the Plugins screen and can be applied with one click, or set to
auto-update. No wp-config.php access needed for public repos.

--- STEP 1: Create a public GitHub repo ---

1. Sign in at github.com (or create a free account).
2. Click "+" > "New repository".
3. Name it:  dhh-tv-display
4. Set it to Public.
5. Do NOT tick "Add a README".
6. Click "Create repository".

--- STEP 2: Push the plugin code ---

On your local machine, open a terminal in the plugin folder:

    cd /path/to/dhh-tv-display
    git init
    git add .
    git commit -m "Initial commit - v1.6.0"
    git branch -M main
    git remote add origin https://github.com/YOUR-ACCOUNT/dhh-tv-display.git
    git push -u origin main

The plugin files must sit at the REPO ROOT (dhh-tv-display.php, /includes/,
/assets/ etc.) -- not inside a subfolder.

--- STEP 3: Add the PUC library to the plugin ---

1. Download the latest v5 release:
   https://github.com/YahnisElsts/plugin-update-checker/releases
2. Unzip. Rename the inner folder to:  plugin-update-checker
3. Place it in /lib/ so the path is:
   dhh-tv-display/lib/plugin-update-checker/plugin-update-checker.php
4. Commit and push:
   git add lib/ && git commit -m "Add PUC library" && git push

--- STEP 4: Set the repo URL in the dashboard ---

Go to TV Display > Settings, paste your repo URL into "GitHub repo URL":
    https://github.com/YOUR-ACCOUNT/dhh-tv-display/
Save. The Status card should now show "Remote updates: Enabled".

--- STEP 5: Install this version on the site (last manual upload) ---

Upload the zip via Plugins > Add New > Upload. From now on, updates come
from GitHub.

--- Releasing an update (repeat workflow) ---

1. Make your changes.
2. Bump the version in TWO places in dhh-tv-display.php:
   - The "Version:" header comment
   - The DHH_DISPLAY_VERSION constant
3. Commit and push to main.
4. On GitHub: Releases > "Draft a new release".
   - Tag: the version (e.g. 1.7.0)
   - Target: main
   - Publish release.
5. The site sees "update available" within ~12 hours, or click "Check for
   updates" under the plugin for an immediate check. Enable auto-updates
   on the Plugins screen for fully hands-off.

--- Private repos (optional) ---

If you later switch to a private repo, create a fine-grained personal access
token (Contents: Read-only, Metadata: Read-only, scoped to the repo only)
and add it to wp-config.php:

    define( 'DHH_DISPLAY_GITHUB_TOKEN', 'github_pat_XXXX' );

The plugin picks it up automatically. The token must never be in the repo.

== Advanced configuration ==

Everything in Settings is also overridable in code via the 'dhh_display_config'
filter (handy for locking values in a mu-plugin):

    add_filter( 'dhh_display_config', function ( $config ) {
        $config['fetchTimeout'] = 8000; // ms before a hung request is aborted
        return $config;
    } );

== WP Engine notes ==

* The REST response is cached in wp_options (not transients — WP Engine's Redis
  object cache intercepts transients) and is flushed the moment any post is
  published, updated, or deleted, so new items appear immediately.
* A short Cache-Control header is sent on the REST response. If you see stale
  data on live, add /wp-json/dhh-display/ to the cache exclusions in the WP
  Engine dashboard.

== Changelog ==

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
