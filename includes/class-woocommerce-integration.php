<?php
/**
 * WooCommerce Integration — Tự động tạo License khi mua hàng qua WooCommerce
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
 * Class AJAX_Pagination_Pro_WooCommerce_Integration
 *
 * Tích hợp với WooCommerce:
 * 1. Tự động tạo License Key khi đơn hàng hoàn tất (processing → completed)
 * 2. Gửi License Key qua email đơn hàng
 * 3. Hiển thị License Key trên trang "My Account"
 * 4. Hủy License khi đơn hàng bị hoàn tiền
 *
 * @since 1.1.0
 */
class AJAX_Pagination_Pro_WooCommerce_Integration {

	/**
	 * Single instance.
	 *
	 * @var AJAX_Pagination_Pro_WooCommerce_Integration|null
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
	 * Product IDs được gắn với license (có thể configure trong admin).
	 *
	 * @var array
	 */
	private $licensed_products = array();

	/**
	 * Get singleton instance.
	 *
	 * @return AJAX_Pagination_Pro_WooCommerce_Integration
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
		// Kiểm tra WooCommerce có active không
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		// Lấy danh sách product IDs được cấu hình
		$this->licensed_products = array_map( 'absint', get_option( 'app_wc_product_ids', array() ) );

		// Hooks chính
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_cancelled' ), 10, 1 );

		// Hiển thị License Key trong email
		add_action( 'woocommerce_email_after_order_table', array( $this, 'display_license_in_email' ), 10, 4 );

		// Hiển thị License Key trên My Account
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_license_in_order' ) );

		// Admin: Thêm field cấu hình product IDs
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_license_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_license_field' ) );

		// Admin: Settings page
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_license', array( $this, 'render_settings_tab' ) );
		add_action( 'woocommerce_update_options_license', array( $this, 'save_settings' ) );
	}

	// =========================================================================
	// ORDER HOOKS
	// =========================================================================

	/**
	 * Khi đơn hàng hoàn tất → tạo License.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Kiểm tra đã có license chưa (tránh duplicate)
		$existing_key = $order->get_meta( '_app_license_key' );
		if ( ! empty( $existing_key ) ) {
			return;
		}

		// Kiểm tra order có chứa product licensed không
		$licensed_items = $this->get_licensed_items( $order );
		if ( empty( $licensed_items ) ) {
			return;
		}

		// Lấy thông tin customer
		$customer_name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		$customer_email = $order->get_billing_email();
		$domain         = wp_parse_url( home_url(), PHP_URL_HOST );

		// Gọi API tạo license
		$response = $this->api_create_license( $customer_name, $customer_email, $domain, $order_id );

		if ( is_wp_error( $response ) ) {
			// Log lỗi nhưng không block đơn hàng
			$order->add_order_note(
				sprintf(
					'[AJAX Pagination Pro] Lỗi tạo License: %s',
					$response->get_error_message()
				)
			);
			return;
		}

		if ( true !== ( $response['success'] ?? false ) ) {
			$order->add_order_note(
				sprintf(
					'[AJAX Pagination Pro] Không thể tạo License: %s',
					$response['error'] ?? 'Lỗi không xác định'
				)
			);
			return;
		}

		$license_key = $response['data']['license_key'] ?? '';
		if ( empty( $license_key ) ) {
			$order->add_order_note( '[AJAX Pagination Pro] API trả về license_key rỗng' );
			return;
		}

		// Lưu License Key vào order meta
		$order->update_meta_data( '_app_license_key', $license_key );
		$order->update_meta_data( '_app_software_id', $this->software_id );
		$order->update_meta_data( '_app_license_data', wp_json_encode( $response['data'] ) );
		$order->save();

		// Log thành công
		$order->add_order_note(
			sprintf(
				'[AJAX Pagination Pro] License Key đã tạo: %s (Hết hạn: %s)',
				$license_key,
				$response['data']['expires_at'] ?? 'N/A'
			)
		);

		// Trigger action cho các extension khác
		do_action( 'app_license_created', $license_key, $response['data'], $order );
	}

	/**
	 * Khi đơn hàng bị hoàn tiền → suspend license.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_refunded( $order_id ) {
		$this->suspend_order_license( $order_id, 'refunded' );
	}

	/**
	 * Khi đơn hàng bị hủy → suspend license.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_cancelled( $order_id ) {
		$this->suspend_order_license( $order_id, 'cancelled' );
	}

	/**
	 * Suspend license của đơn hàng.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $reason   Lý do (refunded/cancelled).
	 */
	private function suspend_order_license( $order_id, $reason ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$license_key = $order->get_meta( '_app_license_key' );
		if ( empty( $license_key ) ) {
			return;
		}

		$response = $this->api_call( 'license.suspend', array(
			'license_key' => $license_key,
			'action'      => 'suspend',
		), 'POST' );

		if ( ! is_wp_error( $response ) && true === ( $response['success'] ?? false ) ) {
			$order->update_meta_data( '_app_license_suspended', 'yes' );
			$order->update_meta_data( '_app_license_suspend_reason', $reason );
			$order->save();

			$order->add_order_note(
				sprintf(
					'[AJAX Pagination Pro] License đã bị khóa (Lý do: %s): %s',
					$reason,
					$license_key
				)
			);
		}
	}

