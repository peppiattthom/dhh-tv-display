<?php
// Clean up plugin data on uninstall.

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Settings + cached payloads.
delete_option( 'dhh_display_settings' );
for ( $i = 1; $i <= 10; $i++ ) {
	delete_option( 'dhh_display_posts_' . $i );
}

// Self-hosted fonts in uploads.
$up      = wp_upload_dir();
$basedir = trailingslashit( $up['basedir'] ) . 'dhh-tv-display';
$fonts   = $basedir . '/fonts';

if ( is_dir( $fonts ) ) {
	$files = glob( $fonts . '/*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}
	rmdir( $fonts );
}

if ( is_dir( $basedir ) ) {
	rmdir( $basedir );
}
