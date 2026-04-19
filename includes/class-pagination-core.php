<?php
/**
 * Pagination Core Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pagination Core Class
 */
class AJAX_Pagination_Pro_Core {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Core
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Core
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
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// CSS
		wp_enqueue_style(
			'ajax-pagination-pro',
			AJAX_PAGINATION_PRO_URL . 'assets/css/pagination.css',
			array(),
			AJAX_PAGINATION_PRO_VERSION
		);

		// JS
		wp_enqueue_script(
			'ajax-pagination-pro',
			AJAX_PAGINATION_PRO_URL . 'assets/js/pagination.js',
			array( 'jquery' ),
			AJAX_PAGINATION_PRO_VERSION,
			true
		);

		// Localize script
		wp_localize_script( 'ajax-pagination-pro', 'ajaxPagination', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'ajax-pagination-nonce' ),
			'loadingText'   => get_option( 'ajax_pagination_loading_text', __( 'Loading...', 'ajax-pagination-pro' ) ),
			'noPostsText'   => get_option( 'ajax_pagination_no_posts_text', __( 'No posts found', 'ajax-pagination-pro' ) ),
			'animationSpeed' => absint( get_option( 'ajax_pagination_animation_speed', 300 ) ),
			'updateUrl'     => get_option( 'ajax_pagination_update_url', '1' ) === '1',
			'infiniteScroll' => get_option( 'ajax_pagination_infinite_scroll', '0' ) === '1',
			'scrollThreshold' => absint( get_option( 'ajax_pagination_scroll_threshold', 200 ) ),
		) );
	}

	/**
	 * Get posts for pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_posts( $args = array() ) {
		$defaults = array(
			'post_type'      => 'post',
			'posts_per_page' => absint( get_option( 'ajax_pagination_per_page', 10 ) ),
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'category'       => '',
			'taxonomy'       => '',
			'term'           => '',
			'offset'         => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build tax query if needed
		$tax_query = array();
		if ( ! empty( $args['taxonomy'] ) && ! empty( $args['term'] ) ) {
			$tax_query[] = array(
				'taxonomy' => $args['taxonomy'],
				'field'    => 'slug',
				'terms'    => $args['term'],
			);
		} elseif ( ! empty( $args['category'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => $args['category'],
			);
		}

		// Build query args
		$query_args = array(
			'post_type'           => $args['post_type'],
			'posts_per_page'      => $args['posts_per_page'],
			'paged'               => $args['paged'],
			'orderby'             => $args['orderby'],
			'order'               => $args['order'],
			'ignore_sticky_posts' => true,
			'post_status'         => 'publish',
		);

		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		if ( $args['offset'] > 0 ) {
			$query_args['offset'] = $args['offset'];
		}

		$query = new WP_Query( $query_args );

		return array(
			'posts'       => $query->posts,
			'found_posts' => $query->found_posts,
			'max_num_pages' => $query->max_num_pages,
			'current_page' => $args['paged'],
		);
	}

	/**
	 * Render post card HTML.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Display arguments.
	 * @return string
	 */
	public function render_post_card( $post, $args = array() ) {
		$defaults = array(
			'columns'        => 3,
			'image_size'     => 'medium',
			'show_image'     => true,
			'show_excerpt'   => true,
			'show_date'      => true,
			'show_author'    => false,
			'excerpt_length' => 55,
		);

		$args = wp_parse_args( $args, $defaults );

		$html = '<article class="ajax-pagination-card ajax-pagination-columns-' . esc_attr( $args['columns'] ) . '">';

		// Featured image
		if ( $args['show_image'] && has_post_thumbnail( $post->ID ) ) {
			$html .= '<div class="ajax-pagination-card-image">';
			$html .= '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">';
			$html .= get_the_post_thumbnail( $post->ID, $args['image_size'], array(
				'alt' => esc_attr( get_the_title( $post->ID ) ),
			) );
			$html .= '</a>';
			$html .= '</div>';
		}

		// Content
		$html .= '<div class="ajax-pagination-card-content">';

		// Title
		$html .= '<h3 class="ajax-pagination-card-title">';
		$html .= '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">';
		$html .= esc_html( get_the_title( $post->ID ) );
		$html .= '</a>';
		$html .= '</h3>';

		// Meta
		$html .= '<div class="ajax-pagination-card-meta">';

		if ( $args['show_date'] ) {
			$html .= '<span class="ajax-pagination-card-date">';
			$html .= '<span class="dashicons dashicons-calendar-alt"></span>';
			$html .= esc_html( get_the_date( '', $post->ID ) );
			$html .= '</span>';
		}

		if ( $args['show_author'] ) {
			$html .= '<span class="ajax-pagination-card-author">';
			$html .= '<span class="dashicons dashicons-admin-users"></span>';
			$html .= esc_html( get_the_author_meta( 'display_name', $post->post_author ) );
			$html .= '</span>';
		}

		$html .= '</div>';

		// Excerpt
		if ( $args['show_excerpt'] ) {
			$excerpt = has_excerpt( $post->ID )
				? get_the_excerpt( $post->ID )
				: wp_trim_words( strip_shortcodes( $post->post_content ), $args['excerpt_length'] );

			$html .= '<div class="ajax-pagination-card-excerpt">';
			$html .= '<p>' . esc_html( $excerpt ) . '</p>';
			$html .= '</div>';
		}

		// Read more link
		$html .= '<a href="' . esc_url( get_permalink( $post->ID ) ) . '" class="ajax-pagination-card-link">';
		$html .= esc_html__( 'Read More', 'ajax-pagination-pro' );
		$html .= ' <span class="dashicons dashicons-arrow-right-alt"></span>';
		$html .= '</a>';

		$html .= '</div>'; // .ajax-pagination-card-content
		$html .= '</article>';

		return $html;
	}

	/**
	 * Render numbered pagination.
	 *
	 * @param int   $current_page Current page number.
	 * @param int   $total_pages  Total number of pages.
	 * @param array $args         Pagination arguments.
	 * @return string
	 */
	public function render_numbered_pagination( $current_page, $total_pages, $args = array() ) {
		if ( $total_pages <= 1 ) {
			return '';
		}

		$html = '<nav class="ajax-pagination-numbers" aria-label="' . esc_attr__( 'Pagination', 'ajax-pagination-pro' ) . '">';

		// Previous button
		if ( $current_page > 1 ) {
			$html .= '<a href="#" class="ajax-pagination-prev ajax-pagination-link" data-page="' . esc_attr( $current_page - 1 ) . '">';
			$html .= '<span class="dashicons dashicons-arrow-left-alt2"></span>';
			$html .= esc_html__( 'Previous', 'ajax-pagination-pro' );
			$html .= '</a>';
		}

		// Page numbers
		$html .= '<div class="ajax-pagination-pages">';

		$range = 2; // Show 2 pages before and after current
		$start = max( 1, $current_page - $range );
		$end = min( $total_pages, $current_page + $range );

		// First page
		if ( $start > 1 ) {
			$html .= '<a href="#" class="ajax-pagination-link" data-page="1">1</a>';
			if ( $start > 2 ) {
				$html .= '<span class="ajax-pagination-ellipsis">...</span>';
			}
		}

		// Page numbers
		for ( $i = $start; $i <= $end; $i++ ) {
			if ( $i === $current_page ) {
				$html .= '<span class="ajax-pagination-current">' . $i . '</span>';
			} else {
				$html .= '<a href="#" class="ajax-pagination-link" data-page="' . esc_attr( $i ) . '">' . $i . '</a>';
			}
		}

		// Last page
		if ( $end < $total_pages ) {
			if ( $end < $total_pages - 1 ) {
				$html .= '<span class="ajax-pagination-ellipsis">...</span>';
			}
			$html .= '<a href="#" class="ajax-pagination-link" data-page="' . esc_attr( $total_pages ) . '">' . $total_pages . '</a>';
		}

		$html .= '</div>';

		// Next button
		if ( $current_page < $total_pages ) {
			$html .= '<a href="#" class="ajax-pagination-next ajax-pagination-link" data-page="' . esc_attr( $current_page + 1 ) . '">';
			$html .= esc_html__( 'Next', 'ajax-pagination-pro' );
			$html .= '<span class="dashicons dashicons-arrow-right-alt2"></span>';
			$html .= '</a>';
		}

		$html .= '</nav>';

		return $html;
	}

	/**
	 * Render load more button.
	 *
	 * @param int   $current_page Current page number.
	 * @param int   $total_pages  Total number of pages.
	 * @param array $args         Button arguments.
	 * @return string
	 */
	public function render_load_more_button( $current_page, $total_pages, $args = array() ) {
		if ( $current_page >= $total_pages ) {
			return '';
		}

		$loading_text = isset( $args['loading_text'] ) ? $args['loading_text'] : __( 'Loading...', 'ajax-pagination-pro' );
		$button_text = isset( $args['button_text'] ) ? $args['button_text'] : __( 'Load More', 'ajax-pagination-pro' );

		$html = '<div class="ajax-pagination-load-more-wrapper">';
		$html .= '<button type="button" class="ajax-pagination-load-more" data-page="' . esc_attr( $current_page + 1 ) . '" data-total="' . esc_attr( $total_pages ) . '">';
		$html .= '<span class="ajax-pagination-load-more-text">' . esc_html( $button_text ) . '</span>';
		$html .= '<span class="ajax-pagination-load-more-loading" style="display: none;">';
		$html .= '<span class="spinner"></span>';
		$html .= esc_html( $loading_text );
		$html .= '</span>';
		$html .= '</button>';
		$html .= '</div>';

		return $html;
	}
}
