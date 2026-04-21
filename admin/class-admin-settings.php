<?php
/**
 * Admin Settings Class
 *
 * @package AJAX_Pagination_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Settings Class
 */
class AJAX_Pagination_Pro_Admin_Settings {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro_Admin_Settings
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro_Admin_Settings
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'AJAX Pagination', 'ajax-pagination-pro' ),
			__( 'AJAX Pagination', 'ajax-pagination-pro' ),
			'manage_options',
			'ajax-pagination-settings',
			array( $this, 'render_settings_page' )
		);

		// Sub-menu License
		add_submenu_page(
			'options-general.php',
			__( 'AJAX Pagination License', 'ajax-pagination-pro' ),
			__( 'AJAX Pagination License', 'ajax-pagination-pro' ),
			'manage_options',
			'ajax-pagination-license',
			array( $this, 'render_license_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		// General Settings
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_per_page' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_style' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_loading_text' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_no_posts_text' );

		// Display Settings
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_show_loading' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_loading_color' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_animation_speed' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_update_url' );

		// Advanced Settings
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_infinite_scroll' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_scroll_threshold' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_cache_enabled' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_cache_duration' );
		register_setting( 'ajax_pagination_settings', 'ajax_pagination_debug' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'settings_page_ajax-pagination-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ajax-pagination-admin',
			AJAX_PAGINATION_PRO_URL . 'assets/css/admin.css',
			array(),
			AJAX_PAGINATION_PRO_VERSION
		);

		wp_enqueue_script(
			'ajax-pagination-admin',
			AJAX_PAGINATION_PRO_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			AJAX_PAGINATION_PRO_VERSION,
			true
		);

		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		?>
		<div class="wrap ajax-pagination-settings">
			<h1><?php esc_html_e( 'AJAX Pagination Settings', 'ajax-pagination-pro' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'ajax_pagination_settings' ); ?>

				<!-- General Settings -->
				<div class="ajax-pagination-settings-section">
					<h2>
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'General Settings', 'ajax-pagination-pro' ); ?>
					</h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="ajax_pagination_per_page"><?php esc_html_e( 'Posts Per Page', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<input type="number" id="ajax_pagination_per_page" name="ajax_pagination_per_page" value="<?php echo esc_attr( get_option( 'ajax_pagination_per_page', 10 ) ); ?>" class="small-text" min="1" max="100">
								<p class="description"><?php esc_html_e( 'Number of posts to display per page.', 'ajax-pagination-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_style"><?php esc_html_e( 'Pagination Style', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<select id="ajax_pagination_style" name="ajax_pagination_style">
									<option value="numbered" <?php selected( get_option( 'ajax_pagination_style', 'numbered' ), 'numbered' ); ?>><?php esc_html_e( 'Numbered Pagination', 'ajax-pagination-pro' ); ?></option>
									<option value="load_more" <?php selected( get_option( 'ajax_pagination_style', 'numbered' ), 'load_more' ); ?>><?php esc_html_e( 'Load More Button', 'ajax-pagination-pro' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Choose how pagination is displayed.', 'ajax-pagination-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_loading_text"><?php esc_html_e( 'Loading Text', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<input type="text" id="ajax_pagination_loading_text" name="ajax_pagination_loading_text" value="<?php echo esc_attr( get_option( 'ajax_pagination_loading_text', __( 'Loading...', 'ajax-pagination-pro' ) ) ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_no_posts_text"><?php esc_html_e( 'No Posts Text', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<input type="text" id="ajax_pagination_no_posts_text" name="ajax_pagination_no_posts_text" value="<?php echo esc_attr( get_option( 'ajax_pagination_no_posts_text', __( 'No posts found', 'ajax-pagination-pro' ) ) ); ?>" class="regular-text">
							</td>
						</tr>
					</table>
				</div>

				<!-- Display Settings -->
				<div class="ajax-pagination-settings-section">
					<h2>
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Display Settings', 'ajax-pagination-pro' ); ?>
					</h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="ajax_pagination_show_loading"><?php esc_html_e( 'Show Loading Spinner', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="ajax_pagination_show_loading" name="ajax_pagination_show_loading" value="1" <?php checked( get_option( 'ajax_pagination_show_loading', '1' ), '1' ); ?>>
									<?php esc_html_e( 'Display loading spinner during AJAX requests', 'ajax-pagination-pro' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_loading_color"><?php esc_html_e( 'Loading Color', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<input type="text" id="ajax_pagination_loading_color" name="ajax_pagination_loading_color" value="<?php echo esc_attr( get_option( 'ajax_pagination_loading_color', '#2271b1' ) ); ?>" class="ajax-pagination-color-picker">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_animation_speed"><?php esc_html_e( 'Animation Speed (ms)', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<input type="number" id="ajax_pagination_animation_speed" name="ajax_pagination_animation_speed" value="<?php echo esc_attr( get_option( 'ajax_pagination_animation_speed', 300 ) ); ?>" class="small-text" min="0" max="2000" step="100">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_update_url"><?php esc_html_e( 'Update URL on Page Change', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="ajax_pagination_update_url" name="ajax_pagination_update_url" value="1" <?php checked( get_option( 'ajax_pagination_update_url', '1' ), '1' ); ?>>
									<?php esc_html_e( 'Update browser URL when page changes (SEO friendly)', 'ajax-pagination-pro' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<!-- Advanced Settings -->
				<div class="ajax-pagination-settings-section">
					<h2>
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Advanced Settings', 'ajax-pagination-pro' ); ?>
					</h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="ajax_pagination_infinite_scroll"><?php esc_html_e( 'Infinite Scroll', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="ajax_pagination_infinite_scroll" name="ajax_pagination_infinite_scroll" value="1" <?php checked( get_option( 'ajax_pagination_infinite_scroll', '0' ), '1' ); ?>>
									<?php esc_html_e( 'Automatically load more posts when scrolling to bottom', 'ajax-pagination-pro' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_scroll_threshold"><?php esc_html_e( 'Scroll Threshold (px)', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<input type="number" id="ajax_pagination_scroll_threshold" name="ajax_pagination_scroll_threshold" value="<?php echo esc_attr( get_option( 'ajax_pagination_scroll_threshold', 200 ) ); ?>" class="small-text" min="50" max="1000" step="50">
								<p class="description"><?php esc_html_e( 'Distance from bottom to trigger infinite scroll.', 'ajax-pagination-pro' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_cache_enabled"><?php esc_html_e( 'Enable Caching', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="ajax_pagination_cache_enabled" name="ajax_pagination_cache_enabled" value="1" <?php checked( get_option( 'ajax_pagination_cache_enabled', '0' ), '1' ); ?>>
									<?php esc_html_e( 'Cache AJAX responses for better performance', 'ajax-pagination-pro' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_cache_duration"><?php esc_html_e( 'Cache Duration (seconds)', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<input type="number" id="ajax_pagination_cache_duration" name="ajax_pagination_cache_duration" value="<?php echo esc_attr( get_option( 'ajax_pagination_cache_duration', 300 ) ); ?>" class="small-text" min="60" max="3600" step="60">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ajax_pagination_debug"><?php esc_html_e( 'Debug Mode', 'ajax-pagination-pro' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" id="ajax_pagination_debug" name="ajax_pagination_debug" value="1" <?php checked( get_option( 'ajax_pagination_debug', '0' ), '1' ); ?>>
									<?php esc_html_e( 'Enable debug mode for troubleshooting', 'ajax-pagination-pro' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<!-- Shortcode Reference -->
				<div class="ajax-pagination-settings-section">
					<h2>
						<span class="dashicons dashicons-shortcode"></span>
						<?php esc_html_e( 'Shortcode Reference', 'ajax-pagination-pro' ); ?>
					</h2>

					<div class="ajax-pagination-shortcode-reference">
						<h3><?php esc_html_e( 'Basic Usage', 'ajax-pagination-pro' ); ?></h3>
						<code>[ajax_pagination]</code>

						<h3><?php esc_html_e( 'Available Attributes', 'ajax-pagination-pro' ); ?></h3>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Attribute', 'ajax-pagination-pro' ); ?></th>
									<th><?php esc_html_e( 'Default', 'ajax-pagination-pro' ); ?></th>
									<th><?php esc_html_e( 'Description', 'ajax-pagination-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><code>post_type</code></td>
									<td><code>post</code></td>
									<td><?php esc_html_e( 'Post type to display (post, page, custom post type)', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>style</code></td>
									<td><code>numbered</code></td>
									<td><?php esc_html_e( 'Pagination style: numbered or load_more', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>per_page</code></td>
									<td><code>10</code></td>
									<td><?php esc_html_e( 'Number of posts per page', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>category</code></td>
									<td><code></code></td>
									<td><?php esc_html_e( 'Filter by category slug', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>taxonomy</code></td>
									<td><code></code></td>
									<td><?php esc_html_e( 'Filter by taxonomy', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>term</code></td>
									<td><code></code></td>
									<td><?php esc_html_e( 'Filter by taxonomy term slug', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>orderby</code></td>
									<td><code>date</code></td>
									<td><?php esc_html_e( 'Order by: date, title, rand, etc.', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>order</code></td>
									<td><code>DESC</code></td>
									<td><?php esc_html_e( 'Order direction: ASC or DESC', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>columns</code></td>
									<td><code>3</code></td>
									<td><?php esc_html_e( 'Number of columns (1-4)', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>image_size</code></td>
									<td><code>medium</code></td>
									<td><?php esc_html_e( 'Image size: thumbnail, medium, large', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>show_image</code></td>
									<td><code>true</code></td>
									<td><?php esc_html_e( 'Show/hide featured image', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>show_excerpt</code></td>
									<td><code>true</code></td>
									<td><?php esc_html_e( 'Show/hide excerpt', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>show_date</code></td>
									<td><code>true</code></td>
									<td><?php esc_html_e( 'Show/hide date', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>show_author</code></td>
									<td><code>false</code></td>
									<td><?php esc_html_e( 'Show/hide author', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>excerpt_length</code></td>
									<td><code>55</code></td>
									<td><?php esc_html_e( 'Number of words in excerpt', 'ajax-pagination-pro' ); ?></td>
								</tr>
								<tr>
									<td><code>css_class</code></td>
									<td><code></code></td>
									<td><?php esc_html_e( 'Custom CSS class', 'ajax-pagination-pro' ); ?></td>
								</tr>
							</tbody>
						</table>

						<h3><?php esc_html_e( 'Examples', 'ajax-pagination-pro' ); ?></h3>
						<code>[ajax_pagination post_type="product" style="load_more" per_page="12" columns="4"]</code>
						<br><br>
						<code>[ajax_pagination post_type="post" category="news" style="numbered" per_page="5"]</code>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render License page — delegates to License Manager class.
	 *
	 * @return void
	 */
	public function render_license_page() {
		$license_manager = AJAX_Pagination_Pro_License_Manager::get_instance();
		$license_manager->render_license_settings();
	}
}
