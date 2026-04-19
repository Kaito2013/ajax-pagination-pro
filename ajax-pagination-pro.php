<?php
/**
 * Plugin Name: AJAX Pagination Pro
 * Plugin URI:  https://github.com/Kaito2013/ajax-pagination-pro
 * Description: Advanced AJAX pagination for WordPress with numbered pagination and load more button. Works with posts and custom post types.
 * Version:     1.0.0
 * Author:      Kaito2013
 * Author URI:  https://github.com/Kaito2013
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ajax-pagination-pro
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 *
 * @package AJAX_Pagination_Pro
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'AJAX_PAGINATION_PRO_VERSION', '1.0.0' );
define( 'AJAX_PAGINATION_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'AJAX_PAGINATION_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'AJAX_PAGINATION_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main AJAX Pagination Pro Class
 *
 * @since 1.0.0
 */
final class AJAX_Pagination_Pro {

	/**
	 * Single instance of the class.
	 *
	 * @var AJAX_Pagination_Pro
	 */
	private static $instance = null;

	/**
	 * Get instance of the class.
	 *
	 * @return AJAX_Pagination_Pro
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	private function includes() {
		// Core classes
		require_once AJAX_PAGINATION_PRO_DIR . 'includes/class-pagination-core.php';
		require_once AJAX_PAGINATION_PRO_DIR . 'includes/class-ajax-handler.php';
		require_once AJAX_PAGINATION_PRO_DIR . 'includes/class-shortcode.php';
		require_once AJAX_PAGINATION_PRO_DIR . 'includes/class-template-manager.php';
		require_once AJAX_PAGINATION_PRO_DIR . 'includes/class-search.php';
		require_once AJAX_PAGINATION_PRO_DIR . 'includes/class-lazy-loading.php';
		require_once AJAX_PAGINATION_PRO_DIR . 'includes/class-cache.php';

		// Admin classes
		if ( is_admin() ) {
			require_once AJAX_PAGINATION_PRO_DIR . 'admin/class-admin-settings.php';
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Activation/Deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Initialize plugin
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public function activate() {
		// Set default options
		$this->set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		// Clean up
		flush_rewrite_rules();
	}

	/**
	 * Set default options.
	 *
	 * @return void
	 */
	private function set_default_options() {
		$defaults = array(
			'ajax_pagination_per_page'        => 10,
			'ajax_pagination_style'           => 'numbered',
			'ajax_pagination_show_loading'    => '1',
			'ajax_pagination_loading_color'   => '#2271b1',
			'ajax_pagination_animation_speed' => 300,
			'ajax_pagination_update_url'      => '1',
			'ajax_pagination_infinite_scroll' => '0',
			'ajax_pagination_scroll_threshold' => 200,
			'ajax_pagination_cache_enabled'   => '0',
			'ajax_pagination_cache_duration'  => 300,
			'ajax_pagination_debug'           => '0',
		);

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				update_option( $key, $value );
			}
		}
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init() {
		// Initialize classes
		AJAX_Pagination_Pro_Core::get_instance();
		AJAX_Pagination_Pro_AJAX::get_instance();
		AJAX_Pagination_Pro_Shortcode::get_instance();
		AJAX_Pagination_Pro_Template_Manager::get_instance();
		AJAX_Pagination_Pro_Search::get_instance();
		AJAX_Pagination_Pro_Lazy_Loading::get_instance();
		AJAX_Pagination_Pro_Cache::get_instance();

		if ( is_admin() ) {
			AJAX_Pagination_Pro_Admin_Settings::get_instance();
		}
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ajax-pagination-pro',
			false,
			dirname( AJAX_PAGINATION_PRO_BASENAME ) . '/languages/'
		);
	}
}

// Initialize the plugin
AJAX_Pagination_Pro::get_instance();