	// =========================================================================
	// DISPLAY LICENSE IN EMAIL & ORDER
	// =========================================================================

	/**
	 * Hiển thị License Key trong email đơn hàng.
	 *
	 * @param WC_Order $order   Order object.
	 * @param bool     $sent_to_admin  Có phải gửi cho admin không.
	 * @param bool     $plain_text     Email dạng plain text.
	 * @param WC_Email $email   Email object.
	 */
	public function display_license_in_email( $order, $sent_to_admin, $plain_text, $email ) {
		// Chỉ hiển thị cho customer
		if ( $sent_to_admin ) {
			return;
		}

		// Chỉ hiển thị cho email completed_order
		if ( 'customer_completed_order' !== $email->id ) {
			return;
		}

		$license_key = $order->get_meta( '_app_license_key' );
		if ( empty( $license_key ) ) {
			return;
		}

		$license_data = json_decode( $order->get_meta( '_app_license_data' ), true );

		if ( $plain_text ) {
			echo "\n--- License Key ---\n";
			echo 'License Key: ' . $license_key . "\n";
			echo 'Sản phẩm: AJAX Pagination Pro' . "\n";
			if ( ! empty( $license_data['expires_at'] ) ) {
				echo 'Hết hạn: ' . wp_date( 'd/m/Y', strtotime( $license_data['expires_at'] ) ) . "\n";
			}
			echo "Nhập License Key trong trang cài đặt Plugin để kích hoạt.\n";
		} else {
			echo '<h3 style="color: #667eea; margin-top: 20px;">License Key</h3>';
			echo '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
			echo '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>License Key:</strong></td>';
			echo '<td style="padding: 8px; border-bottom: 1px solid #eee;"><code style="font-size: 16px; color: #667eea; font-weight: bold;">' . esc_html( $license_key ) . '</code></td></tr>';
			echo '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Sản phẩm:</strong></td>';
			echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">AJAX Pagination Pro</td></tr>';

			if ( ! empty( $license_data['expires_at'] ) ) {
				echo '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Hết hạn:</strong></td>';
				echo '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html( wp_date( 'd/m/Y', strtotime( $license_data['expires_at'] ) ) ) . '</td></tr>';
			}

			echo '</table>';
			echo '<p style="color: #666;">Nhập License Key trong trang cài đặt Plugin để kích hoạt và nhận cập nhật tự động.</p>';
		}
	}

	/**
	 * Hiển thị License Key trên trang Order Details (My Account).
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_license_in_order( $order ) {
		$license_key = $order->get_meta( '_app_license_key' );
		if ( empty( $license_key ) ) {
			return;
		}

		$license_data = json_decode( $order->get_meta( '_app_license_data' ), true );
		$suspended    = 'yes' === $order->get_meta( '_app_license_suspended' );

		?>
		<section class="app-license-info" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'License Key', 'ajax-pagination-pro' ); ?></h3>
			<table style="width: 100%;">
				<tr>
					<td><strong><?php esc_html_e( 'License Key:', 'ajax-pagination-pro' ); ?></strong></td>
					<td><code style="font-size: 15px; color: #667eea; font-weight: bold;"><?php echo esc_html( $license_key ); ?></code></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Sản phẩm:', 'ajax-pagination-pro' ); ?></strong></td>
					<td>AJAX Pagination Pro</td>
				</tr>
				<?php if ( ! empty( $license_data['expires_at'] ) ) : ?>
					<tr>
						<td><strong><?php esc_html_e( 'Hết hạn:', 'ajax-pagination-pro' ); ?></strong></td>
						<td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $license_data['expires_at'] ) ) ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td><strong><?php esc_html_e( 'Trạng thái:', 'ajax-pagination-pro' ); ?></strong></td>
					<td>
						<?php if ( $suspended ) : ?>
							<span style="color: #dc3545;"><?php esc_html_e( 'Đã khóa', 'ajax-pagination-pro' ); ?></span>
						<?php else : ?>
							<span style="color: #28a745;"><?php esc_html_e( 'Hoạt động', 'ajax-pagination-pro' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</section>
		<?php
	}

	// =========================================================================
	// ADMIN: PRODUCT LICENSE FIELD
	// =========================================================================

	/**
	 * Thêm checkbox "Licensed Product" trong trang edit product.
	 */
	public function add_product_license_field() {
		echo '<div class="options_group">';

		woocommerce_wp_checkbox( array(
			'id'          => '_app_licensed_product',
			'label'       => __( 'Licensed Product', 'ajax-pagination-pro' ),
			'description' => __( 'Đánh dấu sản phẩm này sẽ tự động tạo License Key khi mua.', 'ajax-pagination-pro' ),
		) );

		echo '</div>';
	}

