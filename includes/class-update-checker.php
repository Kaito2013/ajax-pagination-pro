<?php
/**
 * Update Checker — Kiểm tra cập nhật từ License Manager API
 *
 * @package    AJAX_Pagination_Pro
 * @subpackage Includes
 * @since      1.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class AJAX_Pagination_Pro_Update_Checker
 *
 * Hook vào hệ thống update của WordPress để kiểm tra phiên bản mới
 * thông qua License Manager API (GAS). Plugin chỉ nhận update khi
 * license đang active.
 *
 * @since 1.1.0
 */
class AJAX_Pagination_Pro_Update_Checker {

	/**
	 * Single instance.
	 *
	 * @var AJAX_Pagination_Pro_Update_Checker|null
	 */
	private static $instance = null;

	/**
	 * API Base URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://script.google.com/macros/s/AKfycbyR3JI25wZmf43svkNpS6SlBLmtOsVpOkhqNSdsdjMzAPCjiU9TR4BzaLEwH5FL6rE/exec';

	/**
	 * Software ID.
	 *
	 * @var string
	 */
	private $software_id = 'ajax-pagination-pro';

	/**
	 * Option key cho update data cache.
	 *
	 * @var string
	 */
	private $option_update_data = 'app_update_data';

	/**
	 * Get singleton instance.
	 *
	 * @return AJAX_Pagination_Pro_Update_Checker
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
		// Hook vào update system
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );

		// Admin notice khi có update
		add_action( 'admin_notices', array( $this, 'admin_update_notice' ) );
		add_action( 'admin_init', array( $this, 'dismiss_update_notice' ) );

		// WP Cron: kiểm tra update định kỳ
		add_action( 'app_update_cron_check', array( $this, 'cron_check_update' ) );
		if ( ! wp_next_scheduled( 'app_update_cron_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'app_update_cron_check' );
		}
	}

	// =========================================================================
	// WORDPRESS UPDATE HOOKS
	// =========================================================================

	/**
	 * Kiểm tra update — hook vào pre_set_site_transient_update_plugins.
	 *
	 * @param object $transient Update transient.
	 *
	 * @return object
	 */
	public function check_for_update( $transient ) {
		// Kiểm tra license active
		$license_manager = AJAX_Pagination_Pro_License_Manager::get_instance();
		if ( ! $license_manager->is_active() ) {
			return $transient;
		}

		// Kiểm tra update
		$update_info = $this->fetch_update_info();

		if ( empty( $update_info ) || ! ( $update_info['has_update'] ?? false ) ) {
			return $transient;
		}

		// So sánh version
		$current_version = AJAX_PAGINATION_PRO_VERSION;
		$latest_version  = $update_info['latest_version'] ?? '';

		if ( version_compare( $latest_version, $current_version, '<=' ) ) {
			return $transient;
		}

		// Thêm vào transient update
		$plugin_slug = AJAX_PAGINATION_PRO_BASENAME;

		$transient->response[ $plugin_slug ] = (object) array(
			'slug'        => dirname( AJAX_PAGINATION_PRO_BASENAME ),
			'plugin'      => AJAX_PAGINATION_PRO_BASENAME,
			'new_version' => $latest_version,
			'url'         => 'https://github.com/Kaito2013/ajax-pagination-pro',
			'package'     => $update_info['download_url'] ?? '',
			'tested'      => '6.6',
			'requires_php' => '8.0',
		);

		return $transient;
	}

	/**
	 * Hook vào plugins_api để cung cấp thông tin plugin.
	 *
	 * @param false|object|array $result  Default result.
	 * @param string             $action  API action.
	 * @param object             $args    Arguments.
	 *
	 * @return false|object|array
	 */
	public function plugins_api_filter( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$plugin_slug = dirname( AJAX_PAGINATION_PRO_BASENAME );
		if ( $args->slug !== $plugin_slug ) {
			return $result;
		}

		$update_info = $this->fetch_update_info();

		if ( empty( $update_info ) ) {
			return $result;
		}

		return (object) array(
			'name'           => 'AJAX Pagination Pro',
			'slug'           => $plugin_slug,
			'version'        => $update_info['latest_version'] ?? AJAX_PAGINATION_PRO_VERSION,
			'author'         => '<a href="https://github.com/Kaito2013">Kaito2013</a>',
			'author_profile' => 'https://github.com/Kaito2013',
			'requires'       => '6.0',
			'tested'         => '6.6',
			'requires_php'   => '8.0',
			'download_link'  => $update_info['download_url'] ?? '',
			'sections'       => array(
				'description'    => 'Advanced AJAX pagination for WordPress with numbered pagination and load more button.',
				'changelog'      => $this->format_changelog( $update_info['changelog'] ?? '' ),
			),
			'banners'        => array(),
		);
	}

