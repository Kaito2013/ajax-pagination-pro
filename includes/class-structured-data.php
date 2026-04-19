<?php
/**
 * Structured Data Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured Data Class
 */
class AJAX_Pagination_Pro_Structured_Data {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Structured_Data
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Structured_Data
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
		add_action( 'wp_footer', array( $this, 'output_structured_data' ) );
		add_filter( 'ajax_pagination_after_response', array( $this, 'add_to_response' ), 10, 2 );
	}

	/**
	 * Generate structured data for posts.
	 *
	 * @param array $posts Posts array.
	 * @param int   $page  Current page.
	 * @return array
	 */
	public function generate( $posts, $page = 1 ) {
		if ( ! get_option( 'ajax_pagination_structured_data', '1' ) ) {
			return array();
		}

		$structured_data = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => get_the_title() . ' - Page ' . $page,
			'description'     => get_bloginfo( 'description' ),
			'numberOfItems'   => count( $posts ),
			'itemListElement' => array(),
		);

		foreach ( $posts as $index => $post ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => ( $page - 1 ) * get_option( 'ajax_pagination_per_page', 10 ) + $index + 1,
				'item'     => $this->generate_item( $post ),
			);

			$structured_data['itemListElement'][] = $item;
		}

		return $structured_data;
	}

	/**
	 * Generate structured data for single item.
	 *
	 * @param object $post Post object.
	 * @return array
	 */
	private function generate_item( $post ) {
		$post_type = get_post_type_object( $post->post_type );

		$item = array(
			'@type'         => $this->get_schema_type( $post->post_type ),
			'name'          => get_the_title( $post->ID ),
			'description'   => wp_trim_words( strip_shortcodes( $post->post_content ), 20 ),
			'url'           => get_permalink( $post->ID ),
			'datePublished' => get_the_date( 'c', $post->ID ),
			'dateModified'  => get_the_modified_date( 'c', $post->ID ),
		);

		// Add author
		$author = get_the_author_meta( 'display_name', $post->post_author );
		if ( $author ) {
			$item['author'] = array(
				'@type' => 'Person',
				'name'  => $author,
			);
		}

		// Add image
		if ( has_post_thumbnail( $post->ID ) ) {
			$image_url = get_the_post_thumbnail_url( $post->ID, 'large' );
			if ( $image_url ) {
				$item['image'] = $image_url;
			}
		}

		// Add publisher
		$item['publisher'] = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'logo'  => array(
				'@type' => 'ImageObject',
				'url'   => get_site_icon_url(),
			),
		);

		return $item;
	}

	/**
	 * Get schema type for post type.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private function get_schema_type( $post_type ) {
		$types = array(
			'post'      => 'Article',
			'page'      => 'WebPage',
			'product'   => 'Product',
			'event'     => 'Event',
			'recipe'    => 'Recipe',
			'job'       => 'JobPosting',
			'course'    => 'Course',
			'book'      => 'Book',
			'movie'     => 'Movie',
			'restaurant' => 'Restaurant',
		);

		return isset( $types[ $post_type ] ) ? $types[ $post_type ] : 'Thing';
	}

	/**
	 * Output structured data in footer.
	 *
	 * @return void
	 */
	public function output_structured_data() {
		if ( ! get_option( 'ajax_pagination_structured_data', '1' ) ) {
			return;
		}

		// Only output on pages with pagination shortcode
		global $post;

		if ( ! $post || ! has_shortcode( $post->post_content, 'ajax_pagination' ) ) {
			return;
		}

		// Get posts for current page
		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
		$query = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => get_option( 'ajax_pagination_per_page', 10 ),
			'paged'          => $paged,
		) );

		if ( ! $query->have_posts() ) {
			return;
		}

		$structured_data = $this->generate( $query->posts, $paged );

		if ( ! empty( $structured_data ) ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $structured_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
		}
	}

	/**
	 * Add structured data to AJAX response.
	 *
	 * @param array $response Response data.
	 * @param array $data     Response data.
	 * @return array
	 */
	public function add_to_response( $response, $data ) {
		if ( ! get_option( 'ajax_pagination_structured_data', '1' ) ) {
			return $response;
		}

		if ( isset( $data['posts'] ) && ! empty( $data['posts'] ) ) {
			$response['structured_data'] = $this->generate( $data['posts'], $data['current_page'] ?? 1 );
		}

		return $response;
	}
}
