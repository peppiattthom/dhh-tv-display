<?php

/* Front-end renderer for the DHH TV Display */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DHH_Display_Render {

	const QUERY_VAR = 'dhh_display';

	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	/**
	 * Pretty URL: /tv-display/  (fallback: /?dhh_display=1).
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^tv-display/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * If our query var is present, render the kiosk and stop.
	 */
	public function maybe_render() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		$this->render();
		exit;
	}

	/** Option key for user settings. */
	const OPTION = 'dhh_display_settings';

	/** Font weights used by the display. */
	const FONT_WEIGHTS = array( 300, 400, 600, 700, 800, 900 );

	/**
	 * Default, user-editable settings (units are human-friendly).
	 */
	public static function defaults() {
		return array(
			'post_count'      => 3,
			'cover_seconds'   => 10,
			'about_seconds'   => 14,
			'news_seconds'    => 12,
			'product_seconds'   => 12,
			'community_seconds' => 14,
			'end_seconds'       => 10,
			'refresh_minutes' => 10,
			'logo_url'        => 'https://dhhpanelproducts.co.uk/wp-content/uploads/2021/08/dhh-panel-products-white.svg',
			'github_repo'     => '',
		);
	}

	/**
	 * Saved settings merged over defaults.
	 */
	public static function get_settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * The public REST endpoint URL for the display.
	 */
	public static function api_url() {
		return esc_url_raw( home_url( '/wp-json/dhh-display/v1/posts' ) );
	}

	/**
	 * Where are the font files? Prefers the uploads copy (installed via the
	 * admin), then the bundled plugin copy. Returns source, url, dir and the
	 * list of weights actually present.
	 */
	public static function font_dir_info() {
		$up = wp_upload_dir();

		$candidates = array(
			'uploads' => array(
				'dir' => trailingslashit( $up['basedir'] ) . 'dhh-tv-display/fonts',
				'url' => trailingslashit( $up['baseurl'] ) . 'dhh-tv-display/fonts',
			),
			'plugin'  => array(
				'dir' => rtrim( DHH_DISPLAY_DIR, '/' ) . '/assets/fonts',
				'url' => rtrim( DHH_DISPLAY_URL, '/' ) . '/assets/fonts',
			),
		);

		foreach ( $candidates as $source => $loc ) {
			$present = array();
			foreach ( self::FONT_WEIGHTS as $w ) {
				if ( file_exists( trailingslashit( $loc['dir'] ) . 'nunito-sans-' . $w . '.woff2' ) ) {
					$present[] = $w;
				}
			}
			if ( ! empty( $present ) ) {
				return array(
					'source'  => $source,
					'url'     => $loc['url'],
					'dir'     => $loc['dir'],
					'weights' => $present,
				);
			}
		}

		return array( 'source' => 'none', 'url' => '', 'dir' => '', 'weights' => array() );
	}

	/**
	 * Build the runtime config injected into the page.
	 * Filterable via 'dhh_display_config' for per-site overrides.
	 */
	private function get_config() {
		$s = self::get_settings();

		$config = array(
			'apiUrl'          => self::api_url(),
			'postCount'       => max( 1, (int) $s['post_count'] ),
			'durations'       => array(
				'welcome'  => max( 3, (int) $s['cover_seconds'] ) * 1000,
				'about'    => max( 3, (int) $s['about_seconds'] ) * 1000,
				'news'     => max( 3, (int) $s['news_seconds'] ) * 1000,
				'products'  => max( 3, (int) $s['product_seconds'] ) * 1000,
				'community' => max( 3, (int) $s['community_seconds'] ) * 1000,
				'contact'   => max( 3, (int) $s['end_seconds'] ) * 1000,
				'default'  => 10000,
			),
			'refreshInterval' => max( 1, (int) $s['refresh_minutes'] ) * 60000,
			'fetchTimeout'    => 8000,
			'qrBaseUrl'       => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&color=3c7f3d&data=',
			'logoUrl'         => $s['logo_url'],
		);

		return apply_filters( 'dhh_display_config', $config );
	}

	/**
	 * Build the @font-face CSS for whichever weights are installed.
	 */
	private function font_face_css() {
		$font = self::font_dir_info();
		if ( empty( $font['weights'] ) ) {
			return '';
		}
		$css = '';
		foreach ( $font['weights'] as $w ) {
			$url  = esc_url( trailingslashit( $font['url'] ) . 'nunito-sans-' . $w . '.woff2' );
			$css .= "@font-face{font-family:'Nunito Sans';font-style:normal;font-weight:{$w};font-display:swap;src:url('{$url}') format('woff2');}";
		}
		return $css;
	}

	/**
	 * Output the standalone kiosk document.
	 */
	private function render() {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			// Shell is tiny; always re-validate so plugin updates take effect on next boot.
			header( 'Cache-Control: no-cache, must-revalidate' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}

		$config      = $this->get_config();
		$config_json = wp_json_encode( $config );
		$logo        = esc_url( $config['logoUrl'] );
		$css         = esc_url( DHH_DISPLAY_URL . 'assets/css/display.css?ver=' . DHH_DISPLAY_VERSION );
		$js          = esc_url( DHH_DISPLAY_URL . 'assets/js/display.js?ver=' . DHH_DISPLAY_VERSION );
		$font_css    = $this->font_face_css();
		$font_style  = $font_css ? "<style id=\"dhh-fonts\">{$font_css}</style>\n" : '';

		echo <<<DISPLAYHTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>DHH Panel Products — TV Display</title>

<!-- Speed up the connections to the image CDN and QR service -->
<link rel="preconnect" href="https://www.dhhpanelproducts.co.uk" crossorigin>
<link rel="preconnect" href="https://dhhpanelproducts.co.uk" crossorigin>
<link rel="preconnect" href="https://api.qrserver.com" crossorigin>

{$font_style}<link rel="stylesheet" href="{$css}">
</head>
<body>

<div class="display-wrap" id="displayWrap">

  <!-- Progress Bar -->
  <div class="progress-bar" id="progressBar"></div>

  <!-- Indicator Dots (built by JS) -->
  <div class="slide-indicators" id="indicators"></div>

  <!-- Slide 1: Welcome -->
  <div class="slide slide--welcome static-slide active">
    <div class="welcome-content">
      <img class="logo-main" src="{$logo}" alt="DHH Panel Products">
      <div class="welcome-divider"></div>
      <h1>Welcome to DHH Panel Products</h1>
      <div class="welcome-sub">UK's Largest Independent Importer &amp; Distributor of Panel Products</div>
      <div class="welcome-features">
        <div class="wf-item">
          <div class="wf-icon"><img src="https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/08/cup.svg" alt=""></div>
          Award Winning<br>Products
        </div>
        <div class="wf-item">
          <div class="wf-icon"><img src="https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/08/list.svg" alt=""></div>
          Largest UK<br>Birch Stockist
        </div>
        <div class="wf-item">
          <div class="wf-icon"><img src="https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/08/locations.svg" alt=""></div>
          Nationwide<br>Delivery
        </div>
        <div class="wf-item">
          <div class="wf-icon"><img src="https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/08/leaf.svg" alt=""></div>
          Responsibly<br>Sourced
        </div>
      </div>
    </div>
  </div>

  <!-- Slide 2: About -->
  <div class="slide slide--about static-slide">
    <div class="top-bar">
      <div class="tb-logo"><img src="{$logo}" alt="DHH"></div>
      <div class="tb-clock"><div class="tb-time"></div><div class="tb-date"></div></div>
    </div>
    <div class="about-bg"></div>
    <div class="about-text">
      <span class="label-tag">About us</span>
      <h2>Independent, family run &amp; trusted for over 30 years</h2>
      <p>We're a leading independent importer and distributor of wood panel products, supplying customers across the UK. With extensive stockholding, expert knowledge and responsible sourcing, DHH delivers quality products backed by trusted service.</p>
      <div class="about-why">
        <h3>Why choose DHH?</h3>
        <ul>
          <li>Over 35 years of industry experience</li>
          <li>Extensive UK stockholding for fast delivery</li>
          <li>FSC&reg; (FSC-C002826) and PEFC-certified products available</li>
          <li>Dedicated account management and technical support</li>
          <li>Flexible, customer-based service</li>
        </ul>
      </div>
      <div class="about-stats-line">35+ Years in Business <span class="sep">|</span> 350+ Years Combined Experience <span class="sep">|</span> 12+ Product Ranges</div>
      <div class="about-safe">The Safe &amp; Legal Choice</div>
      <div class="cert-logos">
        <img src="https://www.dhhpanelproducts.co.uk/wp-content/uploads/2026/02/fsc-logo.svg" alt="FSC">
        <img src="https://www.dhhpanelproducts.co.uk/wp-content/uploads/2026/02/pefc-logo.svg" alt="PEFC">
        <img src="https://www.dhhpanelproducts.co.uk/wp-content/uploads/2026/02/tduk-logo.svg" alt="TDUK">
      </div>
    </div>
  </div>

  <!-- Slides 3-5: News (built by JS) -->

  <!-- Slide 6: Products -->
  <div class="slide slide--products static-slide">
    <div class="top-bar">
      <div class="tb-logo"><img src="{$logo}" alt="DHH"></div>
      <div class="tb-clock"><div class="tb-time"></div><div class="tb-date"></div></div>
    </div>
    <div class="products-body">
      <div class="products-head">
        <span class="label-tag">Our Range</span>
        <h2>Specialist Panel Products</h2>
        <div class="welcome-divider"></div>
      </div>
      <div class="products-grid">
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/12/DHH-Prod-Category-Birch.jpg')"><span class="pc-name">Birch<br>Plywood</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/12/DHH-Prod-Category-Douglas-Fir-scaled.jpg')"><span class="pc-name">Douglas Fir<br>Plywood</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/12/DHH-Prod-Category-Fire-Panels.jpg')"><span class="pc-name">Fire-Rated<br>Panels</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/12/DHH-Prod-Category-Veneers.jpg')"><span class="pc-name">Flexible<br>Panels</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2023/03/DHH-New-Website-Hardwood.jpg')"><span class="pc-name">Hardwood<br>Plywood</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2023/03/DHH-New-Website-Marine.jpg')"><span class="pc-name">Marine<br>Plywood</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/12/DHH-Prod-Category-MDF.jpg')"><span class="pc-name">MDF &amp;<br>Fibreboard</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/12/DHH-Prod-Category-OSB.jpg')"><span class="pc-name">OSB<br>Panels</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2023/03/DHH-New-Website-Poplar.jpg')"><span class="pc-name">Poplar<br>Plywood</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2023/03/DHH-New-Website-Softwood.jpg')"><span class="pc-name">Softwood<br>Plywood</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2021/12/DHH-Prod-Category-Valchromat.jpg')"><span class="pc-name">Valchromat<br>Colour</span></div>
        <div class="product-card" style="background-image:url('https://www.dhhpanelproducts.co.uk/wp-content/uploads/2022/02/VENEERED-Category-Photo-scaled.jpg')"><span class="pc-name">Veneered<br>Panels</span></div>
      </div>
    </div>
  </div>

  <!-- Slide 7: Community -->
  <div class="slide slide--community static-slide">
    <div class="top-bar">
      <div class="tb-logo"><img src="{$logo}" alt="DHH"></div>
      <div class="tb-clock"><div class="tb-time"></div><div class="tb-date"></div></div>
    </div>
    <div class="community-body">
      <div class="community-head">
        <span class="label-tag">In the Community</span>
        <h2>Helping Communities Grow</h2>
        <p class="community-intro">We use our profits each year to support grassroots sports clubs across Essex, helping to secure the green spaces that bring people together.</p>
      </div>
      <div class="community-cards">
        <div class="community-card">
          <div class="cc-image" style="background-image:url('https://dhhtest.wpenginepowered.com/wp-content/uploads/2026/06/Football-Sponsorship-Image.png')"></div>
          <div class="cc-text">
            <h3>Corringham Athletic FC</h3>
            <p>Proud sponsors since Under 6s. The Yellow team earned promotion this season &mdash; a brilliant achievement for the players, coaches and parents. Founded in 1976, Corringham Athletic continues to do a fantastic job supporting youth football.</p>
          </div>
        </div>
        <div class="community-card">
          <div class="cc-image" style="background-image:url('https://dhhtest.wpenginepowered.com/wp-content/uploads/2026/06/DHH-Panels-Low-Resx2.jpg')"></div>
          <div class="cc-text">
            <h3>Rayleigh Cricket Club</h3>
            <p>Teamwear sponsor until 2027. Over ten years of partnership through our &lsquo;Helping Communities Grow&rsquo; initiative, championing the club and the green spaces our communities depend on.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Slide 8: Contact -->
  <div class="slide slide--contact static-slide">
    <div class="contact-content">
      <img class="logo-main" src="{$logo}" alt="DHH Panel Products">
      <div class="welcome-divider"></div>
      <h2>Nationwide Delivery of <strong>Panel Products</strong></h2>
      <div class="contact-grid">
        <div class="cg-item">
          <div class="cg-label">Email</div>
          <div class="cg-value">sales@dhhpanelproducts.co.uk</div>
        </div>
        <div class="cg-item">
          <div class="cg-label">Visit</div>
          <div class="cg-value">dhhpanelproducts.co.uk</div>
        </div>
      </div>
      <div class="contact-grid">
        <div class="cg-item">
          <div class="cg-label">Call Us</div>
          <div class="cg-value">01708 864245</div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>window.DHH_DISPLAY = {$config_json};</script>
<script src="{$js}"></script>

</body>
</html>
DISPLAYHTML;
	}
}
