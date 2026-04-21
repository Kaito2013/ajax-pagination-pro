<?php
/**
 * License Manager — Giao tiếp với License Manager API (Google Apps Script)
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
 * Class AJAX_Pagination_Pro_License_Manager
 *
 * Xử lý toàn bộ logic license: validate, activate, deactivate, check update.
 * Dữ liệu license được lưu trong wp_options.
 *
 * @since 1.1.0
 */
class AJAX_Pagination_Pro_License_Manager {

	/**
	 * Single instance.
	 *
	 * @var AJAX_Pagination_Pro_License_Manager|null
	 */
	private static $instance = null;

	/**
	 * API Base URL — License Manager GAS endpoint.
	 *
	 * @var string
	 */
	private $api_url = 'https://script.google.com/macros/s/AKfycbyR3JI25wZmf43svkNpS6SlBLmtOsVpOkhqNSdsdjMzAPCjiU9TR4BzaLEwH5FL6rE/exec';

	/**
	 * Option keys.
	 *
	 * @var string
	 */
	private $option_license_key   = 'app_license_key';
	private $option_license_data  = 'app_license_data';
	private $option_last_check    = 'app_license_last_check';
	private $option_update_data   = 'app_update_data';

	/**
	 * Cache duration — 12 giờ (giây).
	 *
	 * @var int
	 */
	private $cache_duration = 43200;

	/**
	 * Software ID trên License Manager.
	 *
	 * @var string
	 */
	private $software_id = 'ajax-pagination-pro';

	/**
	 * Get singleton instance.
	 *
	 * @return AJAX_Pagination_Pro_License_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — đăng ký hooks.
	 */
	private function __construct() {
		// Admin: xử lý form license
		add_action( 'admin_init', array( $this, 'handle_license_actions' ) );

		// WP Cron: kiểm tra license định kỳ
		add_action( 'app_license_cron_check', array( $this, 'cron_validate_license' ) );

		// Đăng ký cron nếu chưa có
		if ( ! wp_next_scheduled( 'app_license_cron_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'app_license_cron_check' );
		}
	}

	// =========================================================================
	// API CALLS
	// =========================================================================

	/**
	 * Gọi License Manager API.
	 *
	 * @param string $action  Action name (vd: license.validate).
	 * @param array  $params  Tham số bổ sung.
	 * @param string $method  HTTP method (GET|POST).
	 *
	 * @return array|WP_Error  Response data hoặc WP_Error nếu thất bại.
	 */
	private function api_call( $action, $params = array(), $method = 'GET' ) {
		$params['action']  = $action;
		$params['api_key'] = '59bf9dca4289daafb4199a4c0b5176b1';

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $params );

			$response = wp_remote_post( $this->api_url, $args );
		} else {
			$url = add_query_arg( $params, $this->api_url );

			$response = wp_remote_get( $url, $args );
		}

		// Kiểm tra lỗi HTTP
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'app_api_error',
				sprintf( 'API trả về HTTP %d', $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new WP_Error(
				'app_api_decode_error',
				'Không thể parse JSON response từ API'
			);
		}

