<?php
/**
 * Lazy Loading Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy Loading Class
 */
class AJAX_Pagination_Pro_Lazy_Loading {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Lazy_Loading
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Lazy_Loading
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'ajax_pagination_post_html', array( $this, 'apply_lazy_loading' ), 20, 3 );
		add_action( 'wp_head', array( $this, 'add_preload_hint' ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! get_option( 'ajax_pagination_lazy_loading', '1' ) ) {
			return;
		}

		wp_enqueue_script(
			'ajax-pagination-lazy',
			AJAX_PAGINATION_PRO_URL . 'assets/js/lazy-loading.js',
			array(),
			AJAX_PAGINATION_PRO_VERSION,
			true
		);

		wp_localize_script( 'ajax-pagination-lazy', 'ajaxPaginationLazy', array(
			'rootMargin'    => absint( get_option( 'ajax_pagination_lazy_root_margin', 50 ) ) . 'px',
			'threshold'     => floatval( get_option( 'ajax_pagination_lazy_threshold', 0.1 ) ),
			'fadeDuration'  => absint( get_option( 'ajax_pagination_lazy_fade_duration', 300 ) ),
			'placeholder'   => get_option( 'ajax_pagination_lazy_placeholder', 'skeleton' ),
		) );
	}

	/**
	 * Apply lazy loading to post HTML.
	 *
	 * @param string $html  Post HTML.
	 * @param object $post  Post object.
	 * @param array  $args  Template arguments.
	 * @return string
	 */
	public function apply_lazy_loading( $html, $post, $args ) {
		if ( ! get_option( 'ajax_pagination_lazy_loading', '1' ) ) {
			return $html;
		}

		// Find all images in HTML
		$html = preg_replace_callback(
			'/<img\s+([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
			function( $matches ) use ( $args ) {
				$before = $matches[1];
				$src = $matches[2];
				$after = $matches[3];

				// Skip if already lazy loaded
				if ( strpos( $before, 'data-src' ) !== false || strpos( $after, 'data-src' ) !== false ) {
					return $matches[0];
				}

				// Get placeholder
				$placeholder = $this->get_placeholder( $src );

				// Get srcset if exists
				$srcset = '';
				if ( preg_match( '/srcset=["\']([^"\']+)["\']/', $before . $after, $srcset_matches ) ) {
					$srcset = $srcset_matches[1];
					$before = str_replace( 'srcset="' . $srcset . '"', '', $before );
					$after = str_replace( 'srcset="' . $srcset . '"', '', $after );
				}

				// Get sizes if exists
				$sizes = '';
				if ( preg_match( '/sizes=["\']([^"\']+)["\']/', $before . $after, $sizes_matches ) ) {
					$sizes = $sizes_matches[1];
				}

				// Build lazy image
				$lazy_img = '<img ' . $before;
				$lazy_img .= ' class="ajax-pagination-lazy"';
				$lazy_img .= ' src="' . esc_url( $placeholder ) . '"';
				$lazy_img .= ' data-src="' . esc_url( $src ) . '"';

				if ( $srcset ) {
					$lazy_img .= ' data-srcset="' . esc_attr( $srcset ) . '"';
				}

				if ( $sizes ) {
					$lazy_img .= ' sizes="' . esc_attr( $sizes ) . '"';
				}

				$lazy_img .= ' ' . $after . '>';

				return $lazy_img;
			},
			$html
		);

		return $html;
	}

	/**
	 * Get placeholder image.
	 *
	 * @param string $src Original image URL.
	 * @return string
	 */
	private function get_placeholder( $src ) {
		$placeholder_type = get_option( 'ajax_pagination_lazy_placeholder', 'skeleton' );

		switch ( $placeholder_type ) {
			case 'blur':
				return $this->get_blur_placeholder( $src );

			case 'color':
				return $this->get_color_placeholder();

			case 'skeleton':
			default:
				return $this->get_skeleton_placeholder();
		}
	}

	/**
	 * Get skeleton placeholder.
	 *
	 * @return string
	 */
	private function get_skeleton_placeholder() {
		// Return a 1x1 transparent GIF
		return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
	}

	/**
	 * Get blur placeholder.
	 *
	 * @param string $src Original image URL.
	 * @return string
	 */
	private function get_blur_placeholder( $src ) {
		// Try to get a tiny version of the image
		$attachment_id = $this->get_attachment_id_from_url( $src );

		if ( $attachment_id ) {
			$thumbnail = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
			if ( $thumbnail ) {
				return $thumbnail[0];
			}
		}

		// Fallback to skeleton
		return $this->get_skeleton_placeholder();
	}

	/**
	 * Get color placeholder.
	 *
	 * @return string
	 */
	private function get_color_placeholder() {
		$color = get_option( 'ajax_pagination_lazy_placeholder_color', '#f6f7f7' );
		$color = ltrim( $color, '#' );

		// Return SVG with color
		return 'data:image/svg+xml,' . urlencode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300">' .
			'<rect width="400" height="300" fill="#' . esc_attr( $color ) . '"/>' .
			'</svg>'
		);
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
	 * Add preload hint for first image.
	 *
	 * @return void
	 */
	public function add_preload_hint() {
		if ( ! get_option( 'ajax_pagination_lazy_preload', '1' ) ) {
			return;
		}

		// Preload first image in viewport
		echo '<link rel="preload" as="image" href="' . esc_url( $this->get_skeleton_placeholder() ) . '">';
	}
}