	// =========================================================================
	// ADMIN NOTICE
	// =========================================================================

	/**
	 * Hiển thị admin notice khi có update.
	 */
	public function admin_update_notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Kiểm tra đã dismiss chưa
		$dismissed = get_transient( 'app_update_notice_dismissed' );
		if ( $dismissed ) {
			return;
		}

		$update_info = $this->fetch_update_info();
		if ( empty( $update_info ) || ! ( $update_info['has_update'] ?? false ) ) {
			return;
		}

		$current_version = AJAX_PAGINATION_PRO_VERSION;
		$latest_version  = $update_info['latest_version'] ?? '';

		if ( version_compare( $latest_version, $current_version, '<=' ) ) {
			return;
		}

		$update_url = admin_url( 'update-core.php' );
		$dismiss_url = wp_nonce_url(
			add_query_arg( 'app_dismiss_update', '1' ),
			'app_dismiss_update_nonce'
		);

		printf(
			'<div class="notice notice-info is-dismissible">
				<p>
					<strong>AJAX Pagination Pro:</strong> Phiên bản mới <strong>%s</strong> đã có sẵn (hiện tại: %s).
					<a href="%s">Cập nhật ngay</a> |
					<a href="%s">Bỏ qua</a>
				</p>
			</div>',
			esc_html( $latest_version ),
			esc_html( $current_version ),
			esc_url( $update_url ),
			esc_url( $dismiss_url )
		);
	}

	/**
	 * Xử lý dismiss update notice.
	 */
	public function dismiss_update_notice() {
		if ( ! isset( $_GET['app_dismiss_update'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'app_dismiss_update_nonce' ) ) {
			return;
		}

		set_transient( 'app_update_notice_dismissed', true, 86400 ); // 24 giờ
		wp_safe_redirect( remove_query_arg( array( 'app_dismiss_update', '_wpnonce' ) ) );
		exit;
	}

	// =========================================================================
	// CRON
	// =========================================================================

	/**
	 * Cron: kiểm tra update định kỳ.
	 */
	public function cron_check_update() {
		$this->fetch_update_info( true );
	}

	// =========================================================================
	// API
	// =========================================================================

	/**
	 * Fetch update info từ License Manager API.
	 *
	 * @param bool $force Bỏ qua cache.
	 *
	 * @return array|false
	 */
	private function fetch_update_info( $force = false ) {
		// Kiểm tra cache
		if ( ! $force ) {
			$cached = get_option( $this->option_update_data, array() );
			if ( ! empty( $cached ) && isset( $cached['checked'] ) ) {
				// Cache 12 giờ
				if ( ( time() - $cached['checked'] ) < 43200 ) {
					return $cached;
				}
			}
		}

		// Gọi API
		$response = $this->api_call( 'update.check', array(
			'software_id'    => $this->software_id,
			'current_version' => AJAX_PAGINATION_PRO_VERSION,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$update_data = array(
			'has_update'     => ( true === $response['success'] && true === ( $response['has_update'] ?? false ) ),
			'latest_version' => $response['data']['latest_version'] ?? '',
			'download_url'   => $response['data']['download_url'] ?? '',
			'changelog'      => $response['data']['changelog'] ?? '',
			'checked'        => time(),
		);

		update_option( $this->option_update_data, $update_data, false );

		return $update_data;
	}

	/**
	 * Gọi License Manager API.
	 *
	 * @param string $action Action.
	 * @param array  $params Params.
	 *
	 * @return array|WP_Error
	 */
	private function api_call( $action, $params = array() ) {
		$params['action']  = $action;
		$params['api_key'] = '59bf9dca4289daafb4199a4c0b5176b1';

		$url = add_query_arg( $params, $this->api_url );

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'app_api_error', sprintf( 'API HTTP %d', $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new WP_Error( 'app_api_decode', 'Không thể parse JSON' );
		}

		return $data;
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Format changelog text thành HTML.
	 *
	 * @param string $changelog Changelog raw text.
	 *
	 * @return string HTML changelog.
	 */
	private function format_changelog( $changelog ) {
		if ( empty( $changelog ) ) {
			return '<p>Không có thông tin changelog.</p>';
		}

		// Convert line breaks thành list items
		$lines = explode( "\n", $changelog );
		$html  = '<ul>';
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) ) {
				$html .= '<li>' . esc_html( $line ) . '</li>';
			}
		}
		$html .= '</ul>';

		return $html;
	}
}
