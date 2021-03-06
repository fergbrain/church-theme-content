<?php
/**
 * Google Maps
 *
 * @package    Church_Theme_Content
 * @subpackage Admin
 * @copyright  Copyright (c) 2016, churchthemes.com
 * @link       https://github.com/churchthemes/church-theme-content
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @since      1.7
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/**********************************
 * HELPERS
 **********************************/

/**
 * Google Map types array
 *
 * @since 0.9
 * @return array Google Maps API map types
 */
function ctc_gmaps_types() {

	$types = array(
		'ROADMAP'	=> esc_html_x( 'Road', 'map', 'church-theme-content' ),
		'SATELLITE'	=> esc_html_x( 'Satellite', 'map', 'church-theme-content' ),
		'HYBRID'	=> esc_html_x( 'Hybrid', 'map', 'church-theme-content' ),
		'TERRAIN'	=> esc_html_x( 'Terrain', 'map', 'church-theme-content' )
	);

	return apply_filters( 'ctc_gmaps_types', $types );

}

/**
 * Google Map type default
 *
 * @since 0.9
 * @return string Default map type
 */
function ctc_gmaps_type_default() {
	return apply_filters( 'ctc_gmaps_type_default', 'HYBRID' );
}

/**
 * Zoom levels array
 *
 * @since 0.9
 * @return array Valid Google Maps zoom levels
 */
function ctc_gmaps_zoom_levels() {

	$zoom_levels = array();

	$zoom_min = 1; // 0 is actually lowest but then it's detected as not set and reverts to default
	$zoom_max = 21;

	for ( $z = $zoom_min; $z <= $zoom_max; $z++ ) {
		$zoom_levels[$z] = $z;
	}

	return apply_filters( 'ctc_gmaps_zoom_levels', $zoom_levels );

}

/**
 * Zoom level default
 *
 * @since 0.9
 * @return int Default Google Maps zoom level
 */
function ctc_gmaps_zoom_level_default() {
	return apply_filters( 'ctc_gmaps_zoom_level_default', 14 );
}

/**********************************
 * API KEY NOTICE
 **********************************/

/**
 * Show missing Google Maps API Key notice
 *
 * The notice should only be shown if certain conditions are met.
 *
 * @since 1.7
 * @return bool True if notice should be shown
 */
function ctc_gmaps_api_key_show_notice() {

	$show = true;

	// Only on Add/Edit Location or Event
	$screen = get_current_screen();
	if ( ! ( $screen->base == 'post' && in_array( $screen->post_type, array( 'ctc_event', 'ctc_location' ) ) ) ) {
		$show = false;
	}

	// Only if user can edit plugin settings
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Only if latitude and longitude fields supported
	if (
		( 'ctc_event' == $screen->post_type && ( ! ctc_field_supported( 'events', '_ctc_event_map_lat' ) || ! ctc_field_supported( 'events', '_ctc_event_map_lng' ) ) )
		|| ( 'ctc_location' == $screen->post_type && ( ! ctc_field_supported( 'locations', '_ctc_location_map_lat' ) || ! ctc_field_supported( 'locations', '_ctc_location_map_lng' ) ) )
	) {
		return;
	}

	// Only if key not set
	if ( ctc_setting( 'google_maps_api_key' ) ) {
		$show = false;
	}

	// Only if not already dismissed
	if ( get_option( 'ctc_gmaps_api_key_notice_dismissed' ) ) {
		$show = false;
	}

	return $show;

}

/**
 * Show notice if Google Maps API Key missing
 *
 * This will show only on Add/Edit event or location screen if key is not set and user has permission to set it.
 *
 * @since 1.7
 */
function ctc_gmaps_api_key_notice() {

	// Only on Add/Edit Location or Event, key is not set, not already dismissed and latitude/longitude fields supported
	if ( ! ctc_gmaps_api_key_show_notice() ) {
		return;
	}

	// Show notice
	?>

	<div id="ctc-gmaps-api-key-notice" class="notice notice-warning is-dismissible">
		<p>
			<?php
				printf(
					/* translators: %1$s is URL to guide showing how to get and set key */
					__( '<strong>Google Maps API Key Not Set.</strong> You must set it in <a href="%1$s">Church Theme Content Settings</a> for maps to work.', 'church-theme-content' ),
					admin_url( 'options-general.php?page=' . CTC_DIR )
				);
			?>
		</p>
	</div>

	<?php

}

add_action( 'admin_notices', 'ctc_gmaps_api_key_notice' );

/**
 * JavaScript for remembering Google Maps API Key missing notice was dismissed
 *
 * The dismiss button only closes notice for current page view.
 * This uses AJAX to set an option so that the notice can be hidden indefinitely.
 *
 * @since 1.7
 */
function ctc_gmaps_api_key_dismiss_notice_js() {

	// Only on Add/Edit Location or Event, key is not set, not already dismissed and latitude/longitude fields supported
	if ( ! ctc_gmaps_api_key_show_notice() ) {
		return;
	}

	// Nonce
	$ajax_nonce = wp_create_nonce( 'ctc_gmaps_api_key_dismiss_notice' );

	// JavaScript for detecting click on dismiss button
	?>

	<script type="text/javascript">

	jQuery( document ).ready( function( $ ) {

		$( document ).on( 'click', '#ctc-gmaps-api-key-notice .notice-dismiss', function() {
			$.ajax( {
				url: ajaxurl,
				data: {
					action: 'ctc_gmaps_api_key_dismiss_notice',
					security: '<?php echo esc_js( $ajax_nonce ); ?>',
				},
			} );
		} );

	} );

	</script>

	<?php

}

add_action( 'admin_print_footer_scripts', 'ctc_gmaps_api_key_dismiss_notice_js' );

/**
 * Set option to prevent notice from showing again
 *
 * This is called by AJAX in ctc_gmaps_api_key_dismiss_notice_js()
 *
 * @since 1.7
 */
function ctc_gmaps_api_key_dismiss_notice() {

	// Only if is AJAX request
	if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}

	// Check nonce
	check_ajax_referer( 'ctc_gmaps_api_key_dismiss_notice', 'security' );

	// Only if user is privileged to use screen notice shown on and can edit plugin settings
	if ( ! ( current_user_can( 'edit_posts' ) && current_user_can( 'manage_options' ) ) ) {
		return;
	}

	// Update option so notice is not shown again
	update_option( 'ctc_gmaps_api_key_notice_dismissed', '1' );

}

add_action( 'wp_ajax_ctc_gmaps_api_key_dismiss_notice', 'ctc_gmaps_api_key_dismiss_notice' );
