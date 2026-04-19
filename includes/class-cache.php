<?php
/**
 * Caching Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caching Class
 */
class AJAX_Pagination_Pro_Cache {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Cache
	 */
	private static $instance = null;

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'ajax-pagination-pro';

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Cache
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
		add_action( 'wp_ajax_ajax_pagination_clear_cache', array( $this, 'ajax_clear_cache' ) );
		add_action( 'save_post', array( $this, 'clear_cache_on_save' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'clear_cache_on_delete' ) );
	}

	/**
	 * Get cached data.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false
	 */
	public function get( $key ) {
		if ( ! get_option( 'ajax_pagination_cache_enabled', '0' ) ) {
			return false;
		}

		// Try object cache first
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// Try transient
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			// Store in object cache for faster access
			wp_cache_set( $key, $cached, self::CACHE_GROUP, $this->get_cache_duration() );
			return $cached;
		}

		return false;
	}

	/**
	 * Set cached data.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $data  Data to cache.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool
	 */
	public function set( $key, $data, $ttl = null ) {
		if ( ! get_option( 'ajax_pagination_cache_enabled', '0' ) ) {
			return false;
		}

		if ( null === $ttl ) {
			$ttl = $this->get_cache_duration();
		}

		// Store in object cache
		wp_cache_set( $key, $data, self::CACHE_GROUP, $ttl );

		// Store in transient
		return set_transient( $key, $data, $ttl );
	}

	/**
	 * Delete cached data.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( $key ) {
		// Delete from object cache
		wp_cache_delete( $key, self::CACHE_GROUP );

		// Delete from transient
		return delete_transient( $key );
	}

	/**
	 * Clear all cache.
	 *
	 * @return bool
	 */
	public function clear_all() {
		global $wpdb;

		// Get all transients
		$transients = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_ajax_pagination_%'"
		);

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient );
			delete_transient( $key );
		}

		// Flush object cache group
		wp_cache_flush();

		return true;
	}

	/**
	 * Generate cache key.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public function generate_key( $args ) {
		// Sort args for consistent keys
		ksort( $args );

		// Add page to key
		$page = isset( $args['paged'] ) ? $args['paged'] : 1;
		unset( $args['paged'] );

		// Generate key
		$key = 'ajax_pagination_' . md5( serialize( $args ) ) . '_page_' . $page;

		return $key;
	}

	/**
	 * Get cache duration.
	 *
	 * @return int
	 */
	private function get_cache_duration() {
		return absint( get_option( 'ajax_pagination_cache_duration', 300 ) );
	}

	/**
	 * Get cache stats.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;

		// Count cached transients
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_ajax_pagination_%'"
		);

		// Get cache size
		$size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_ajax_pagination_%'"
		);

		return array(
			'count' => (int) $count,
			'size'  => $this->format_bytes( $size ?: 0 ),
			'enabled' => get_option( 'ajax_pagination_cache_enabled', '0' ) === '1',
			'duration' => $this->get_cache_duration(),
		);
	}

	/**
	 * Format bytes to human readable.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}

	/**
	 * Clear cache via AJAX.
	 *
	 * @return void
	 */
	public function ajax_clear_cache() {
		check_ajax_referer( 'ajax-pagination-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ajax-pagination-pro' ) ) );
		}

		$this->clear_all();

		wp_send_json_success( array(
			'message' => __( 'Cache cleared successfully', 'ajax-pagination-pro' ),
		) );
	}

	/**
	 * Clear cache when post is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function clear_cache_on_save( $post_id, $post, $update ) {
		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip if not published
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Clear cache for this post type
		$this->clear_cache_for_post_type( $post->post_type );
	}

	/**
	 * Clear cache when post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_cache_on_delete( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( $post_type ) {
			$this->clear_cache_for_post_type( $post_type );
		}
	}

	/**
	 * Clear cache for specific post type.
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	private function clear_cache_for_post_type( $post_type ) {
		global $wpdb;

		// Get all transients for this post type
		$transients = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_ajax_pagination_%' . $post_type . '%'
		) );

		foreach ( $transients as $transient ) {
			$key = str_replace( '_transient_', '', $transient );
			delete_transient( $key );
		}
	}
}
