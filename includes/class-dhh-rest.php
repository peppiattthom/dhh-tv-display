<?php
/**
 * REST API for the DHH TV Display.
 *
 * Route: /wp-json/dhh-display/v1/posts?count=3
 *
 * Performance: results are cached in wp_options (NOT transients — WP Engine's
 * Redis object cache can intercept transients) with a short TTL as a safety net.
 * Reliability: the cache is flushed the instant any post is published, updated,
 * or deleted, so new news items appear immediately rather than waiting for TTL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DHH_Display_REST {

	/** Option key prefix (one entry per requested count). */
	const CACHE_PREFIX = 'dhh_display_posts_';

	/** Safety-net TTL in seconds. Publish/update flushes the cache anyway. */
	const CACHE_TTL = 300;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Only clear the cache for actual news posts, not every post type.
		add_action( 'transition_post_status', array( $this, 'maybe_flush_cache' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'maybe_flush_cache_on_delete' ) );
	}

	/**
	 * Register the /posts route.
	 */
	public function register_routes() {
		register_rest_route(
			'dhh-display/v1',
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_posts' ),
				'permission_callback' => '__return_true', // Public, read-only.
				'args'                => array(
					'count' => array(
						'default'           => 4,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && $value > 0 && $value <= 10;
						},
					),
				),
			)
		);
	}

	/**
	 * Return the latest posts, served from cache where possible.
	 */
	public function get_posts( $request ) {
		$count     = (int) $request->get_param( 'count' );
		$cache_key = self::CACHE_PREFIX . $count;

		$cached = get_option( $cache_key );
		if ( is_array( $cached ) && isset( $cached['expires'], $cached['data'] ) && $cached['expires'] > time() ) {
			$data = $cached['data'];
		} else {
			$data = $this->build_posts( $count );
			update_option(
				$cache_key,
				array(
					'expires' => time() + self::CACHE_TTL,
					'data'    => $data,
				),
				false // Do not autoload.
			);
		}

		$response = rest_ensure_response( $data );
		// Short browser/edge cache; the client polls every 10 min anyway.
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}

	/**
	 * Build the posts payload from the database.
	 */
	private function build_posts( $count ) {
		$settings    = DHH_Display_Render::get_settings();
		$pinned_ids  = ! empty( $settings['pinned_posts'] ) ? array_map( 'absint', (array) $settings['pinned_posts'] ) : array();
		$posts       = array();

		// Fetch pinned posts first, in the saved order.
		if ( ! empty( $pinned_ids ) ) {
			foreach ( $pinned_ids as $pid ) {
				$p = get_post( $pid );
				if ( $p && 'publish' === $p->post_status && 'post' === $p->post_type ) {
					$posts[] = $this->format_post( $p->ID );
				}
				if ( count( $posts ) >= $count ) break;
			}
		}

		// Fill remaining slots with latest posts, excluding pinned ones.
		$remaining = $count - count( $posts );
		if ( $remaining > 0 ) {
			$args = array(
				'posts_per_page'      => $remaining,
				'post_status'         => 'publish',
				'post_type'           => 'post',
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			);
			if ( ! empty( $pinned_ids ) ) {
				$args['post__not_in'] = $pinned_ids;
			}
			$query = new WP_Query( $args );
			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$posts[] = $this->format_post( get_the_ID() );
				}
				wp_reset_postdata();
			}
		}

		return $posts;
	}

	/**
	 * Format a single post for the API response.
	 */
	private function format_post( $post_id ) {
		$image_url = has_post_thumbnail( $post_id )
			? get_the_post_thumbnail_url( $post_id, 'full' )
			: '';

		$categories = get_the_category( $post_id );
		$category   = ! empty( $categories ) ? $categories[0]->name : '';

		$raw_excerpt = get_post_field( 'post_excerpt', $post_id );
		$excerpt     = ! empty( $raw_excerpt )
			? wp_strip_all_tags( $raw_excerpt )
			: wp_strip_all_tags( get_the_excerpt( $post_id ) );
		if ( strlen( $excerpt ) > 300 ) {
			$excerpt = substr( $excerpt, 0, 297 ) . '...';
		}

		return array(
			'id'             => $post_id,
			'title'          => esc_html( get_the_title( $post_id ) ),
			'excerpt'        => esc_html( $excerpt ),
			'featured_image' => esc_url( $image_url ),
			'category'       => esc_html( $category ),
			'permalink'      => esc_url( get_permalink( $post_id ) ),
			'date'           => esc_html( get_the_date( 'F Y', $post_id ) ),
		);
	}

	/**
	 * Clear the cache only when a published news post changes status.
	 */
	public function maybe_flush_cache( $new_status, $old_status, $post ) {
		if ( 'post' === $post->post_type && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
			$this->flush_cache();
		}
	}

	/**
	 * Clear the cache when a news post is deleted.
	 */
	public function maybe_flush_cache_on_delete( $post_id ) {
		if ( 'post' === get_post_type( $post_id ) ) {
			$this->flush_cache();
		}
	}

	/**
	 * Clear every cached variant (counts 1-10).
	 */
	public function flush_cache() {
		for ( $i = 1; $i <= 10; $i++ ) {
			delete_option( self::CACHE_PREFIX . $i );
		}
	}
}
