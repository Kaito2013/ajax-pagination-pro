<?php
/**
 * CDN Support Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CDN Support Class
 */
class AJAX_Pagination_Pro_CDN {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_CDN
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_CDN
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter( 'ajax_pagination_post_html', array( $this, 'rewrite_urls' ), 40, 3 );
		add_action( 'wp_ajax_ajax_pagination_cdn_test', array( $this, 'ajax_test_cdn' ) );
	}

	/**
	 * Rewrite URLs to CDN.
	 *
	 * @param string $html  Post HTML.
	 * @param object $post  Post object.
	 * @param array  $args  Template arguments.
	 * @return string
	 */
	public function rewrite_urls( $html, $post, $args ) {
		$cdn_url = get_option( 'ajax_pagination_cdn_url', '' );

		if ( empty( $cdn_url ) ) {
			return $html;
		}

		$site_url = site_url();

		// Rewrite image URLs
		$html = str_replace( $site_url, $cdn_url, $html );

		return $html;
	}

	/**
	 * Get CDN URL for asset.
	 *
	 * @param string $url Original URL.
	 * @return string
	 */
	public function get_cdn_url( $url ) {
		$cdn_url = get_option( 'ajax_pagination_cdn_url', '' );

		if ( empty( $cdn_url ) ) {
			return $url;
		}

		$site_url = site_url();

		return str_replace( $site_url, $cdn_url, $url );
	}

	/**
	 * Test CDN connection via AJAX.
	 *
	 * @return void
	 */
	public function ajax_test_cdn() {
		check_ajax_referer( 'ajax-pagination-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ajax-pagination-pro' ) ) );
		}

		$cdn_url = get_option( 'ajax_pagination_cdn_url', '' );

		if ( empty( $cdn_url ) ) {
			wp_send_json_error( array( 'message' => __( 'CDN URL not configured', 'ajax-pagination-pro' ) ) );
		}

		// Test CDN connection
		$response = wp_remote_get( $cdn_url, array(
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message' => __( 'CDN connection failed: ', 'ajax-pagination-pro' ) . $response->get_error_message(),
			) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code || 301 === $status_code || 302 === $status_code ) {
			wp_send_json_success( array(
				'message' => __( 'CDN connection successful', 'ajax-pagination-pro' ),
				'status'  => $status_code,
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf( __( 'CDN returned status code: %d', 'ajax-pagination-pro' ), $status_code ),
			) );
		}
	}

	/**
	 * Get CDN stats.
	 *
	 * @return array
	 */
	public function get_stats() {
		return array(
			'enabled' => ! empty( get_option( 'ajax_pagination_cdn_url', '' ) ),
			'url'     => get_option( 'ajax_pagination_cdn_url', '' ),
		);
	}
}
