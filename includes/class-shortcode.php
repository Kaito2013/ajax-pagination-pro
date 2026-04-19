<?php
/**
 * Shortcode Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode Class
 */
class AJAX_Pagination_Pro_Shortcode {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Shortcode
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Shortcode
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
		add_shortcode( 'ajax_pagination', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'post_type'      => 'post',
			'style'          => get_option( 'ajax_pagination_style', 'numbered' ),
			'per_page'       => absint( get_option( 'ajax_pagination_per_page', 10 ) ),
			'category'       => '',
			'taxonomy'       => '',
			'term'           => '',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'columns'        => 3,
			'image_size'     => 'medium',
			'show_image'     => 'true',
			'show_excerpt'   => 'true',
			'show_date'      => 'true',
			'show_author'    => 'false',
			'excerpt_length' => 55,
			'css_class'      => '',
			'loading_text'   => __( 'Loading...', 'ajax-pagination-pro' ),
			'no_posts_text'  => __( 'No posts found', 'ajax-pagination-pro' ),
			'button_text'    => __( 'Load More', 'ajax-pagination-pro' ),
		), $atts, 'ajax_pagination' );

		// Convert string booleans to actual booleans
		$atts['show_image'] = filter_var( $atts['show_image'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_excerpt'] = filter_var( $atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_date'] = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
		$atts['show_author'] = filter_var( $atts['show_author'], FILTER_VALIDATE_BOOLEAN );

		// Validate post type
		if ( ! post_type_exists( $atts['post_type'] ) ) {
			return '<div class="ajax-pagination-error">' . esc_html__( 'Invalid post type', 'ajax-pagination-pro' ) . '</div>';
		}

		// Get posts
		$core = AJAX_Pagination_Pro_Core::get_instance();
		$result = $core->get_posts( array(
			'post_type'      => $atts['post_type'],
			'posts_per_page' => $atts['per_page'],
			'paged'          => 1,
			'category'       => $atts['category'],
			'taxonomy'       => $atts['taxonomy'],
			'term'           => $atts['term'],
			'orderby'        => $atts['orderby'],
			'order'          => $atts['order'],
		) );

		// Start output buffering
		ob_start();

		// Container
		$container_class = 'ajax-pagination-container';
		if ( ! empty( $atts['css_class'] ) ) {
			$container_class .= ' ' . esc_attr( $atts['css_class'] );
		}
		?>
		<div class="<?php echo esc_attr( $container_class ); ?>"
			 data-post-type="<?php echo esc_attr( $atts['post_type'] ); ?>"
			 data-style="<?php echo esc_attr( $atts['style'] ); ?>"
			 data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>"
			 data-category="<?php echo esc_attr( $atts['category'] ); ?>"
			 data-taxonomy="<?php echo esc_attr( $atts['taxonomy'] ); ?>"
			 data-term="<?php echo esc_attr( $atts['term'] ); ?>"
			 data-orderby="<?php echo esc_attr( $atts['orderby'] ); ?>"
			 data-order="<?php echo esc_attr( $atts['order'] ); ?>"
			 data-columns="<?php echo esc_attr( $atts['columns'] ); ?>"
			 data-image-size="<?php echo esc_attr( $atts['image_size'] ); ?>"
			 data-show-image="<?php echo $atts['show_image'] ? 'true' : 'false'; ?>"
			 data-show-excerpt="<?php echo $atts['show_excerpt'] ? 'true' : 'false'; ?>"
			 data-show-date="<?php echo $atts['show_date'] ? 'true' : 'false'; ?>"
			 data-show-author="<?php echo $atts['show_author'] ? 'true' : 'false'; ?>"
			 data-excerpt-length="<?php echo esc_attr( $atts['excerpt_length'] ); ?>"
			 data-current-page="1"
			 data-total-pages="<?php echo esc_attr( $result['max_num_pages'] ); ?>"
			 data-total-posts="<?php echo esc_attr( $result['found_posts'] ); ?>">

			<?php if ( empty( $result['posts'] ) ) : ?>
				<div class="ajax-pagination-empty">
					<span class="dashicons dashicons-format-aside"></span>
					<p><?php echo esc_html( $atts['no_posts_text'] ); ?></p>
				</div>
			<?php else : ?>
				<div class="ajax-pagination-grid columns-<?php echo esc_attr( $atts['columns'] ); ?>">
					<?php foreach ( $result['posts'] as $post ) : ?>
						<?php echo $core->render_post_card( $post, array(
							'columns'        => $atts['columns'],
							'image_size'     => $atts['image_size'],
							'show_image'     => $atts['show_image'],
							'show_excerpt'   => $atts['show_excerpt'],
							'show_date'      => $atts['show_date'],
							'show_author'    => $atts['show_author'],
							'excerpt_length' => $atts['excerpt_length'],
						) ); ?>
					<?php endforeach; ?>
				</div>

				<?php
				// Render pagination
				if ( 'numbered' === $atts['style'] ) {
					echo $core->render_numbered_pagination( 1, $result['max_num_pages'] );
				} else {
					echo $core->render_load_more_button( 1, $result['max_num_pages'], array(
						'loading_text' => $atts['loading_text'],
						'button_text'  => $atts['button_text'],
					) );
				}
				?>
			<?php endif; ?>

			<!-- Loading overlay -->
			<div class="ajax-pagination-loading" style="display: none;">
				<div class="ajax-pagination-spinner"></div>
				<p><?php echo esc_html( $atts['loading_text'] ); ?></p>
			</div>

		</div><!-- .ajax-pagination-container -->
		<?php

		return ob_get_clean();
	}
}
