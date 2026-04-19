<?php
/**
 * Performance Dashboard Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance Dashboard Class
 */
class AJAX_Pagination_Pro_Performance {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Performance
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Performance
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
		add_action( 'wp_ajax_ajax_pagination_performance_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_ajax_pagination_clear_all_cache', array( $this, 'ajax_clear_all_cache' ) );
	}

	/**
	 * Get all performance stats.
	 *
	 * @return array
	 */
	public function get_all_stats() {
		$cache = AJAX_Pagination_Pro_Cache::get_instance();
		$image_optimizer = AJAX_Pagination_Pro_Image_Optimizer::get_instance();
		$cdn = AJAX_Pagination_Pro_CDN::get_instance();

		return array(
			'cache'    => $cache->get_stats(),
			'images'   => $image_optimizer->get_stats(),
			'cdn'      => $cdn->get_stats(),
			'server'   => $this->get_server_stats(),
			'wordpress' => $this->get_wordpress_stats(),
		);
	}

	/**
	 * Get server stats.
	 *
	 * @return array
	 */
	private function get_server_stats() {
		return array(
			'php_version'    => phpversion(),
			'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'memory_limit'   => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
		);
	}

	/**
	 * Get WordPress stats.
	 *
	 * @return array
	 */
	private function get_wordpress_stats() {
		global $wpdb;

		// Count posts by type
		$post_counts = $wpdb->get_results(
			"SELECT post_type, COUNT(*) as count FROM {$wpdb->posts} WHERE post_status = 'publish' GROUP BY post_type",
			OBJECT_K
		);

		$counts = array();
		foreach ( $post_counts as $type => $data ) {
			$counts[ $type ] = (int) $data->count;
		}

		return array(
			'version'     => get_bloginfo( 'version' ),
			'post_counts' => $counts,
			'active_plugins' => count( get_option( 'active_plugins', array() ) ),
			'theme'       => wp_get_theme()->get( 'Name' ),
		);
	}

	/**
	 * Get performance score.
	 *
	 * @return array
	 */
	public function get_performance_score() {
		$score = 100;
		$issues = array();

		// Check caching
		if ( ! get_option( 'ajax_pagination_cache_enabled', '0' ) ) {
			$score -= 20;
			$issues[] = __( 'Caching is disabled', 'ajax-pagination-pro' );
		}

		// Check lazy loading
		if ( ! get_option( 'ajax_pagination_lazy_loading', '1' ) ) {
			$score -= 15;
			$issues[] = __( 'Lazy loading is disabled', 'ajax-pagination-pro' );
		}

		// Check image optimization
		if ( ! get_option( 'ajax_pagination_image_optimization', '1' ) ) {
			$score -= 10;
			$issues[] = __( 'Image optimization is disabled', 'ajax-pagination-pro' );
		}

		// Check WebP support
		if ( ! get_option( 'ajax_pagination_webp_support', '1' ) ) {
			$score -= 5;
			$issues[] = __( 'WebP support is disabled', 'ajax-pagination-pro' );
		}

		// Check native lazy loading
		if ( ! get_option( 'ajax_pagination_native_lazy', '1' ) ) {
			$score -= 5;
			$issues[] = __( 'Native lazy loading is disabled', 'ajax-pagination-pro' );
		}

		return array(
			'score'  => max( 0, $score ),
			'grade'  => $this->get_grade( $score ),
			'issues' => $issues,
		);
	}

	/**
	 * Get grade from score.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	private function get_grade( $score ) {
		if ( $score >= 90 ) {
			return 'A';
		} elseif ( $score >= 80 ) {
			return 'B';
		} elseif ( $score >= 70 ) {
			return 'C';
		} elseif ( $score >= 60 ) {
			return 'D';
		} else {
			return 'F';
		}
	}

	/**
	 * Get stats via AJAX.
	 *
	 * @return void
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'ajax-pagination-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ajax-pagination-pro' ) ) );
		}

		wp_send_json_success( array(
			'stats'  => $this->get_all_stats(),
			'score'  => $this->get_performance_score(),
		) );
	}

	/**
	 * Clear all cache via AJAX.
	 *
	 * @return void
	 */
	public function ajax_clear_all_cache() {
		check_ajax_referer( 'ajax-pagination-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ajax-pagination-pro' ) ) );
		}

		$cache = AJAX_Pagination_Pro_Cache::get_instance();
		$cache->clear_all();

		wp_send_json_success( array(
			'message' => __( 'All cache cleared successfully', 'ajax-pagination-pro' ),
		) );
	}
}
