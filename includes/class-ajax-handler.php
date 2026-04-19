<?php
/**
 * AJAX Handler Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handler Class
 */
class AJAX_Pagination_Pro_AJAX {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_AJAX
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_AJAX
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
		add_action( 'wp_ajax_ajax_pagination_load', array( $this, 'ajax_load_page' ) );
		add_action( 'wp_ajax_nopriv_ajax_pagination_load', array( $this, 'ajax_load_page' ) );
		add_action( 'wp_ajax_ajax_pagination_load_more', array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_ajax_pagination_load_more', array( $this, 'ajax_load_more' ) );
	}

	/**
	 * Load page via AJAX (numbered pagination).
	 *
	 * @return void
	 */
	public function ajax_load_page() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ajax-pagination-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'ajax-pagination-pro' ) ) );
		}

		// Get parameters
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : absint( get_option( 'ajax_pagination_per_page', 10 ) );
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( $_POST['taxonomy'] ) : '';
		$term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';
		$orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'date';
		$order = isset( $_POST['order'] ) ? sanitize_text_field( $_POST['order'] ) : 'DESC';
		$columns = isset( $_POST['columns'] ) ? absint( $_POST['columns'] ) : 3;
		$image_size = isset( $_POST['image_size'] ) ? sanitize_text_field( $_POST['image_size'] ) : 'medium';
		$show_image = isset( $_POST['show_image'] ) ? filter_var( $_POST['show_image'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_excerpt = isset( $_POST['show_excerpt'] ) ? filter_var( $_POST['show_excerpt'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_date = isset( $_POST['show_date'] ) ? filter_var( $_POST['show_date'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_author = isset( $_POST['show_author'] ) ? filter_var( $_POST['show_author'], FILTER_VALIDATE_BOOLEAN ) : false;
		$excerpt_length = isset( $_POST['excerpt_length'] ) ? absint( $_POST['excerpt_length'] ) : 55;
		$style = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'numbered';

		// Validate post type
		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post type', 'ajax-pagination-pro' ) ) );
		}

		// Get posts
		$core = AJAX_Pagination_Pro_Core::get_instance();
		$result = $core->get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'category'       => $category,
			'taxonomy'       => $taxonomy,
			'term'           => $term,
			'orderby'        => $orderby,
			'order'          => $order,
		) );

		// Render posts HTML
		$posts_html = '';
		foreach ( $result['posts'] as $post ) {
			$posts_html .= $core->render_post_card( $post, array(
				'columns'        => $columns,
				'image_size'     => $image_size,
				'show_image'     => $show_image,
				'show_excerpt'   => $show_excerpt,
				'show_date'      => $show_date,
				'show_author'    => $show_author,
				'excerpt_length' => $excerpt_length,
			) );
		}

		// Render pagination
		$pagination_html = '';
		if ( 'numbered' === $style ) {
			$pagination_html = $core->render_numbered_pagination( $page, $result['max_num_pages'] );
		} else {
			$pagination_html = $core->render_load_more_button( $page, $result['max_num_pages'] );
		}

		// Return response
		wp_send_json_success( array(
			'html'          => $posts_html,
			'pagination'    => $pagination_html,
			'current_page'  => $page,
			'total_pages'   => $result['max_num_pages'],
			'total_posts'   => $result['found_posts'],
		) );
	}

	/**
	 * Load more posts via AJAX.
	 *
	 * @return void
	 */
	public function ajax_load_more() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ajax-pagination-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'ajax-pagination-pro' ) ) );
		}

		// Get parameters
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : absint( get_option( 'ajax_pagination_per_page', 10 ) );
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_text_field( $_POST['taxonomy'] ) : '';
		$term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';
		$orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'date';
		$order = isset( $_POST['order'] ) ? sanitize_text_field( $_POST['order'] ) : 'DESC';
		$columns = isset( $_POST['columns'] ) ? absint( $_POST['columns'] ) : 3;
		$image_size = isset( $_POST['image_size'] ) ? sanitize_text_field( $_POST['image_size'] ) : 'medium';
		$show_image = isset( $_POST['show_image'] ) ? filter_var( $_POST['show_image'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_excerpt = isset( $_POST['show_excerpt'] ) ? filter_var( $_POST['show_excerpt'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_date = isset( $_POST['show_date'] ) ? filter_var( $_POST['show_date'], FILTER_VALIDATE_BOOLEAN ) : true;
		$show_author = isset( $_POST['show_author'] ) ? filter_var( $_POST['show_author'], FILTER_VALIDATE_BOOLEAN ) : false;
		$excerpt_length = isset( $_POST['excerpt_length'] ) ? absint( $_POST['excerpt_length'] ) : 55;

		// Validate post type
		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post type', 'ajax-pagination-pro' ) ) );
		}

		// Get posts
		$core = AJAX_Pagination_Pro_Core::get_instance();
		$result = $core->get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'offset'         => $offset,
			'category'       => $category,
			'taxonomy'       => $taxonomy,
			'term'           => $term,
			'orderby'        => $orderby,
			'order'          => $order,
		) );

		// Render posts HTML
		$posts_html = '';
		foreach ( $result['posts'] as $post ) {
			$posts_html .= $core->render_post_card( $post, array(
				'columns'        => $columns,
				'image_size'     => $image_size,
				'show_image'     => $show_image,
				'show_excerpt'   => $show_excerpt,
				'show_date'      => $show_date,
				'show_author'    => $show_author,
				'excerpt_length' => $excerpt_length,
			) );
		}

		// Check if has more posts
		$has_more = $page < $result['max_num_pages'];

		// Return response
		wp_send_json_success( array(
			'html'         => $posts_html,
			'has_more'     => $has_more,
			'next_page'    => $page + 1,
			'total_posts'  => $result['found_posts'],
			'current_page' => $page,
			'total_pages'  => $result['max_num_pages'],
		) );
	}
}
