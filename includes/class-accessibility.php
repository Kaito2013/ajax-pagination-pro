<?php
/**
 * Accessibility Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accessibility Class
 */
class AJAX_Pagination_Pro_Accessibility {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Accessibility
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Accessibility
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
		add_filter( 'ajax_pagination_post_html', array( $this, 'add_aria_attributes' ), 50, 3 );
		add_action( 'wp_footer', array( $this, 'add_live_region' ) );
	}

	/**
	 * Enqueue accessibility scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! get_option( 'ajax_pagination_accessibility', '1' ) ) {
			return;
		}

		wp_enqueue_script(
			'ajax-pagination-accessibility',
			AJAX_PAGINATION_PRO_URL . 'assets/js/accessibility.js',
			array( 'jquery' ),
			AJAX_PAGINATION_PRO_VERSION,
			true
		);

		wp_localize_script( 'ajax-pagination-accessibility', 'ajaxPaginationA11y', array(
			'pageLoaded'     => __( 'Page %d loaded', 'ajax-pagination-pro' ),
			'loading'        => __( 'Loading...', 'ajax-pagination-pro' ),
			'noResults'      => __( 'No results found', 'ajax-pagination-pro' ),
			'firstPage'      => __( 'First page', 'ajax-pagination-pro' ),
			'lastPage'       => __( 'Last page', 'ajax-pagination-pro' ),
			'previousPage'   => __( 'Previous page', 'ajax-pagination-pro' ),
			'nextPage'       => __( 'Next page', 'ajax-pagination-pro' ),
			'currentPage'    => __( 'Page %d of %d', 'ajax-pagination-pro' ),
		) );

		wp_enqueue_style(
			'ajax-pagination-accessibility',
			AJAX_PAGINATION_PRO_URL . 'assets/css/accessibility.css',
			array(),
			AJAX_PAGINATION_PRO_VERSION
		);
	}

	/**
	 * Add ARIA attributes to post HTML.
	 *
	 * @param string $html  Post HTML.
	 * @param object $post  Post object.
	 * @param array  $args  Template arguments.
	 * @return string
	 */
	public function add_aria_attributes( $html, $post, $args ) {
		if ( ! get_option( 'ajax_pagination_accessibility', '1' ) ) {
			return $html;
		}

		// Add role="article" to article elements
		$html = str_replace( '<article', '<article role="article"', $html );

		// Add aria-label to links
		$html = preg_replace(
			'/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/',
			'<a $1href="$2"$3 aria-label="' . esc_attr( get_the_title( $post->ID ) ) . '">',
			$html
		);

		// Add alt text to images without alt
		$html = preg_replace(
			'/<img\s+([^>]*?)(?!alt=)([^>]*?)>/',
			'<img $1 alt="' . esc_attr( get_the_title( $post->ID ) ) . '" $2>',
			$html
		);

		return $html;
	}

	/**
	 * Add ARIA live region for announcements.
	 *
	 * @return void
	 */
	public function add_live_region() {
		if ( ! get_option( 'ajax_pagination_accessibility', '1' ) ) {
			return;
		}
		?>
		<div id="ajax-pagination-live-region" 
			 class="ajax-pagination-sr-only" 
			 role="status" 
			 aria-live="polite" 
			 aria-atomic="true">
		</div>
		<?php
	}
}