	/**
	 * Lưu checkbox licensed product.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_license_field( $post_id ) {
		$is_licensed = isset( $_POST['_app_licensed_product'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_app_licensed_product', $is_licensed );
	}

	// =========================================================================
	// ADMIN: SETTINGS TAB
	// =========================================================================

	/**
	 * Thêm Settings Tab "License" trong WooCommerce Settings.
	 *
	 * @param array $tabs Existing tabs.
	 *
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['license'] = __( 'License', 'ajax-pagination-pro' );
		return $tabs;
	}

	/**
	 * Render Settings Tab.
	 */
	public function render_settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Lưu Settings.
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * Lấy settings fields.
	 *
	 * @return array
	 */
	private function get_settings() {
		return array(
			array(
				'title' => __( 'Cấu hình License — AJAX Pagination Pro', 'ajax-pagination-pro' ),
				'type'  => 'title',
				'id'    => 'app_license_options',
			),
			array(
				'title'    => __( 'API URL', 'ajax-pagination-pro' ),
				'desc'     => __( 'URL của License Manager API.', 'ajax-pagination-pro' ),
				'id'       => 'app_license_api_url',
				'type'     => 'url',
				'default'  => $this->api_url,
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Software ID', 'ajax-pagination-pro' ),
				'desc'     => __( 'ID sản phẩm trên License Manager.', 'ajax-pagination-pro' ),
				'id'       => 'app_license_software_id',
				'type'     => 'text',
				'default'  => $this->software_id,
				'desc_tip' => true,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'app_license_options',
			),
		);
	}

	// =========================================================================
	// API CALLS
	// =========================================================================

	/**
	 * Gọi License Manager API.
	 *
	 * @param string $action  Action name.
	 * @param array  $params  Tham số.
	 * @param string $method  HTTP method.
	 *
	 * @return array|WP_Error
	 */
	private function api_call( $action, $params = array(), $method = 'GET' ) {
		$params['action']  = $action;
		$params['api_key'] = '59bf9dca4289daafb4199a4c0b5176b1';

		// Override URL từ settings
		$api_url = get_option( 'app_license_api_url', $this->api_url );

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $params );
			$response = wp_remote_post( $api_url, $args );
		} else {
			$url = add_query_arg( $params, $api_url );
			$response = wp_remote_get( $url, $args );
		}

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

	/**
	 * Gọi API tạo License.
	 *
	 * @param string $name   Tên khách.
	 * @param string $email  Email khách.
	 * @param string $domain Domain.
	 * @param int    $order_id Order ID (ghi vào notes).
	 *
	 * @return array|WP_Error
	 */
	private function api_create_license( $name, $email, $domain, $order_id ) {
		return $this->api_call( 'license.create', array(
			'software_id'    => get_option( 'app_license_software_id', $this->software_id ),
			'customer_name'  => $name,
			'customer_email' => $email,
			'domain'         => $domain,
			'notes'          => 'WooCommerce Order #' . $order_id,
		), 'POST' );
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Kiểm tra WooCommerce có active không.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Lấy các item trong order là licensed products.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return array
	 */
	private function get_licensed_items( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			// Kiểm tra theo setting product IDs hoặc meta field
			if ( in_array( $product_id, $this->licensed_products, true ) ) {
				$items[] = $item;
				continue;
			}

			$is_licensed = get_post_meta( $product_id, '_app_licensed_product', true );
			if ( 'yes' === $is_licensed ) {
				$items[] = $item;
			}
		}

		return $items;
	}
}
