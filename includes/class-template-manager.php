<?php
/**
 * Template Manager Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template Manager Class
 */
class AJAX_Pagination_Pro_Template_Manager {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Template_Manager
	 */
	private static $instance = null;

	/**
	 * Available templates.
	 *
	 * @var array
	 */
	private $templates = array();

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Template_Manager
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
		$this->register_default_templates();
		add_filter( 'ajax_pagination_post_html', array( $this, 'load_template' ), 10, 3 );
	}

	/**
	 * Register default templates.
	 *
	 * @return void
	 */
	private function register_default_templates() {
		$this->templates = array(
			'card'       => array(
				'name'        => __( 'Card', 'ajax-pagination-pro' ),
				'description' => __( 'Standard card layout with image, title, and excerpt', 'ajax-pagination-pro' ),
				'file'        => 'templates/card.php',
			),
			'list'       => array(
				'name'        => __( 'List', 'ajax-pagination-pro' ),
				'description' => __( 'Horizontal list layout', 'ajax-pagination-pro' ),
				'file'        => 'templates/list.php',
			),
			'minimal'    => array(
				'name'        => __( 'Minimal', 'ajax-pagination-pro' ),
				'description' => __( 'Clean minimal design', 'ajax-pagination-pro' ),
				'file'        => 'templates/minimal.php',
			),
			'magazine'   => array(
				'name'        => __( 'Magazine', 'ajax-pagination-pro' ),
				'description' => __( 'Magazine style with featured post', 'ajax-pagination-pro' ),
				'file'        => 'templates/magazine.php',
			),
			'portfolio'  => array(
				'name'        => __( 'Portfolio', 'ajax-pagination-pro' ),
				'description' => __( 'Portfolio grid with overlay', 'ajax-pagination-pro' ),
				'file'        => 'templates/portfolio.php',
			),
			'testimonial' => array(
				'name'        => __( 'Testimonial', 'ajax-pagination-pro' ),
				'description' => __( 'Testimonial card style', 'ajax-pagination-pro' ),
				'file'        => 'templates/testimonial.php',
			),
		);
	}

	/**
	 * Get all templates.
	 *
	 * @return array
	 */
	public function get_templates() {
		return $this->templates;
	}

	/**
	 * Get template by slug.
	 *
	 * @param string $slug Template slug.
	 * @return array|null
	 */
	public function get_template( $slug ) {
		return isset( $this->templates[ $slug ] ) ? $this->templates[ $slug ] : null;
	}

	/**
	 * Register custom template.
	 *
	 * @param string $slug        Template slug.
	 * @param string $name        Template name.
	 * @param string $description Template description.
	 * @param string $file        Template file path.
	 * @return void
	 */
	public function register_template( $slug, $name, $description, $file ) {
		$this->templates[ $slug ] = array(
			'name'        => $name,
			'description' => $description,
			'file'        => $file,
		);
	}

	/**
	 * Load template.
	 *
	 * @param string $html  Default HTML.
	 * @param object $post  Post object.
	 * @param array  $args  Template arguments.
	 * @return string
	 */
	public function load_template( $html, $post, $args ) {
		$template = isset( $args['template'] ) ? $args['template'] : 'card';

		// Check if custom template exists in theme
		$theme_template = $this->locate_template( $template );
		if ( $theme_template ) {
			ob_start();
			include $theme_template;
			return ob_get_clean();
		}

		// Check if template is registered
		$registered = $this->get_template( $template );
		if ( $registered ) {
			$template_file = AJAX_PAGINATION_PRO_DIR . $registered['file'];
			if ( file_exists( $template_file ) ) {
				ob_start();
				include $template_file;
				return ob_get_clean();
			}
		}

		// Fallback to default card template
		return $this->render_default_card( $post, $args );
	}

	/**
	 * Locate template in theme.
	 *
	 * @param string $template Template slug.
	 * @return string|false
	 */
	private function locate_template( $template ) {
		// Check in theme folder
		$theme_template = locate_template( array(
			'ajax-pagination/' . $template . '.php',
			'ajax-pagination-pro/' . $template . '.php',
		) );

		if ( $theme_template ) {
			return $theme_template;
		}

		return false;
	}

	/**
	 * Render default card template.
	 *
	 * @param object $post Post object.
	 * @param array  $args Template arguments.
	 * @return string
	 */
	private function render_default_card( $post, $args ) {
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

		ob_start();
		?>
		<article class="ajax-pagination-card ajax-pagination-columns-<?php echo esc_attr( $args['columns'] ); ?>">

			<?php if ( $args['show_image'] && has_post_thumbnail( $post->ID ) ) : ?>
				<div class="ajax-pagination-card-image">
					<a href="<?php the_permalink( $post->ID ); ?>">
						<?php echo get_the_post_thumbnail( $post->ID, $args['image_size'], array( 'alt' => esc_attr( get_the_title( $post->ID ) ) ) ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="ajax-pagination-card-content">

				<h3 class="ajax-pagination-card-title">
					<a href="<?php the_permalink( $post->ID ); ?>">
						<?php echo esc_html( get_the_title( $post->ID ) ); ?>
					</a>
				</h3>

				<?php if ( $args['show_date'] || $args['show_author'] ) : ?>
					<div class="ajax-pagination-card-meta">
						<?php if ( $args['show_date'] ) : ?>
							<span class="ajax-pagination-card-date">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php echo esc_html( get_the_date( '', $post->ID ) ); ?>
							</span>
						<?php endif; ?>

						<?php if ( $args['show_author'] ) : ?>
							<span class="ajax-pagination-card-author">
								<span class="dashicons dashicons-admin-users"></span>
								<?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $args['show_excerpt'] ) : ?>
					<div class="ajax-pagination-card-excerpt">
						<p><?php echo esc_html( wp_trim_words( strip_shortcodes( $post->post_content ), $args['excerpt_length'] ) ); ?></p>
					</div>
				<?php endif; ?>

				<a href="<?php the_permalink( $post->ID ); ?>" class="ajax-pagination-card-link">
					<?php esc_html_e( 'Read More', 'ajax-pagination-pro' ); ?>
					<span class="dashicons dashicons-arrow-right-alt"></span>
				</a>

			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
