<?php
/**
 * Admin dashboard for the DHH TV Display.
 *
 * Adds a top-level "TV Display" menu with three sections:
 *   - Status  : live overview (kiosk URL, posts available, font + cache state)
 *   - Settings: post count, slide timing, refresh interval, logo URL
 *   - Tools   : install font, flush cache, re-flush permalinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DHH_Display_Admin {

	const PAGE = 'dhh-tv-display';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Tool actions.
		add_action( 'admin_post_dhh_display_flush_cache', array( $this, 'action_flush_cache' ) );
		add_action( 'admin_post_dhh_display_flush_rewrite', array( $this, 'action_flush_rewrite' ) );
		add_action( 'admin_post_dhh_display_install_fonts', array( $this, 'action_install_fonts' ) );
	}

	/* Menu */

	public function add_menu() {
		add_menu_page(
			'TV Display',
			'TV Display',
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-desktop',
			59
		);
	}

	/* Settings */

	public function register_settings() {
		register_setting(
			'dhh_display_group',
			DHH_Display_Render::OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize' ) )
		);
	}

	public function sanitize( $input ) {
		$out = DHH_Display_Render::defaults();

		$out['post_count']      = isset( $input['post_count'] ) ? min( 10, max( 1, (int) $input['post_count'] ) ) : $out['post_count'];
		foreach ( array( 'cover_seconds', 'about_seconds', 'news_seconds', 'product_seconds', 'community_seconds', 'end_seconds' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? min( 120, max( 3, (int) $input[ $key ] ) ) : $out[ $key ];
		}
		$out['refresh_minutes'] = isset( $input['refresh_minutes'] ) ? min( 240, max( 1, (int) $input['refresh_minutes'] ) ) : $out['refresh_minutes'];
		$out['logo_url']        = isset( $input['logo_url'] ) ? esc_url_raw( trim( $input['logo_url'] ) ) : $out['logo_url'];
		$out['github_repo']     = isset( $input['github_repo'] ) ? esc_url_raw( trim( $input['github_repo'] ) ) : $out['github_repo'];

		// Settings changed → drop the cached payloads so the next poll is fresh.
		$this->flush_cache();

		add_settings_error( 'dhh_display', 'saved', 'Settings saved.', 'updated' );
		return $out;
	}

	/* Tool actions */

	private function flush_cache() {
		for ( $i = 1; $i <= 10; $i++ ) {
			delete_option( 'dhh_display_posts_' . $i );
		}
	}

	public function action_flush_cache() {
		$this->guard( 'dhh_display_flush_cache' );
		$this->flush_cache();
		$this->redirect( array( 'dhh_msg' => 'cache' ) );
	}

	public function action_flush_rewrite() {
		$this->guard( 'dhh_display_flush_rewrite' );
		$render = new DHH_Display_Render();
		$render->add_rewrite();
		flush_rewrite_rules();
		$this->redirect( array( 'dhh_msg' => 'rewrite' ) );
	}

	public function action_install_fonts() {
		$this->guard( 'dhh_display_install_fonts' );
		$result = $this->download_fonts();

		if ( is_wp_error( $result ) ) {
			$this->redirect( array( 'dhh_fonterr' => rawurlencode( $result->get_error_message() ) ) );
		}
		$this->redirect( array( 'dhh_fontok' => rawurlencode( implode( ',', $result ) ) ) );
	}

	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( $action );
	}

	private function redirect( $args ) {
		$args['page'] = self::PAGE;
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Fetch Nunito Sans (latin subset) from Google Fonts and self-host the
	 * .woff2 files in the uploads folder. Runs on the live server, which has
	 * outbound internet — no binaries need shipping in the plugin.
	 */
	private function download_fonts() {
		$weights = DHH_Display_Render::FONT_WEIGHTS;
		$css_url = 'https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@' . implode( ';', $weights ) . '&display=swap';

		$resp = wp_remote_get(
			$css_url,
			array(
				'timeout' => 20,
				'headers' => array(
					// Force woff2 response.
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return new WP_Error( 'css', 'Google Fonts request failed (' . wp_remote_retrieve_response_code( $resp ) . ').' );
		}

		$css = wp_remote_retrieve_body( $resp );

		$up = wp_upload_dir();
		if ( ! empty( $up['error'] ) ) {
			return new WP_Error( 'upload', $up['error'] );
		}
		$dir = trailingslashit( $up['basedir'] ) . 'dhh-tv-display/fonts';
		wp_mkdir_p( $dir );
		if ( ! wp_is_writable( $dir ) ) {
			return new WP_Error( 'write', 'Font folder is not writable: ' . $dir );
		}

		// Each subset is preceded by a /* name */ comment. We only want plain 'latin'.
		$installed = array();
		foreach ( explode( '/* ', $css ) as $segment ) {
			if ( 0 !== strpos( $segment, 'latin */' ) ) {
				continue;
			}
			if ( ! preg_match( '/font-weight:\s*(\d+)/', $segment, $wm ) ) {
				continue;
			}
			if ( ! preg_match( '/src:\s*url\(([^)]+\.woff2)\)/', $segment, $um ) ) {
				continue;
			}
			$weight = (int) $wm[1];
			if ( ! in_array( $weight, $weights, true ) ) {
				continue;
			}

			$file = wp_remote_get( $um[1], array( 'timeout' => 20 ) );
			if ( is_wp_error( $file ) || 200 !== (int) wp_remote_retrieve_response_code( $file ) ) {
				continue;
			}
			$bytes = wp_remote_retrieve_body( $file );
			if ( '' === $bytes ) {
				continue;
			}
			if ( false !== file_put_contents( trailingslashit( $dir ) . 'nunito-sans-' . $weight . '.woff2', $bytes ) ) {
				$installed[] = $weight;
			}
		}

		if ( empty( $installed ) ) {
			return new WP_Error( 'none', 'Could not download any font files. You can add them manually instead (see readme).' );
		}

		sort( $installed );
		return $installed;
	}

	/* Page */

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s          = DHH_Display_Render::get_settings();
		$font       = DHH_Display_Render::font_dir_info();
		$kiosk_url  = home_url( '/tv-display/' );
		$api_url    = DHH_Display_Render::api_url();
		$pub        = (int) wp_count_posts()->publish;
		$news_shown = min( (int) $s['post_count'], $pub );

		// Cache state for the current post count.
		$cache       = get_option( 'dhh_display_posts_' . (int) $s['post_count'] );
		$cache_state = ( is_array( $cache ) && isset( $cache['expires'] ) && $cache['expires'] > time() )
			? 'Cached (expires in ' . human_time_diff( time(), $cache['expires'] ) . ')'
			: 'Not cached';

		// Notices from tool actions.
		$notice = '';
		if ( isset( $_GET['dhh_msg'] ) ) {
			$map    = array( 'cache' => 'News cache cleared.', 'rewrite' => 'Permalinks re-flushed — /tv-display/ re-registered.' );
			$key    = sanitize_key( wp_unslash( $_GET['dhh_msg'] ) );
			$notice = isset( $map[ $key ] ) ? $this->notice( $map[ $key ], 'success' ) : '';
		} elseif ( isset( $_GET['dhh_fontok'] ) ) {
			$w      = sanitize_text_field( wp_unslash( $_GET['dhh_fontok'] ) );
			$notice = $this->notice( 'Font installed (weights: ' . esc_html( $w ) . '). It is now self-hosted from your uploads folder.', 'success' );
		} elseif ( isset( $_GET['dhh_fonterr'] ) ) {
			$notice = $this->notice( 'Font install failed: ' . esc_html( sanitize_text_field( wp_unslash( $_GET['dhh_fonterr'] ) ) ), 'error' );
		}

		$font_label = 'none' === $font['source']
			? '<span class="dhh-pill dhh-warn">Not installed — using system sans-serif</span>'
			: '<span class="dhh-pill dhh-ok">Installed (' . esc_html( $font['source'] ) . '): weights ' . esc_html( implode( ', ', $font['weights'] ) ) . '</span>';

		$posts_warn = ( $pub < (int) $s['post_count'] )
			? ' <span class="dhh-hint">(only ' . $pub . ' published, so ' . $news_shown . ' news slide' . ( 1 === $news_shown ? '' : 's' ) . ' will show)</span>'
			: '';

		// Remote updates status.
		$puc_lib  = file_exists( DHH_DISPLAY_DIR . 'lib/plugin-update-checker/plugin-update-checker.php' );
		$puc_repo = ! empty( $s['github_repo'] );
		if ( $puc_lib && $puc_repo ) {
			$update_label = '<span class="dhh-pill dhh-ok">Enabled</span> <span class="dhh-muted">' . esc_html( $s['github_repo'] ) . '</span>';
		} elseif ( ! $puc_lib && ! $puc_repo ) {
			$update_label = '<span class="dhh-pill dhh-warn">Not configured</span>';
		} else {
			$missing = array();
			if ( ! $puc_lib )  $missing[] = 'PUC library in /lib/';
			if ( ! $puc_repo ) $missing[] = 'GitHub repo URL in Settings';
			$update_label = '<span class="dhh-pill dhh-warn">Incomplete — missing: ' . esc_html( implode( ', ', $missing ) ) . '</span>';
		}

		// The posts that will actually appear in rotation.
		$rotation      = get_posts( array(
			'numberposts' => max( 1, (int) $s['post_count'] ),
			'post_status' => 'publish',
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );
		$rotation_html = '';
		if ( $rotation ) {
			$rotation_html = '<ol class="dhh-rotation">';
			foreach ( $rotation as $p ) {
				$rotation_html .= '<li><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a> <span class="dhh-muted">— ' . esc_html( get_the_date( 'j M Y', $p ) ) . '</span></li>';
			}
			$rotation_html .= '</ol>';
		} else {
			$rotation_html = '<span class="dhh-muted">No published posts yet — only the static brand slides will show.</span>';
		}
		?>
		<div class="wrap dhh-admin">
			<h1><span class="dashicons dashicons-desktop"></span> TV Display</h1>
			<p class="dhh-sub">Reception kiosk for DHH Panel Products.</p>

			<?php settings_errors( 'dhh_display' ); ?>
			<?php echo $notice; // phpcs:ignore ?>

			<div class="dhh-grid">

				<!-- STATUS -->
				<div class="dhh-card dhh-card--status">
					<h2>Status</h2>
					<table class="dhh-status">
						<tr>
							<th>Kiosk URL</th>
							<td>
								<code><?php echo esc_html( $kiosk_url ); ?></code>
								<a class="button button-small" href="<?php echo esc_url( $kiosk_url ); ?>" target="_blank" rel="noopener">Open display ↗</a>
							</td>
						</tr>
						<tr>
							<th>REST endpoint</th>
							<td>
								<code><?php echo esc_html( $api_url ); ?></code>
								<a class="button button-small" href="<?php echo esc_url( $api_url . '?count=' . (int) $s['post_count'] ); ?>" target="_blank" rel="noopener">Test ↗</a>
							</td>
						</tr>
						<tr>
							<th>Published posts</th>
							<td><?php echo esc_html( $pub ); ?><?php echo $posts_warn; // phpcs:ignore ?></td>
						</tr>
						<tr>
							<th>Now showing</th>
							<td><?php echo $rotation_html; // phpcs:ignore ?></td>
						</tr>
						<tr>
							<th>Font</th>
							<td><?php echo $font_label; // phpcs:ignore ?></td>
						</tr>
						<tr>
							<th>News cache</th>
							<td><?php echo esc_html( $cache_state ); ?></td>
						</tr>
						<tr>
							<th>Version</th>
							<td><?php echo esc_html( DHH_DISPLAY_VERSION ); ?></td>
						</tr>
						<tr>
							<th>Remote updates</th>
							<td><?php echo $update_label; // phpcs:ignore ?></td>
						</tr>
					</table>
				</div>

				<!-- SETTINGS -->
				<div class="dhh-card dhh-card--settings">
					<h2>Settings</h2>
					<form method="post" action="options.php">
						<?php settings_fields( 'dhh_display_group' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="dhh_post_count">News slides</label></th>
								<td>
									<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[post_count]" id="dhh_post_count" type="number" min="1" max="10" value="<?php echo esc_attr( $s['post_count'] ); ?>" class="small-text">
									<p class="description">How many recent posts to rotate (1–10).</p>
								</td>
							</tr>
							<tr>
								<th scope="row">Slide durations (secs)</th>
								<td>
									<div class="dhh-durations">
										<label>Cover<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[cover_seconds]" type="number" min="3" max="120" value="<?php echo esc_attr( $s['cover_seconds'] ); ?>" class="small-text"></label>
										<label>About<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[about_seconds]" type="number" min="3" max="120" value="<?php echo esc_attr( $s['about_seconds'] ); ?>" class="small-text"></label>
										<label>News<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[news_seconds]" type="number" min="3" max="120" value="<?php echo esc_attr( $s['news_seconds'] ); ?>" class="small-text"></label>
										<label>Products<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[product_seconds]" type="number" min="3" max="120" value="<?php echo esc_attr( $s['product_seconds'] ); ?>" class="small-text"></label>
										<label>Community<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[community_seconds]" type="number" min="3" max="120" value="<?php echo esc_attr( $s['community_seconds'] ); ?>" class="small-text"></label>
										<label>Ending<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[end_seconds]" type="number" min="3" max="120" value="<?php echo esc_attr( $s['end_seconds'] ); ?>" class="small-text"></label>
									</div>
									<p class="description">How long each slide stays on screen. News applies to every news post.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="dhh_refresh">Refresh every (mins)</label></th>
								<td>
									<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[refresh_minutes]" id="dhh_refresh" type="number" min="1" max="240" value="<?php echo esc_attr( $s['refresh_minutes'] ); ?>" class="small-text">
									<p class="description">How often the display pulls new posts.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="dhh_logo">Logo URL</label></th>
								<td>
									<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[logo_url]" id="dhh_logo" type="url" value="<?php echo esc_attr( $s['logo_url'] ); ?>" class="large-text code">
									<p class="description">White logo shown on the top bar and welcome slide.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="dhh_github">GitHub repo URL</label></th>
								<td>
									<input name="<?php echo esc_attr( DHH_Display_Render::OPTION ); ?>[github_repo]" id="dhh_github" type="url" value="<?php echo esc_attr( $s['github_repo'] ); ?>" class="large-text code" placeholder="https://github.com/your-account/dhh-tv-display/">
									<p class="description">Leave blank to disable remote updates. PUC library must also be in <code>/lib/</code>.</p>
								</td>
							</tr>
						</table>
						<?php submit_button( 'Save settings' ); ?>
					</form>
				</div>

				<!-- TOOLS -->
				<div class="dhh-card dhh-card--tools">
					<h2>Tools</h2>

					<div class="dhh-tool">
						<div>
							<strong>Install / update font</strong>
							<p class="description">Downloads Nunito Sans and self-hosts it (no Google Fonts call at display time).</p>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="dhh_display_install_fonts">
							<?php wp_nonce_field( 'dhh_display_install_fonts' ); ?>
							<?php submit_button( 'none' === $font['source'] ? 'Install font' : 'Re-install font', 'secondary', 'submit', false ); ?>
						</form>
					</div>

					<div class="dhh-tool">
						<div>
							<strong>Clear news cache</strong>
							<p class="description">Forces the next display refresh to rebuild from the database.</p>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="dhh_display_flush_cache">
							<?php wp_nonce_field( 'dhh_display_flush_cache' ); ?>
							<?php submit_button( 'Clear cache', 'secondary', 'submit', false ); ?>
						</form>
					</div>

					<div class="dhh-tool">
						<div>
							<strong>Re-flush permalinks</strong>
							<p class="description">Use only if <code>/tv-display/</code> ever returns a 404.</p>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="dhh_display_flush_rewrite">
							<?php wp_nonce_field( 'dhh_display_flush_rewrite' ); ?>
							<?php submit_button( 'Re-flush', 'secondary', 'submit', false ); ?>
						</form>
					</div>
				</div>

			</div>

			<!-- PREVIEW -->
			<div class="dhh-card dhh-card--preview">
				<h2>Live preview</h2>
				<p class="description">A live, scaled view of the kiosk — handy for a quick check without opening the full screen.</p>
				<div class="dhh-preview"><iframe src="<?php echo esc_url( $kiosk_url ); ?>" title="TV Display preview" loading="lazy"></iframe></div>
			</div>

		</div>

		<style>
			.dhh-admin .dhh-sub { color: #50575e; margin-top: -6px; }
			.dhh-admin h1 .dashicons { font-size: 28px; width: 28px; height: 28px; vertical-align: text-bottom; color: #3c7f3d; }
			.dhh-grid { display: grid; grid-template-columns: 1fr 1fr; grid-auto-rows: min-content; gap: 20px; margin-top: 16px; align-items: start; }
			.dhh-card--status   { grid-column: 1; grid-row: 1; }
			.dhh-card--tools    { grid-column: 1; grid-row: 2; }
			.dhh-card--settings { grid-column: 2; grid-row: 1 / span 2; }
			.dhh-card--preview  { margin-top: 20px; }
			.dhh-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 4px 22px 18px; }
			.dhh-card h2 { border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; }
			.dhh-status { width: 100%; border-collapse: collapse; }
			.dhh-status th { text-align: left; vertical-align: top; padding: 8px 14px 8px 0; white-space: nowrap; color: #50575e; font-weight: 600; }
			.dhh-status td { padding: 8px 0; }
			.dhh-status code { background: #f6f7f7; padding: 2px 6px; border-radius: 4px; margin-right: 8px; }
			.dhh-pill { display: inline-block; padding: 2px 10px; border-radius: 20px; font-weight: 600; font-size: 12px; }
			.dhh-pill.dhh-ok { background: rgba(60,127,61,.12); color: #2d5f2e; }
			.dhh-pill.dhh-warn { background: #fcf3d6; color: #8a6d00; }
			.dhh-hint { color: #8a6d00; }
			.dhh-tool { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #f0f0f1; }
			.dhh-tool:last-child { border-bottom: 0; }
			.dhh-tool .description { margin: 2px 0 0; }
			.dhh-muted { color: #8c8f94; }
			.dhh-durations { display: flex; flex-wrap: wrap; gap: 16px; }
			.dhh-durations label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; font-weight: 600; color: #50575e; }
			.dhh-durations input { margin: 0; }
			.dhh-rotation { margin: 0; padding-left: 18px; }
			.dhh-rotation li { margin: 0 0 4px; }
			.dhh-preview { width: 640px; max-width: 100%; height: 360px; overflow: hidden; border: 1px solid #dcdcde; border-radius: 8px; background: #111; }
			.dhh-preview iframe { width: 1920px; height: 1080px; border: 0; transform: scale(0.33333); transform-origin: top left; }
			@media (max-width: 1100px) {
				.dhh-grid { grid-template-columns: 1fr; }
				.dhh-card--status, .dhh-card--tools, .dhh-card--settings { grid-column: 1; grid-row: auto; }
			}
		</style>
		<?php
	}

	private function notice( $msg, $type ) {
		$class = 'success' === $type ? 'notice-success' : 'notice-error';
		return '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
}
