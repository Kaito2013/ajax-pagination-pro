<?php
/**
 * Search Integration Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search Integration Class
 */
class AJAX_Pagination_Pro_Search {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Search
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Search
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
		add_shortcode( 'ajax_pagination_search', array( $this, 'render_search_shortcode' ) );
		add_action( 'wp_ajax_ajax_pagination_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_ajax_pagination_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_search_scripts' ) );
	}

	/**
	 * Enqueue search scripts.
	 *
	 * @return void
	 */
	public function enqueue_search_scripts() {
		wp_enqueue_style(
			'ajax-pagination-search',
			AJAX_PAGINATION_PRO_URL . 'assets/css/search.css',
			array( 'ajax-pagination-pro' ),
			AJAX_PAGINATION_PRO_VERSION
		);

		wp_enqueue_script(
			'ajax-pagination-search',
			AJAX_PAGINATION_PRO_URL . 'assets/js/search.js',
			array( 'jquery', 'ajax-pagination-pro' ),
			AJAX_PAGINATION_PRO_VERSION,
			true
		);

		wp_localize_script( 'ajax-pagination-search', 'ajaxPaginationSearch', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'ajax-pagination-nonce' ),
			'searchText'    => __( 'Searching...', 'ajax-pagination-pro' ),
			'noResultsText' => __( 'No results found', 'ajax-pagination-pro' ),
			'minChars'      => 3,
			'delay'         => 500,
		) );
	}

	/**
	 * Render search shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_search_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'post_type'      => 'post',
			'placeholder'    => __( 'Search...', 'ajax-pagination-pro' ),
			'style'          => 'numbered',
			'per_page'       => absint( get_option( 'ajax_pagination_per_page', 10 ) ),
			'columns'        => 3,
			'image_size'     => 'medium',
			'show_image'     => 'true',
			'show_excerpt'   => 'true',
			'show_date'      => 'true',
			'show_author'    => 'false',
			'excerpt_length' => 55,
			'css_class'      => '',
			'button_text'    => __( 'Search', 'ajax-pagination-pro' ),
		), $atts, 'ajax_pagination_search' );

		// Convert string booleans to actual booleans
		$atts['show_image'] = filter_var( $atts['show_image'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_excerpt'] = filter_var( $atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_date'] = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_author'] = filter_var( $atts['show_author'], FILTER_VALIDATE_BOOLEAN );

		ob_start();
		?>
		<div class="ajax-pagination-search-container <?php echo esc_attr( $atts['css_class'] ); ?>"
			 data-post-type="<?php echo esc_attr( $atts['post_type'] ); ?>"
			 data-style="<?php echo esc_attr( $atts['style'] ); ?>"
			 data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>"
			 data-columns="<?php echo esc_attr( $atts['columns'] ); ?>"
			 data-image-size="<?php echo esc_attr( $atts['image_size'] ); ?>"
			 data-show-image="<?php echo $atts['show_image'] ? 'true' : 'false'; ?>"
			 data-show-excerpt="<?php echo $atts['show_excerpt'] ? 'true' : 'false'; ?>"
			 data-show-date="<?php echo $atts['show_date'] ? 'true' : 'false'; ?>"
			 data-show-author="<?php echo $atts['show_author'] ? 'true' : 'false'; ?>"
			 data-excerpt-length="<?php echo esc_attr( $atts['excerpt_length'] ); ?>">

			<!-- Search Form -->
			<div class="ajax-pagination-search-form">
				<div class="ajax-pagination-search-input-wrapper">
					<span class="dashicons dashicons-search"></span>
					<input type="text"
						   class="ajax-pagination-search-input"
						   placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
						   autocomplete="off">
					<button type="button" class="ajax-pagination-search-clear" style="display: none;">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<button type="button" class="ajax-pagination-search-button">
					<?php echo esc_html( $atts['button_text'] ); ?>
				</button>
			</div>

			<!-- Search Results -->
			<div class="ajax-pagination-search-results">
				<!-- Results will be loaded here via AJAX -->
			</div>

			<!-- Loading State -->
			<div class="ajax-pagination-search-loading" style="display: none;">
				<div class="ajax-pagination-spinner"></div>
				<p><?php esc_html_e( 'Searching...', 'ajax-pagination-pro' ); ?></p>
			</div>

			<!-- No Results -->
			<div class="ajax-pagination-search-empty" style="display: none;">
				<span class="dashicons dashicons-search"></span>
				<p><?php esc_html_e( 'No results found', 'ajax-pagination-pro' ); ?></p>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX search handler.
	 *
	 * @return void
	 */
	public function ajax_search() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ajax-pagination-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'ajax-pagination-pro' ) ) );
		}

		// Get search query
		$search_query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$columns = isset( $_POST['columns'] ) ? absint( $_POST['columns'] ) : 3;
		$image_size = isset( $_POST['image_size'] ) ? sanitize_text_field( $_POST['image_size'] ) : 'medium';
		$show_image = isset( $_POST['show_image'] ) ? filter_var( $_POST['show_image'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_excerpt = isset( $_POST['show_excerpt'] ) ? filter_var( $_POST['show_excerpt'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_date = isset( $_POST['show_date'] ) ? filter_var( $_POST['show_date'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_author = isset( $_POST['show_author'] ) ? filter_var( $_POST['show_author'], FILTER_VALIDATE_BOOLEAN ) : false;
		$excerpt_length = isset( $_POST['excerpt_length'] ) ? absint( $_POST['excerpt_length'] ) : 55;
		$style = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'numbered';

		// Validate search query
		if ( empty( $search_query ) || strlen( $search_query ) < 3 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter at least 3 characters', 'ajax-pagination-pro' ) ) );
		}

		// Search posts
		$query_args = array(
			'post_type'           => $post_type,
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			's'                   => $search_query,
			'ignore_sticky_posts' => true,
			'post_status'         => 'publish',
		);

		$query = new WP_Query( $query_args );

		// Render results
		$core = AJAX_Pagination_Pro_Core::get_instance();
		$results_html = '';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$results_html .= $core->render_post_card( $query->post, array(
					'columns'        => $columns,
					'image_size'     => $image_size,
					'show_image'     => $show_image,
					'show_excerpt'   => $show_excerpt,
					'show_date'      => $show_date,
					'show_author'    => $show_author,
					'excerpt_length' => $excerpt_length,
				) );
			}
			wp_reset_postdata();
		}

		// Render pagination
		$pagination_html = '';
		if ( 'numbered' === $style ) {
			$pagination_html = $core->render_numbered_pagination( $page, $query->max_num_pages );
		} else {
			$pagination_html = $core->render_load_more_button( $page, $query->max_num_pages );
		}

		// Return response
		wp_send_json_success( array(
			'html'          => $results_html,
			'pagination'    => $pagination_html,
			'found_posts'   => $query->found_posts,
			'max_num_pages' => $query->max_num_pages,
			'current_page'  => $page,
		) );
	}
}
