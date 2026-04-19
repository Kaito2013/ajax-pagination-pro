<?php
/**
 * Image Optimizer Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Optimizer Class
 */
class AJAX_Pagination_Pro_Image_Optimizer {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Image_Optimizer
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Image_Optimizer
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
		add_filter( 'ajax_pagination_post_html', array( $this, 'optimize_images' ), 30, 3 );
		add_action( 'wp_ajax_ajax_pagination_webp_check', array( $this, 'ajax_check_webp_support' ) );
	}

	/**
	 * Optimize images in post HTML.
	 *
	 * @param string $html  Post HTML.
	 * @param object $post  Post object.
	 * @param array  $args  Template arguments.
	 * @return string
	 */
	public function optimize_images( $html, $post, $args ) {
		if ( ! get_option( 'ajax_pagination_image_optimization', '1' ) ) {
			return $html;
		}

		// Add responsive images
		$html = $this->add_responsive_images( $html, $post );

		// Add WebP support
		$html = $this->add_webp_support( $html, $post );

		// Add image dimensions
		$html = $this->add_image_dimensions( $html, $post );

		// Add loading="lazy" for native lazy loading
		if ( get_option( 'ajax_pagination_native_lazy', '1' ) ) {
			$html = $this->add_native_lazy( $html );
		}

		return $html;
	}

	/**
	 * Add responsive images (srcset, sizes).
	 *
	 * @param string $html Post HTML.
	 * @param object $post Post object.
	 * @return string
	 */
	private function add_responsive_images( $html, $post ) {
		$html = preg_replace_callback(
			'/<img\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
			function( $matches ) use ( $post ) {
				$before = $matches[1];
				$src = $matches[2];
				$after = $matches[3];

				// Skip if already has srcset
				if ( strpos( $before . $after, 'srcset' ) !== false ) {
					return $matches[0];
				}

				// Get attachment ID
				$attachment_id = $this->get_attachment_id_from_url( $src );

				if ( ! $attachment_id ) {
					return $matches[0];
				}

				// Get responsive srcset
				$srcset = wp_get_attachment_image_srcset( $attachment_id, 'full' );
				$sizes = wp_get_attachment_image_sizes( $attachment_id, 'full' );

				if ( $srcset ) {
					$img = '<img ' . $before;
					$img .= ' src="' . esc_url( $src ) . '"';
					$img .= ' srcset="' . esc_attr( $srcset ) . '"';
					$img .= ' sizes="' . esc_attr( $sizes ) . '"';
					$img .= ' ' . $after . '>';
					return $img;
				}

				return $matches[0];
			},
			$html
		);

		return $html;
	}

	/**
	 * Add WebP support.
	 *
	 * @param string $html Post HTML.
	 * @param object $post Post object.
	 * @return string
	 */
	private function add_webp_support( $html, $post ) {
		if ( ! get_option( 'ajax_pagination_webp_support', '1' ) ) {
			return $html;
		}

		// Check if browser supports WebP
		if ( ! $this->supports_webp() ) {
			return $html;
		}

		$html = preg_replace_callback(
			'/<img\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
			function( $matches ) {
				$before = $matches[1];
				$src = $matches[2];
				$after = $matches[3];

				// Skip if already WebP
				if ( strpos( $src, '.webp' ) !== false ) {
					return $matches[0];
				}

				// Get WebP version
				$webp_src = $this->get_webp_url( $src );

				if ( $webp_src ) {
					$img = '<img ' . $before;
					$img .= ' src="' . esc_url( $webp_src ) . '"';
					$img .= ' data-original-src="' . esc_url( $src ) . '"';
					$img .= ' ' . $after . '>';
					return $img;
				}

				return $matches[0];
			},
			$html
		);

		return $html;
	}

	/**
	 * Add image dimensions (width, height).
	 *
	 * @param string $html Post HTML.
	 * @param object $post Post object.
	 * @return string
	 */
	private function add_image_dimensions( $html, $post ) {
		$html = preg_replace_callback(
			'/<img\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
			function( $matches ) {
				$before = $matches[1];
				$src = $matches[2];
				$after = $matches[3];

				// Skip if already has dimensions
				if ( strpos( $before . $after, 'width=' ) !== false && strpos( $before . $after, 'height=' ) !== false ) {
					return $matches[0];
				}

				// Get attachment ID
				$attachment_id = $this->get_attachment_id_from_url( $src );

				if ( ! $attachment_id ) {
					return $matches[0];
				}

				// Get image dimensions
				$metadata = wp_get_attachment_metadata( $attachment_id );

				if ( $metadata && isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
					$img = '<img ' . $before;
					$img .= ' src="' . esc_url( $src ) . '"';
					$img .= ' width="' . esc_attr( $metadata['width'] ) . '"';
					<img .= ' height="' . esc_attr( $metadata['height'] ) . '"';
					$img .= ' ' . $after . '>';
					return $img;
				}

				return $matches[0];
			},
			$html
		);

		return $html;
	}

	/**
	 * Add native lazy loading.
	 *
	 * @param string $html Post HTML.
	 * @return string
	 */
	private function add_native_lazy( $html ) {
		$html = preg_replace(
			'/<img\s+/',
			'<img loading="lazy" ',
			$html
		);

		return $html;
	}

	/**
	 * Get WebP URL from image URL.
	 *
	 * @param string $url Image URL.
	 * @return string|false
	 */
	private function get_webp_url( $url ) {
		// Check if WebP file exists
		$upload_dir = wp_upload_dir();
		$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		// Get file info
		$path_info = pathinfo( $file_path );
		$webp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';

		if ( file_exists( $webp_path ) ) {
			return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $webp_path );
		}

		return false;
	}

	/**
	 * Check if browser supports WebP.
	 *
	 * @return bool
	 */
	private function supports_webp() {
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false;
		}

		return false;
	}

	/**
	 * Get attachment ID from URL.
	 *
	 * @param string $url Image URL.
	 * @return int|false
	 */
	private function get_attachment_id_from_url( $url ) {
		global $wpdb;

		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid = %s", $url ) );

		return ! empty( $attachment ) ? absint( $attachment[0] ) : false;
	}

	/**
	 * Check WebP support via AJAX.
	 *
	 * @return void
	 */
	public function ajax_check_webp_support() {
		check_ajax_referer( 'ajax-pagination-nonce', 'nonce' );

		$supports = $this->supports_webp();

		wp_send_json_success( array(
			'supports_webp' => $supports,
		) );
	}

	/**
	 * Get image optimization stats.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;

		// Count images with srcset
		$srcset_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		// Get total image size
		$total_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(guid)) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		return array(
			'total_images' => (int) $srcset_count,
			'total_size'   => $this->format_bytes( $total_size ?: 0 ),
			'webp_support' => $this->supports_webp(),
			'optimization_enabled' => get_option( 'ajax_pagination_image_optimization', '1' ) === '1',
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
}