		return $data;
	}

	// =========================================================================
	// LICENSE VALIDATION
	// =========================================================================

	/**
	 * Validate license hiện tại.
	 *
	 * @param bool $force Bỏ qua cache, gọi API trực tiếp.
	 *
	 * @return array  Kết quả validation với các key: valid, data, error.
	 */
	public function validate( $force = false ) {
		$license_key = get_option( $this->option_license_key, '' );

		if ( empty( $license_key ) ) {
			return array(
				'valid' => false,
				'error' => 'Chưa nhập License Key',
			);
		}

		// Kiểm tra cache
		if ( ! $force ) {
			$last_check = get_option( $this->option_last_check, 0 );
			$cached     = get_option( $this->option_license_data, array() );

			if ( ( time() - $last_check ) < $this->cache_duration && ! empty( $cached ) ) {
				return array(
					'valid' => ( 'active' === ( $cached['status'] ?? '' ) ),
					'data'  => $cached,
				);
			}
		}

		// Gọi API
		$response = $this->api_call( 'license.validate', array(
			'license_key' => $license_key,
			'domain'      => wp_parse_url( home_url(), PHP_URL_HOST ),
			'ip_address'  => $this->get_client_ip(),
		) );

		if ( is_wp_error( $response ) ) {
			// Nếu API lỗi, dùng cache cũ
			$cached = get_option( $this->option_license_data, array() );
			if ( ! empty( $cached ) ) {
				return array(
					'valid'    => ( 'active' === ( $cached['status'] ?? '' ) ),
					'data'     => $cached,
					'cached'   => true,
					'api_error' => $response->get_error_message(),
				);
			}

			return array(
				'valid' => false,
				'error' => 'Không thể kết nối License Server: ' . $response->get_error_message(),
			);
		}

		// Lưu cache
		update_option( $this->option_last_check, time(), false );

		if ( ! empty( $response['data'] ) ) {
			update_option( $this->option_license_data, $response['data'], false );
		}

		return array(
			'valid' => ( true === $response['success'] && true === ( $response['valid'] ?? false ) ),
			'data'  => $response['data'] ?? array(),
			'error' => $response['error'] ?? '',
		);
	}

	/**
	 * Activate license cho domain hiện tại.
	 *
	 * @param string $license_key License key.
	 *
	 * @return array  Kết quả activate.
	 */
	public function activate( $license_key ) {
		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'error'   => 'License Key không được để trống',
			);
		}

		$response = $this->api_call( 'license.activate', array(
			'license_key' => $license_key,
			'domain'      => wp_parse_url( home_url(), PHP_URL_HOST ),
			'ip_address'  => $this->get_client_ip(),
		), 'POST' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Lỗi kết nối: ' . $response->get_error_message(),
			);
		}

		if ( true === $response['success'] ) {
			// Lưu license key và data
			update_option( $this->option_license_key, $license_key, false );
			update_option( $this->option_license_data, $response['data'], false );
			update_option( $this->option_last_check, time(), false );

			// Xóa update cache để fetch lại
			delete_option( $this->option_update_data );
		}

		return $response;
	}

	/**
	 * Deactivate license trên domain hiện tại.
	 *
	 * @return array  Kết quả deactivate.
	 */
	public function deactivate() {
		$license_key = get_option( $this->option_license_key, '' );

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'error'   => 'Không có License Key để deactivate',
			);
		}

		// GAS API không có endpoint deactivate riêng,
		// ta dùng suspend với action=unsuspend ngược lại = suspend
		$response = $this->api_call( 'license.suspend', array(
			'license_key' => $license_key,
			'action'      => 'deactivate',
		), 'POST' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'Lỗi kết nối: ' . $response->get_error_message(),
			);
		}

		// Xóa local data bất kể API trả gì
		delete_option( $this->option_license_key );
		delete_option( $this->option_license_data );
		delete_option( $this->option_last_check );
		delete_option( $this->option_update_data );

		return array(
			'success' => true,
			'message' => 'Đã deactivate license thành công',
		);
	}

	// =========================================================================
	// UPDATE CHECK
	// =========================================================================

	/**
	 * Kiểm tra update từ License Manager API.
	 *
	 * @param bool $force Bỏ qua cache.
	 *
	 * @return array  Thông tin update hoặc mảng rỗng nếu không có update.
	 */
	public function check_update( $force = false ) {
		if ( ! $force ) {
			$cached = get_option( $this->option_update_data, array() );
			if ( ! empty( $cached ) && isset( $cached['checked'] ) ) {
				// Cache 24 giờ cho update check
				if ( ( time() - $cached['checked'] ) < 86400 ) {
					return $cached;
				}
			}
		}

		$response = $this->api_call( 'update.check', array(
			'software_id'    => $this->software_id,
			'current_version' => AJAX_PAGINATION_PRO_VERSION,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'has_update' => false,
				'error'      => $response->get_error_message(),
			);
		}

		$update_data = array(
			'has_update'      => ( true === $response['success'] && true === ( $response['has_update'] ?? false ) ),
			'latest_version'  => $response['data']['latest_version'] ?? '',
			'download_url'    => $response['data']['download_url'] ?? '',
			'changelog'       => $response['data']['changelog'] ?? '',
			'checked'         => time(),
		);

		update_option( $this->option_update_data, $update_data, false );

		return $update_data;
	}

	// =========================================================================
	// ADMIN FORM HANDLER
	// =========================================================================

	/**
	 * Xử lý form activate/deactivate license trên admin.
	 */
	public function handle_license_actions() {
		if ( ! isset( $_POST['app_license_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['app_license_nonce'], 'app_license_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['app_license_do'] ?? '' ) );

		if ( 'activate' === $action ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST['app_license_key'] ?? '' ) );
			$result      = $this->activate( $license_key );

			if ( true === $result['success'] ) {
				add_settings_error(
					'app_license',
					'license_activated',
					'License đã kích hoạt thành công!',
					'success'
				);
			} else {
				add_settings_error(
					'app_license',
					'license_activate_error',
					'Lỗi: ' . ( $result['error'] ?? 'Không xác định' ),
					'error'
				);
			}
		} elseif ( 'deactivate' === $action ) {
			$result = $this->deactivate();

			if ( true === $result['success'] ) {
				add_settings_error(
					'app_license',
					'license_deactivated',
					'Đã deactivate license.',
					'success'
				);
			} else {
				add_settings_error(
					'app_license',
					'license_deactivate_error',
					'Lỗi: ' . ( $result['error'] ?? 'Không xác định' ),
					'error'
				);
			}
		} elseif ( 'check' === $action ) {
			$this->validate( true );
			add_settings_error(
				'app_license',
				'license_checked',
				'Đã kiểm tra lại license.',
				'info'
			);
		}
	}

	/**
	 * Cron: validate license định kỳ.
	 */
	public function cron_validate_license() {
		$this->validate( true );
	}

	// =========================================================================
	// HELPER METHODS
		// =========================================================================

	/**
	 * Kiểm tra license có đang active không.
	 *
	 * @return bool
	 */
	public function is_active() {
		$result = $this->validate();
		return true === $result['valid'];
	}

	/**
	 * Lấy license key hiện tại.
	 *
	 * @return string
	 */
	public function get_license_key() {
		return get_option( $this->option_license_key, '' );
	}

	/**
	 * Lấy dữ liệu license đã cache.
	 *
	 * @return array
	 */
	public function get_cached_data() {
		return get_option( $this->option_license_data, array() );
	}

	/**
	 * Lấy ngày hết hạn định dạng.
	 *
	 * @return string  Ngày hết hạn hoặc 'N/A'.
	 */
	public function get_expiry_date() {
		$data = $this->get_cached_data();
		if ( ! empty( $data['expires_at'] ) ) {
			return wp_date( 'd/m/Y', strtotime( $data['expires_at'] ) );
		}
		return 'N/A';
	}

	/**
	 * Lấy số ngày còn lại.
	 *
	 * @return int|null  Số ngày hoặc null nếu không có data.
	 */
	public function get_days_left() {
		$data = $this->get_cached_data();
		if ( ! empty( $data['expires_at'] ) ) {
			$expire = strtotime( $data['expires_at'] );
			return max( 0, (int) ceil( ( $expire - time() ) / 86400 ) );
		}
		return null;
	}

	/**
	 * Lấy IP client.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Xử lý comma-separated IPs
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Render License Settings UI trong admin.
	 *
	 * @return void
	 */
	public function render_license_settings() {
		$license_key  = $this->get_license_key();
		$license_data = $this->get_cached_data();
		$is_active    = $this->is_active();
		$days_left    = $this->get_days_left();
		$expiry_date  = $this->get_expiry_date();

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'License — AJAX Pagination Pro', 'ajax-pagination-pro' ); ?></h2>

			<?php settings_errors( 'app_license' ); ?>

			<div class="app-license-panel" style="max-width: 600px; margin-top: 20px;">
				<?php if ( $is_active ) : ?>
					<!-- ACTIVE LICENSE -->
					<div class="notice notice-success inline" style="padding: 15px 20px;">
						<p style="font-size: 14px;">
							<strong><?php esc_html_e( '✅ License Active', 'ajax-pagination-pro' ); ?></strong>
						</p>
						<table class="form-table" style="margin: 10px 0;">
							<tr>
								<th style="padding: 5px 10px 5px 0;"><?php esc_html_e( 'License Key:', 'ajax-pagination-pro' ); ?></th>
								<td><code><?php echo esc_html( $license_key ); ?></code></td>
							</tr>
							<tr>
								<th style="padding: 5px 10px 5px 0;"><?php esc_html_e( 'Hết hạn:', 'ajax-pagination-pro' ); ?></th>
								<td>
									<?php echo esc_html( $expiry_date ); ?>
									<?php if ( null !== $days_left && $days_left <= 30 ) : ?>
										<span style="color: #d63638;">(<?php echo esc_html( $days_left ); ?> ngày còn lại)</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th style="padding: 5px 10px 5px 0;"><?php esc_html_e( 'Domain:', 'ajax-pagination-pro' ); ?></th>
								<td><?php echo esc_html( $license_data['domain'] ?? wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></td>
							</tr>
						</table>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'app_license_action', 'app_license_nonce' ); ?>
						<input type="hidden" name="app_license_do" value="deactivate">
						<?php submit_button( 'Deactivate License', 'secondary', 'submit', false ); ?>
					</form>

					<form method="post" action="" style="margin-top: 5px;">
						<?php wp_nonce_field( 'app_license_action', 'app_license_nonce' ); ?>
						<input type="hidden" name="app_license_do" value="check">
						<?php submit_button( 'Kiểm tra lại License', 'secondary', 'submit', false ); ?>
					</form>

				<?php else : ?>
					<!-- INACTIVE LICENSE -->
					<div class="notice notice-warning inline" style="padding: 15px 20px;">
						<p style="font-size: 14px;">
							<strong><?php esc_html_e( '⚠️ Chưa kích hoạt License', 'ajax-pagination-pro' ); ?></strong>
						</p>
						<p><?php esc_html_e( 'Nhập License Key để kích hoạt plugin và nhận cập nhật.', 'ajax-pagination-pro' ); ?></p>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'app_license_action', 'app_license_nonce' ); ?>
						<input type="hidden" name="app_license_do" value="activate">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="app_license_key"><?php esc_html_e( 'License Key', 'ajax-pagination-pro' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										id="app_license_key"
										name="app_license_key"
										class="regular-text"
										placeholder="AJAXPAG-XXXX-XXXX-XXXX"
										value="<?php echo esc_attr( $license_key ); ?>"
										required
									>
									<p class="description">
										<?php esc_html_e( 'Nhập License Key nhận được qua email sau khi mua.', 'ajax-pagination-pro' ); ?>
									</p>
								</td>
							</tr>
						</table>
						<?php submit_button( 'Kích hoạt License', 'primary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
