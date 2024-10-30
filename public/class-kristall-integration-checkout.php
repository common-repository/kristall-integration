<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-validator.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-product-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-api.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'public/class-kristall-integration-checkout-renderer.php';

class Kristall_Integration_Checkout implements Kristall_Integration_Module {
	private $api_url;
	private $api_key;

	private $field_names;
	private $option_name;
	private $plugin_name;

	/**
	 * Рендерер.
	 *
	 * @var Kristall_Integration_Checkout_Renderer $renderer Рендерер.
	 */
	private $renderer;

	public function __construct($plugin_name, $plugin_settings) {
		$this->plugin_name = $plugin_name;
		$this->option_name = $plugin_settings['option_name'];
		$this->field_names = $plugin_settings['field_names'];
		$options = get_option($this->option_name);
		$this->api_url = $options ? $options[$this->field_names['api_url']] : '';
		$this->api_key = $options ? $options[$this->field_names['api_key']] : '';
		$this->renderer = new Kristall_Integration_Checkout_Renderer();
	}

	/*============================================================================
   * Регистрация модуля
	 ============================================================================*/

	public function define_hooks($loader) {
		$loader->add_action('wp_enqueue_scripts', $this, 'enqueue_styles');
		$loader->add_action('wp_enqueue_scripts', $this, 'insert_custom_css');
		$loader->add_action('wp_enqueue_scripts', $this, 'enqueue_scripts');
		$loader->add_filter('woocommerce_coupons_enabled', $this, 'disable_coupon_field');
		$loader->add_filter('woocommerce_checkout_fields', $this, 'configure_form_fields');
		$loader->add_action('woocommerce_before_checkout_billing_form', $this, 'check_available_client_types');
		$loader->add_filter('woocommerce_checkout_posted_data', $this, 'preprocess_post_data');
		$loader->add_action('woocommerce_after_checkout_validation', $this, 'validate_form', 10, 2);
		$loader->add_action('woocommerce_checkout_update_order_meta', $this, 'save_custom_checkout_fields', 10, 2);
		$loader->add_filter('woocommerce_thankyou_order_received_text', $this, 'redirect_to_kristall');
		// $loader->add_action('template_redirect', $this, 'check_cart_cookie');
		$loader->add_action('woocommerce_before_calculate_totals', $this, 'update_cart_prices', 20, 1);
		$loader->add_action('woocommerce_new_order', $this, 'process_new_order', 20, 1);
		$loader->add_filter('woocommerce_form_field', $this, 'form_fields_markup', 10, 4);
		$loader->add_filter('woocommerce_order_button_html', $this, 'remove_place_order_button');

		// AJAX endpoints
		$loader->add_action('wc_ajax_krl_v3_buy_now', $this, 'ajax_buy_now_handler');
		$loader->add_action('wc_ajax_krl_v3_verify_checkout_data', $this, 'ajax_verify_checkout_data_handler');
		$loader->add_action('wc_ajax_krl_v3_verification_code_action', $this, 'ajax_verification_code_action_handler');
		$loader->add_action('wc_ajax_krl_v3_check_client_registration', $this, 'ajax_check_client_registration_handler');

		// Получаем данные о платеже тиньков
		$loader->add_action('woocommerce_order_edit_status', $this, 'get_tinkoff_payment_details', 10, 2);		
	}
	
	public function enqueue_styles() {
		wp_enqueue_style($this->plugin_name . '-common', plugin_dir_url(__FILE__) . '../common/css/kristall-integration-common.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
		wp_enqueue_style($this->plugin_name . '-checkout', plugin_dir_url(__FILE__) . 'css/kristall-integration-checkout.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
	}

	public function enqueue_scripts() {
		if (!is_admin() && !Kristall_Integration_Utils::is_ajax() && function_exists('is_checkout') && is_checkout()) {
			wp_enqueue_script($this->plugin_name . '-common', plugin_dir_url(__FILE__) . '../common/js/kristall-integration-common.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
			wp_enqueue_script($this->plugin_name . '-maskedinput', plugin_dir_url(__FILE__) . 'js/jquery.maskedinput.min.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
			wp_enqueue_script($this->plugin_name . '-checkout', plugin_dir_url(__FILE__) . 'js/kristall-integration-checkout.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
		}
	}

	/*============================================================================
	 * Публичные AJAX endpoints
	 ============================================================================*/

	public function ajax_buy_now_handler() {
		$types = ['simple', 'variable'];
		if (!isset($_POST) || !isset($_POST['id']) || empty($_POST['quantity']) || !preg_match("~^\d+$~", $_POST['quantity']) || !isset($_POST['type']) || !in_array($_POST['type'], $types)) {
			wp_send_json([
				'status' => 'error',
				'message' => esc_html__('Переданы некорректные данные.', 'kristall-integration')
			]);
			wp_die();
		}

		$is_variation = $_POST['type'] == 'variable';

		if ($is_variation && !isset($_POST['variationId'])) {
			wp_send_json([
				'status' => 'error',
				'message' => esc_html__('Переданы некорректные данные. Ключ "variationId" отсутствует.', 'kristall-integration')
			]);
			wp_die();
		}

		$quantity = (int)$_POST['quantity'];
		if ($quantity < 1) $quantity = 1;

		// Проверяем продукт

		$product_id = (int)$_POST['id'];
		$_pf = new WC_Product_Factory();
		$product = $_pf->get_product($product_id);

		if (empty($product)) {
			wp_send_json([
				'status' => 'error',
				'message' => esc_html__('Продукт не найден.', 'kristall-integration')
			]);
			wp_die();
		}

		$variation_id = $is_variation ? (int)$_POST['variationId'] : null;
		if ($product->is_type('variable') && $is_variation) {
			$variations = $product->get_available_variations();
			$variations_id = wp_list_pluck($variations, 'variation_id');
			if (!in_array($variation_id, $variations_id)) {
				wp_send_json([
					'status' => 'error',
					'message' => esc_html__('Продукт не имеет вариации.', 'kristall-integration')
				]);
				wp_die();
			}

			$variation_obj = new WC_Product_Variation($variation_id);
			if (!$variation_obj->is_purchasable() || !$variation_obj->is_in_stock()) {
				wp_send_json([
					'status' => 'error',
					'message' => esc_html__('Нет в наличии.', 'kristall-integration')
				]);
				wp_die();
			}
		} else if ($product->is_type('simple')) {
			if (!$product->is_purchasable() || !$product->is_in_stock()) {
				wp_send_json([
					'status' => 'error',
					'message' => esc_html__('Продукт не найден или нет в наличии.', 'kristall-integration')
				]);
				wp_die();
			}
		} else {
			wp_send_json([
				'status' => 'error',
				'message' => esc_html__('Тип продукта не поддерживается.', 'kristall-integration')
			]);
			wp_die();
		}

		$cart = array_values(WC()->cart->get_cart());

		if (count($cart) == 1 && $cart[0]['product_id'] == $product_id && $cart[0]['quantity'] == $quantity && (!$cart[0]['variation_id'] || $cart[0]['variation_id'] == $variation_id)) {
			wp_send_json([
				'status' => 'success',
				'redirectUrl' => wc_get_checkout_url()
			]);
			wp_die();
		}

		// Собираем данные о товарах в корзине
		// $cart_data = [];
		// foreach ($cart as $item) {
		// 	$cart_data[] = $item['product_id'] . ',' . $item['quantity'] .
		// 		($item['variation_id'] ? (',' . $item['variation_id']) : '');
		// }

		// Сохраняем текущую корзину
		// $cookie_name = $this->get_prefixed_key('cart', '_');
		// $cookie_domain = '.' . preg_replace("/^https?:\/+|\/+/", '', get_site_url());
		// $cookie_secure = is_ssl();
		// setcookie($cookie_name, count($cart_data) != 0 ? implode(';', $cart_data) : 'none', 0, '/', $cookie_domain, $cookie_secure, true);

		// Очищаем корзину и добавляем товар
		WC()->cart->empty_cart();

		wp_send_json([
			'status' => 'success',
			'redirectUrl' => wc_get_checkout_url()
		]);
	}

	/**
	 * Обработчик API для проверки данных оформления заказа
	 */
	public function ajax_verify_checkout_data_handler() {	
		// Подключаем зависимости Woocommerce 
		
		$checkout = WC_Checkout::instance();
		$errors = new WP_Error();
		$posted_data = $checkout->get_posted_data();

		$this->validate_form($posted_data, $errors, false);
		if ($errors->has_errors()) {
			wp_send_json([
				'status' => 'error',
				'errors' => json_encode(array_values($errors->errors))
			]);
			wp_die();
		}

		// Получаем инфо о товарах в корзине и данные для отправки на сервер

		$payload = $this->get_kristall_api_payload($posted_data);

		$cart_items = [];
		$cart = WC()->cart->get_cart();
		$original_prices = [];

		foreach ($cart as $cart_item_key => $cart_item) {
			// Пропускаем вариативные продукты
			if ($cart_item['variation_id']) continue;

			$qty = $cart_item['quantity'];
			$sku = $cart_item['data']->get_sku();

			$cart_items[] = $cart_item['product_id'] . ':' . $qty . ':' . $sku;
			$original_prices[] = $cart_item['product_id'] . ':' . wc_get_product($cart_item['product_id'])->get_price();
		}
		sort($original_prices, SORT_STRING);

		$payload['items'] = implode(';', $cart_items);
		$payload['original_prices'] = implode(';', $original_prices);

		// Отправляем данные на сервер

		$api = new Kristall_Integration_API($this->api_url, $this->api_key);
		$api_result = $api->request('products', 'wcOrder.verifyData', $payload);

		if ($api_result->status == 'error') {
			wp_send_json([
				'status' => 'error',
				'errors' =>
					$api_result->getCode() == 403 ?
					  esc_html__('Произошла ошибка доступа при проверке подключения к API. Пожалуйста, свяжитесь с администрацией сайта.', 'kristall-integration') :
						$this->interpolate_api_errors($api_result->getMessage())
			]);
			wp_die();
		}

		$api_data = $api_result->data->response;
		WC()->session->set(
			'_krl_discount_code',
			isset($posted_data['kristall-integration_billing_agent']) && !empty($posted_data['kristall-integration_billing_agent']) ?
				$posted_data['kristall-integration_billing_agent'] :
				null
		);
		WC()->session->set('_krl_prices', $api_data->prices);

		// Проверяем есть ли скидка

		$api_data->hasDiscounts = false;
		$api_data->fullPrice = 0;
		$api_data->discountPrice = 0;

		foreach ($api_data->origPrices as $k => $price) {
			$api_data->fullPrice += $price;
		}
		foreach ($api_data->prices as $k => $price) {
			$api_data->discountPrice += $price;
		}

		if ($api_data->fullPrice == $api_data->discountPrice) {
			unset($api_data->fullPrice);
			unset($api_data->discountPrice);	
		} else {
			$api_data->hasDiscounts = true;
		}

		unset($api_data->origPrices);
		unset($api_data->prices);	

		// Обновляем количество продуктов если требуется
		$api_data->qtyUpdated = false;
		if (isset($api_data->qty) && count($api_data->qty)) {
			$api_data->qtyUpdated = true;
			foreach ($cart as $cart_item_key => $cart_item) {
				$prod_id = $cart_item['product_id'];
				if (!$cart_item['variation_id'] && isset($api_data->qty->{$prod_id})) {
					WC()->cart->set_quantity($cart_item_key, $api_data->qty->{$prod_id});
				}
			}
			unset($api_data->qty);
		}

		$response = [
			'status' => 'ok',
			'data' => $api_data
		];

		wp_send_json($response);
	}

	/**
	 * Обработчик API для манипуляций с проверочным кодом
	 */
	public function ajax_verification_code_action_handler() {
		$checkout = WC_Checkout::instance();
		$posted_data = $checkout->get_posted_data();
		$action = $_POST['action'] ?? '';

		if (
			!in_array($action, ['select', 'resend', 'verify']) ||
			!isset($_POST['param']) || empty($_POST['param']) ||
			!isset($_POST['signature']) || empty($_POST['signature'])
		) {
			wp_send_json([
				'status' => 'error',
				'error' => esc_html__('Ошибка передачи данных.', 'kristall-integration')
			]);
			wp_die();
		}

		// Получаем инфо о товарах в корзине

		$cart_items = [];
		$cart = WC()->cart->get_cart();
		$original_prices = [];

		foreach ($cart as $cart_item) {
			// Пропускаем вариативные продукты
			if ($cart_item['variation_id']) continue;

			$cart_items[] = $cart_item['product_id'] . ':' . $cart_item['quantity'] . ':' . $cart_item['data']->get_sku();
			$original_prices[] = $cart_item['product_id'] . ':' . wc_get_product($cart_item['product_id'])->get_price();
		}
		sort($original_prices, SORT_STRING);

		// Собираем данные для отправки на сервер

		$payload = $this->get_kristall_api_payload($posted_data);
		$payload['items'] = implode(';', $cart_items);
		$payload['original_prices'] = implode(';', $original_prices);
		$payload['param'] = $_POST['param'];
		$payload['signature'] = $_POST['signature'];

		if ($action == 'resend') {
			$payload['deliveryMethod'] = $_POST['deliveryMethod'] ?? 'sms';
		}

		if ($action == 'verify') {
			$payload['verificationCode'] = $_POST['verificationCode'] ?? '';
		}

		// Отправляем данные на сервер

		switch ($action) {
			case 'select':
				$api_method = 'selectClient';
				break;
			case 'resend':
				$api_method = 'resendCode';
				break;
			default:
				$api_method = 'verifyCode';
		}

		$api = new Kristall_Integration_API($this->api_url, $this->api_key);
		$api_result = $api->request('products', 'wcOrder.' . $api_method, $payload);

		if ($api_result->status == 'error') {
			wp_send_json([
				'status' => 'error',
				'error' =>
					$api_result->getCode() == 403 ?
					  esc_html__('Произошла ошибка доступа при проверке подключения к API. Пожалуйста, свяжитесь с администрацией сайта.', 'kristall-integration') :
						$this->interpolate_api_errors($api_result->getMessage())
			]);
			wp_die();
		}

		$api_data = $api_result->data->response;

		$response = [
			'status' => 'ok'
		];

		if ($action !== 'verify' || isset($api_data->error)) {
			$response['data'] = $api_data;
		} else {
			WC()->session->set('_krl_order_param', $_POST['param']);
			WC()->session->set('_krl_order_signature', $api_data->signature);
			$response['data'] = [ 'completed' => true ];
		}

		wp_send_json($response);
		wp_die();
	}

	/**
	 * Обработчик API для проверки регистрации клиента в МБС Кристалл
	 */
	public function ajax_check_client_registration_handler() {
		$response = new stdClass;
		$response->status = 'ok';
		$response->invalidFields = [];

		// Тип клиента

		$type = $_POST['type'] ?? '';

		if (!in_array($type, ['personal', 'organization'])) {
			$response->status = 'error';
			$response->error = esc_html__('Неизвестный тип клиента.', 'kristall-integration');
			wp_send_json($response);
			wp_die();
		}

		// Очищаем данные полтзователя
		$this->clear_client_info_session($type);

		// Данные для проверки

		$phone = $_POST['phone'] ?? '';
		$inn = $_POST['inn'] ?? '';
		$ogrn = $_POST['ogrn'] ?? '';

		// Проверяем данные перед отправкой на сервер

		$validator = new Kristall_Integration_Validator();

		if (!$validator->phone($phone)->is_valid()) {
			$response->invalidFields[] = 'phone';
		}

		if ($type == 'organization') {
			if (!$validator->inn($inn)->is_valid()) {
				$response->invalidFields[] = 'inn';
			}
	
			$inn_len = strlen($inn);
	
			if (($inn_len == 10 && !$validator->ogrn($ogrn)->is_valid()) || ($inn_len == 12 && !$validator->ogrnip($ogrn)->is_valid())) {
				$response->invalidFields[] = 'ogrn';
			}
		}

		// Если есть ошибки, сразу возвращаем
		if (count($response->invalidFields)) {
			$response->status = 'error';
			wp_send_json($response);
			wp_die();
		} else {
			unset($response->invalidFields);
		}

		$payload = [
			'type' => $type,
			'phone' => $phone,
			'inn' => $inn,
			'ogrn' => $ogrn
		];

		$api = new Kristall_Integration_API($this->api_url, $this->api_key);
		$api_result = $api->request('products', 'wcOrder.checkClientRegistration', $payload);

		if ($api_result->status == 'error') {
			$response->status = 'error';
			$response->error = $api_result->getCode() == 403 ?
				esc_html__('Произошла ошибка доступа при проверке подключения к API. Пожалуйста, свяжитесь с администрацией сайта.', 'kristall-integration') :
				$api_result->getMessage();
			wp_send_json($response);
			wp_die();
		}

		$api_data = $api_result->data->response->data;

		// Если физ или юр лицо не существует
		if (($type == 'personal' && !$api_data->flExists) || ($type == 'organization' && !$api_data->ulExists)) {
			$this->clear_client_info_session($type);
			$response->data = [
				'ulExists' => false,
				'flExists' => false
			];
			wp_send_json($response);
			wp_die();
		}

		$session_data = new stdClass;
		$session_data->phone = $phone;

		if ($type == 'personal' || $api_data->flExists) {
			$session_data->firstName = $api_data->firstName;
			$session_data->secondName = $api_data->secondName;
			$session_data->thirdName = $api_data->thirdName;
			$session_data->email = $api_data->email;	
		}

		if ($type == 'organization') {
			$session_data->inn = $inn;
			$session_data->ogrn = $ogrn;
			$session_data->company = $api_data->company;
		}

		// Сохраняем данные в сессию
		$client_info = WC()->session->get('_krl_client_info', null);
		if (empty($client_info)) $client_info = [];
		$client_info[$type] = $session_data;
		WC()->session->set('_krl_client_info', $client_info);

		$response->data = [
			'ulExists' => $api_data->ulExists ?? false,
			'flExists' => $api_data->flExists ?? false
		];

		return wp_send_json($response);
	}

	/*============================================================================
	 * Экшны и фильтры
	 ============================================================================*/

	/**
	 * Обновляет цены в корзине
	 */
	public function update_cart_prices($cart) {
		$new_prices = WC()->session->get('_krl_prices', []);

		foreach ($cart->get_cart() as $cart_item) {
			// Пропускаем вариативные продукты
			if ($cart_item['variation_id']) continue;

			$price = $new_prices->{$cart_item['product_id']} ?? (new WC_Product($cart_item['product_id']))->get_price();
			$cart_item['data']->set_price($price);
		}
	}
	
	/**
	 * Обновляет мета-данные нового заказа и чистит сессию
	 */
	public function process_new_order($order_id) {
		$signature = WC()->session->get('_krl_order_signature', '');
		$discount_code = WC()->session->get('_krl_discount_code', null);
		$prices = WC()->session->get('_krl_prices', null);

		update_post_meta($order_id, 'krl_signature', $signature);

		if (!empty($signature)) WC()->session->set('_krl_order_signature', null);
		if (!empty($discount_code)) WC()->session->set('_krl_discount_code', null);
		if (!empty($prices)) WC()->session->set('_krl_prices', null);
		$this->clear_client_info_session();
	}
	
	/**
	 * Проверяет cookies на наличие данных сохраненной корзины.
	 * Если данные найдены, то восстанавливает корзину.
	 */
	// public function check_cart_cookie() {
	// 	$cookie_name = $this->get_prefixed_key('cart', '_');
	// 	if (
	// 		defined('XMLRPC_REQUEST') ||
	// 		defined('REST_REQUEST') ||
	// 		defined('MS_FILES_REQUEST') ||
	// 		(defined('WP_INSTALLING') && WP_INSTALLING) ||
	// 		wp_doing_ajax() ||
	// 		wp_is_json_request() ||
	// 		!isset($_COOKIE[$cookie_name]) ||
	// 		is_cart() ||
	// 		is_checkout() ||
	// 		is_checkout_pay_page()
	// 	) {
	// 		return;
	// 	}

	// 	$cart_cookie = $_COOKIE[$cookie_name];

	// 	if ($cart_cookie === 'none') {
	// 		WC()->cart->empty_cart();
	// 	} else if (preg_match("/\d+,\d+(:?,\d+)?(:?;\d+,\d+(:?,\d+))*/", $cart_cookie)) {
	// 		$cart_items = [];
	// 		$set_strs = explode(';', $cart_cookie);

	// 		foreach ($set_strs as $set_str) {
	// 			$set = explode(',', $set_str);
	// 			$set[0] = (int)$set[0];
	// 			$set[1] = (int)$set[1];
	// 			$set[2] = isset($set[2]) ? (int)$set[2] : 0;

	// 			$product = wc_get_product($set[0]);

	// 			if (empty($product)) {
	// 				continue;
	// 			}

	// 			if ($product->is_type('variable') && $set[2]) {
	// 				$variations = $product->get_available_variations();
	// 				$variations_id = wp_list_pluck($variations, 'variation_id');
	// 				if (!in_array($set[2], $variations_id)) {
	// 					continue;
	// 				}

	// 				$variation_obj = new WC_Product_Variation($set[2]);
	// 				if ($variation_obj->is_purchasable() || $variation_obj->is_in_stock()) {
	// 					$cart_items[] = $set;
	// 				}
	// 			} else if ($product->is_type('simple')) {
	// 				if ($product->is_purchasable() && $product->is_in_stock()) {
	// 					$cart_items[] = $set;
	// 				}
	// 			}
	// 		}

	// 		// Восстанавливаем корзину
	// 		WC()->cart->empty_cart();
	// 		foreach ($cart_items as $cart_item) {
	// 			try {
	// 				WC()->cart->add_to_cart($cart_item[0], $cart_item[1], $cart_item[2]);
	// 			} catch (Exception $e) {
	// 				continue;
	// 			}
	// 		}
	// 	}

	// 	// Удаляем cookie
	// 	$cookie_domain = '.' . preg_replace("/^https?:\/+|\/+/", '', get_site_url());
	// 	$cookie_secure = is_ssl();
	// 	setcookie($cookie_name, null, time() - 3600, '/', $cookie_domain, $cookie_secure, true);

	// 	wp_redirect($_SERVER['REQUEST_URI']);
	// 	exit;
	// }

	/**
	 * Отключает поле промо-кода на странице оплаты.
	 */
	public function disable_coupon_field($enabled) {
		if (is_checkout()) {
			$enabled = false;
		}
		return $enabled;
	}

	/**
	 * Вставляет динамические стили для кнопок выбора типа клиента.
	 * Цвета настраиваются на странице настроек плагина.
	 */
	public function insert_custom_css() {
		$options = get_option($this->option_name);

		if (!isset($options[$this->field_names['btn_border_color']])) {
			return;
		}

		$custom_css = "
			.woocommerce-checkout .{$this->plugin_name}__billing-info__tabs label {
				--krl-tab-btn-bg: {$options[$this->field_names['btn_bg']]};
				--krl-tab-btn-text: {$options[$this->field_names['btn_text_color']]};
				--krl-tab-btn-border: {$options[$this->field_names['btn_border_color']]};
				--krl-tab-btn-active-bg: {$options[$this->field_names['btn_active_bg']]};
				--krl-tab-btn-active-text: {$options[$this->field_names['btn_active_text_color']]};
				--krl-tab-btn-active-border: {$options[$this->field_names['btn_active_border_color']]};
				--krl-tab-btn-active-shadow: {$options[$this->field_names['btn_active_shadow_color']]};
				--krl-tab-btn-hover-bg: {$options[$this->field_names['btn_hover_bg']]};
				--krl-tab-btn-hover-text: {$options[$this->field_names['btn_hover_text_color']]};
				--krl-tab-btn-hover-border: {$options[$this->field_names['btn_hover_border_color']]};
			}
 	  ";

		wp_add_inline_style($this->plugin_name . '-common', $custom_css);
	}

	/**
	 * Прячем кнопку оформления заказа - у нас своя
	 */
	function remove_place_order_button($html) {
		return '';
	}

	/**
	 * Кастомная форма оформления заказа
	 */
	function form_fields_markup($field, $key, $args, $value) {
		if (empty($value)) {
			$value = $args['value'] ?? '';
		}

		if ($key == 'no_payment_method') {
			return '<div class="kristall-integration__checkout_no_pmethods">' . esc_html__('Нет доступных методов оплаты.', 'kristall-integration') . '</div>';
		}

		if (strpos($key, 'billing_') !== 0) {
			if ($key == $this->get_field_name('client_type') || $key == $this->get_field_name('payment_method')) {
				$field = preg_replace("~^<p ~", '<div ', $field);
				$field = preg_replace("~</p>$~", '</div>', $field);
			}
			$field = preg_replace("~([\" ])form-row([\" ])~", '$1$2', $field);
			return $field;
		}

		if (strpos($key, 'billing_ruler_') === 0) {
			return '<hr class="kristall-integration__checkout_ruler" />';
		}

		if (strpos($key, 'billing_header_') === 0) {
			return '<h3 class="kristall-integration__checkout_header'.(isset($args['class']) && count($args['class']) ? ' ' . esc_attr(implode(' ',  $args['class'])) : '').'">' . esc_html($args['label']) . '</h3>';
		}

		if ($key == 'billing_submit') {
			if (empty($this->api_url) || empty($this->api_key)) {
				return '<div class="woocommerce-error" role="alert">'.esc_html__('Невозможно оформить заказ, так как интеграция с МБС Кристалл не настроена. Пожалуйста, обратитесь к администрации сайта.', 'kristall-integration').'</div>';
			} else {
				$btn_label = esc_html($args['label']);
				return '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr($btn_label) . '" data-value="' . esc_attr($btn_label) . '">' . $btn_label . '</button>';
			}
		}

		$label = esc_html($args['label']);
		$checkmark = in_array($key, ['billing_phone', 'billing_inn', 'billing_ogrn']) ? '<div class="kristall-integration__checkout_check"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"></path><path d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z" fill="currentColor"></path></svg></div>' : '';
		$field_html = '<input type="' . esc_attr($args['type']) . '" value="' . esc_attr($value ?? '') . '" placeholder="' . esc_attr($label) . '" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" class="'.esc_attr(implode(' ', $args['class'])).'"'.($args['autocomplete'] ? ' autocomplete="' . esc_attr($args['autocomplete']) . '"' : '').($args['maxlength'] ? ' maxlength="' . esc_attr($args['maxlength']) . '"' : '').' />';
		$field_html = '<div class="kristall-integration__checkout_field' . ($args['required'] ? ' required' : '') . '">' . $field_html . $checkmark . '<label for="' . esc_attr($args['id']) . '">' . $label . '</label></div>';

		if ($key == 'billing_company') {
			$field_html = '<div class="kristall-integration__checkout_cinfo">' . $field_html . '</div>';
		}

		//
		// Оборачивам ИНН и ОГРН в div

		if ($key == 'billing_inn') {
			$field_html = '<div class="kristall-integration__checkout_cbase kristall-integration__checkout_2cols">' . $field_html;
		}
		if ($key == 'billing_ogrn') {
			$field_html = $field_html . '</div>';
		}

		//
		// Оборачивам персональную информацию в div
		if ($key == 'billing_last_name') {
			$field_html = '<div class="kristall-integration__checkout_pinfo">' . $field_html;
		}

		// Оборачиваем имя / отчество в div, чтобы сделать две колонки
		if ($key == 'billing_first_name') {
			$field_html = '<div class="kristall-integration__checkout_2cols">' . $field_html;
		}
		if ($key == 'billing_third_name') {
			$field_html = $field_html . '</div>';
		}

		if ($key == 'billing_email') {
			$field_html = $field_html . '</div>';
		}
		// конец
		//

    return $field_html;
	}

	/**
	 * Конфигурирует форму оплаты.
	 */
	public function configure_form_fields($fields) {
		// Очищаем список полей, будем использовать свои
		$fields['billing'] = [];
		unset($fields['order']['order_comments']);

		$allow_org = false;

		// Заголовок
		$this->renderer->add_form_field($fields['billing'], 'billing_header_client', [
			'label' => esc_html__('Данные покупателя', 'kristall-integration')
		]);

		// Выбор типа клиента
		$allowed_client_types = $this->get_allowed_client_types();
		if (count($allowed_client_types) != 0) {
			$client_type_options = Kristall_Integration_Product_Utils::get_client_type_options($allowed_client_types);
			$allow_org = in_array('entrepreneur', $allowed_client_types) || in_array('organization', $allowed_client_types);

			$this->renderer->add_checkout_tabs($fields['billing'], $client_type_options);

			// ИП и Юр. лица
			if ($allow_org) {
				$this->renderer->add_form_field($fields['billing'], 'billing_inn', [
					'type'      => 'text',
					'label'     => esc_html__('ИНН', 'kristall-integration'),
					'maxlength' => 12,
					'required'  => true,
				]);
				
				$this->renderer->add_form_field($fields['billing'], 'billing_ogrn', [
					'type'     => 'text',
					'label'    => esc_html__('ОГРН', 'kristall-integration'),
					'maxlength' => 15,
					'required' => true,
				]);
			}
		}

		$this->renderer->add_form_field($fields['billing'], 'billing_phone', [
			'type'     => 'tel',
			'label'    => esc_html__('Телефон', 'kristall-integration'),
			'required' => true,
		]);

		if ($allow_org) {
			$this->renderer->add_form_field($fields['billing'], 'billing_company', [
				'type'     => 'text',
				'label'    => esc_html__('Наименование организации', 'kristall-integration'),
				'required' => true,
			]);
		}
		
		$this->renderer->add_form_field($fields['billing'], 'billing_last_name', [
			'type'     => 'text',
			'label'    => esc_html__('Фамилия', 'kristall-integration'),
			'required' => true,
		]);

		$this->renderer->add_form_field($fields['billing'], 'billing_first_name', [
			'type'     => 'text',
			'label'    => esc_html__('Имя', 'kristall-integration'),
			'required' => true,
		]);

		$this->renderer->add_form_field($fields['billing'], 'billing_third_name', [
			'type'     => 'text',
			'label'    => esc_html__('Отчество', 'kristall-integration'),
			'required' => false,
		]);

		$this->renderer->add_form_field($fields['billing'], 'billing_email', [
			'type'     => 'email',
			'label'    => esc_html__('E-mail', 'kristall-integration'),
			'required' => true,
		]);

		// Заголовок
		$this->renderer->add_form_field($fields['billing'], 'billing_header_payment', [
			'label'    => esc_html__('Оплата', 'kristall-integration')
		]);

		// Получаем доступные методы оплаты
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$enabled_gateways = [];
		if($gateways) {
			foreach($gateways as $gateway) {
				if($gateway->enabled == 'yes') {
					$enabled_gateways[] = $gateway->id;
				}
			}
		}
		$this->renderer->add_payment_tabs($fields['billing'], $enabled_gateways);

		$discount_code = WC()->session->get('_krl_discount_code', null);
		if (empty($discount_code)) {
			$discount_code = isset($_COOKIE['uaid']) ? $_COOKIE['uaid'] : '';
		}

		$this->renderer->add_form_field($fields['billing'], 'billing_promo_code', [
			'type'     => 'text',
			'label'    => esc_html__('Промо-код', 'kristall-integration'),
			'value'    => sanitize_text_field($discount_code),
			'required' => false,
		]);

		$this->renderer->add_form_field($fields['billing'], 'billing_ruler_bottom', []);

		// Добавляем флажок условий
		$option_name = $this->field_names['offer_message_id'];
		$options = get_option($this->option_name);

		if (is_array($options) && !empty($options[$option_name])) {
			$checkbox_content = get_post_field('post_content', $options[$option_name]);
			if (!empty($checkbox_content)) {
				$fields['billing'][$this->get_field_name('offer')] =  [
					'type'     => 'checkbox',
					'class'    => ['input-checkbox'],
					'label'    => preg_replace("/<(?:p|div|h[1-6])[^>]*>|<\/(?:p|div|h[1-6])>|\n/", '', $checkbox_content),
					'required' => true,
				];
			}	
		}

		$this->renderer->add_form_field($fields['billing'], 'billing_submit', [
			'label' => esc_html__('Продолжить', 'kristall-integration'),
		]);	

		return $fields;
	}

	/**
	 * Проверяет, разрешена ли продажа продуктов.
	 */
	public function check_available_client_types() {
		if (count($this->get_allowed_client_types()) == 0) {
			$this->renderer->render_client_types_message();
		}
	}

	/**
	 * Трансформирует входящие POST данные во внутренний формат
	 */
	function preprocess_post_data($data) {
		// Подменяем метод оплаты и промо
		$data['payment_method'] = $data['kristall-integration_payment_method'] ?? null;
		$data['kristall-integration_billing_agent'] = $data['billing_promo_code'] ?? '';

		$type = $data['kristall-integration_client_type'] ?? '';
		$phone = $data['billing_phone'] ?? '';
		$inn = $data['billing_inn'] ?? '';
		$ogrn = $data['billing_ogrn'] ?? '';

		$client = $this->get_client_session_info($type, $phone, $inn, $ogrn);

		// Подменяем тип клиента
		$data['kristall-integration_client_type'] = $type === 'personal' ? $type : (
			strlen($ogrn) == 15 ? 'entrepreneur' : 'organization'
		);

		// Подменяем поля из сессии

		$data['billing_first_name'] = $client && isset($client->firstName) ? $client->firstName : ($data['billing_first_name'] ?? '');
		$data['billing_last_name'] = $client && isset($client->secondName) ? $client->secondName : ($data['billing_last_name'] ?? '');
		$data['kristall-integration_billing_patronymic'] = $client && isset($client->thirdName) ? $client->thirdName : ($data['billing_third_name'] ?? '');
		$data['billing_email'] = $client && isset($client->email) ? $client->email : ($data['billing_email'] ?? '');

		if ($type === 'organization') {
			$data['kristall-integration_billing_title'] = $client ? $client->company : ($data['billing_company'] ?? '');
			$data['kristall-integration_billing_inn'] = $client ? $client->inn : ($data['billing_inn'] ?? '');
			if (strlen($inn) === 12) {
				$data['kristall-integration_billing_ogrn'] = '';
				$data['kristall-integration_billing_ogrnip'] = $client ? $client->ogrn : ($data['billing_ogrn'] ?? '');
			} else {
				$data['kristall-integration_billing_ogrn'] = $client ? $client->ogrn : ($data['billing_ogrn'] ?? '');
				$data['kristall-integration_billing_ogrnip'] = '';
			}
		} else {
			$data['kristall-integration_billing_title'] = '';
			$data['kristall-integration_billing_ogrn'] = '';
			$data['kristall-integration_billing_ogrnip'] = '';
		}

		// Удаляем ненужные поля
		if(isset($data['kristall-integration_payment_method'])) unset($data['kristall-integration_payment_method']);
		if(isset($data['billing_promo_code'])) unset($data['billing_promo_code']);
		if(isset($data['billing_third_name'])) unset($data['billing_third_name']);
		if(isset($data['billing_company'])) unset($data['billing_company']);
		if(isset($data['billing_inn'])) unset($data['billing_inn']);
		if(isset($data['billing_ogrn'])) unset($data['billing_ogrn']);

		return $data;
	}

	/**
	 * Проводит валидацию формы оплаты.
	 */
	public function validate_form($data, $errors, $check_session = true) {
		// Удаляем все ошибки, так как мы будем делать валидацию самостоятельно
		foreach ($errors->get_error_codes() as $error_code) {
			$errors->remove($error_code);
		}

		if (empty($this->api_url) || empty($this->api_key)) {
			$errors->add(false, esc_html__('Невозможно оформить заказ, так как интеграция с МБС Кристалл не настроена. Пожалуйста, обратитесь к администрации сайта.', 'kristall-integration'));
			return;
		}

		$param = WC()->session->get('_krl_order_param', null);
		$signature = WC()->session->get('_krl_order_signature', null);
		if ($check_session && (empty($param) || empty($signature))) {
			$errors->add(
				$this->get_field_name('client_type'),
				esc_html__('Прямое оформление заказа не поддерживается.', 'kristall-integration')
			);
			return;
		}

		// Валидация способа оплаты

		if (!isset($data['payment_method']) || ($data['payment_method'] !== 'cod' && $data['payment_method'] !== 'tinkoff')) {
			$errors->add(
				$this->get_field_name('payment_method'),
				esc_html__('Не указан способ оплаты.', 'kristall-integration')
			);
			return;
		}

		// Валидация типа клиента

		$allowed_client_types = $this->get_allowed_client_types();
		$client_type = $data[$this->get_field_name('client_type')];

		if (!in_array($client_type, $allowed_client_types)) {
			$errors->add(
				$this->get_field_name('client_type'),
				esc_html__('В вашей корзине находятся товары предназначенные для разных клиентов. Одновременная покупка невозможна.', 'kristall-integration')
			);
			return;
		}

		// Проверяем продукты в корзине на соответсвие флагам single_in_cart и sold_individually

		$cart = Kristall_Integration_Product_Utils::get_cart();
		$single_in_cart = [];
		$sold_individually = [];

		foreach ($cart as $cart_item) {
			$product = $cart_item['data'];

			if (Kristall_Integration_Product_Utils::is_single_in_cart($product) && count($cart) > 1) {
				$single_in_cart[] = $product;
			}

			if (Kristall_Integration_Product_Utils::is_sold_individually($product) && $cart_item['quantity'] > 1) {
				$sold_individually[] = $product;
			}
		}

		foreach ($single_in_cart as $item) {
			$errors->add(false, esc_html(sprintf(__('Продукт "%s" должен быть едиственным в корзине.', 'kristall-integration'), $item->get_name())));
		}

		foreach ($sold_individually as $item) {
			$errors->add(false, esc_html(sprintf(__('Продукт "%s" допускается к продаже только в единственном экземпляре.', 'kristall-integration'), $item->get_name())));
		}

		if (count($single_in_cart) || count($sold_individually)) return;

		// Проверяем данные

		$validator = new Kristall_Integration_Validator();

		if ($client_type == 'entrepreneur' || $client_type == 'organization') {
			$this->validate_form_field($errors, $validator, 'string', $data, $this->get_field_name('billing_title'), [
				'label' => esc_html__('Наименование организации', 'kristall-integration'),
			]);

			$this->validate_form_field($errors, $validator, 'inn', $data, $this->get_field_name('billing_inn'), [
				'label' => esc_html__('ИНН', 'kristall-integration'),
			]);
		}

		if ($client_type == 'entrepreneur') {
			$this->validate_form_field($errors, $validator, 'ogrnip', $data, $this->get_field_name('billing_ogrnip'), [
				'label' => esc_html__('ОГРНИП', 'kristall-integration'),
			]);
		}

		if ($client_type == 'organization') {
			$this->validate_form_field($errors, $validator, 'ogrn', $data, $this->get_field_name('billing_ogrn'), [
				'label' => esc_html__('ОГРН', 'kristall-integration'),
			]);
		}

		$this->validate_form_field($errors, $validator, 'string', $data, $this->get_field_name('billing_agent'), [
			'label'    => esc_html__('Промо код', 'kristall-integration'),
			'required' => false,
		]);

		$this->validate_form_field($errors, $validator, 'string', $data, 'billing_first_name', [
			'label' => esc_html__('Имя', 'kristall-integration'),
		]);

		$this->validate_form_field($errors, $validator, 'string', $data, 'billing_last_name', [
			'label' => esc_html__('Фамилия', 'kristall-integration'),
		]);

		$this->validate_form_field($errors, $validator, 'phone', $data, 'billing_phone', [
			'label' => esc_html__('Телефон', 'kristall-integration'),
		]);

		$this->validate_form_field($errors, $validator, 'email', $data, 'billing_email', [
			'label' => esc_html__('Email', 'kristall-integration'),
		]);

		// Проверям, приняты ли условия

		$options = get_option($this->option_name);
		$offer_accepted = isset($_POST[$this->get_field_name('offer')]);

		if (is_array($options) && !empty($options[$this->field_names['offer_message_id']]) && !$offer_accepted) {
			$errors->add(
				$this->get_field_name('offer'),
				$options[$this->field_names['offer_error_message']]
			);
		}

		// Удаляем поле метода олаты, у нас свое
		unset($data['payment_method']);

		return $data;
	}

	/**
	 * Сохраняет данные из кастомных полей формы оплаты в мета полях.
	 */
	public function save_custom_checkout_fields($order_id, $data) {
		if (!isset($data[$this->get_field_name('client_type')])) {
			return;
		}

		$client_type = $data[$this->get_field_name('client_type')];

		// Для того, чтобы старый код работал
		$name_map = [
			'client_type'  => 'custom_question_field',
			'personal'     => 'fiz_lico',
			'entrepreneur' => 'individ_predprin',
			'organization' => 'yur_lico',
			'title'        => 'custom_question_text_p_naimenovanie',
			'inn'          => 'custom_question_text_p_inn',
			'ogrnip'       => 'custom_question_text_ogrnip',
			'ogrn'         => 'custom_question_text_ogrn',
			'agent'        => 'custom_question_text_agent',
			'patronymic'   => 'custom_patronymic',
		];

		$meta = [];

		$meta[$name_map['client_type']] = $name_map[$client_type];

		if ($client_type == 'entrepreneur' || $client_type == 'organization') {
			$meta[$name_map['title']] = $data[$this->get_field_name('billing_title')];
			$meta[$name_map['inn']] = $data[$this->get_field_name('billing_inn')];
		}

		if ($client_type == 'entrepreneur') {
			$meta[$name_map['ogrnip']] = $data[$this->get_field_name('billing_ogrnip')];
		}

		if ($client_type == 'organization') {
			$meta[$name_map['ogrn']] = $data[$this->get_field_name('billing_ogrn')];
		}

		$meta[$name_map['agent']] = $data[$this->get_field_name('billing_agent')];
		$meta[$name_map['patronymic']] = $data[$this->get_field_name('billing_patronymic')];

		foreach ($meta as $field_name => $value) {
			if (!empty($value)) {
				update_post_meta($order_id, $field_name, $value);
			}
		}
	}

	/**
	 * Перенаправляет пользователя на сайт МБС Кристалл после оформления заказа.
	 */
	public function redirect_to_kristall($content) {
		global $wp;

		$param = WC()->session->get('_krl_order_param', null);

		if (empty($param)) {
			$content = '<div class="woocommerce-error" role="alert">'.esc_html__('При перенаправлении возникла ошибка. Пожалуйста, обратитесь к администрации сайта.', 'kristall-integration').'</div>' . $content;
			return $content;
		}

		$options = get_option($this->option_name);

		WC()->session->set('_krl_order_param', null);

		$order_id = $wp->query_vars['order-received'];

		$link = preg_replace("~/+$~", '', $options[$this->field_names['api_url']] ?? $this->default_api_url);
		$link .= "/api/api.php?data=woocommerceOrder&order_id={$order_id}&param={$param}";

		header("Location: {$link}");
	}

	public function get_tinkoff_payment_details($orderId, $status) {
		if (is_admin()) return;

		// Собираем данные о платеже Тинькофф
		try {
			$result = (array)json_decode(file_get_contents('php://input'));
			if (
				isset($result['OrderId']) && $result['OrderId'] == $orderId &&
				isset($result['TerminalKey']) && is_numeric($result['TerminalKey']) &&
				isset($result['PaymentId']) && is_numeric($result['PaymentId']) &&
				isset($result['ErrorCode']) && $result['ErrorCode'] == 0 &&
				isset($result['Token']) && isset($result['Amount']) &&
				isset($result['CardId']) && isset($result['Pan']) && isset($result['ExpDate'])
			) {
				update_post_meta($orderId, 'tinkoff_terminal', $result['TerminalKey']);
				update_post_meta($orderId, 'tinkoff_payment', $result['PaymentId']);		
			}
		} catch (\Throwable $e) {}
	}

	/*============================================================================
	 * Приватные методы
	 ============================================================================*/

	private function get_field_name($name) {
		return Kristall_Integration_Utils::get_field_name($name);
	}

	private function get_prefixed_key($key, $delimiter = null) {
		return Kristall_Integration_Utils::get_prefixed_key($key, $delimiter);
	}

	private function get_allowed_client_types() {
		global $post;
		return Kristall_Integration_Product_Utils::get_allowed_client_types($post);
	}

	private function validate_form_field($errors, $validator, $method, $data, $name, $options = null) {
		$value = $data[$name];
		$result = $validator->{$method}(
			$value,
			$options
		);

		if (!$result->is_valid()) {
			$errors->add($name, $result->get_message());
		}
	}

	private function get_kristall_api_payload($post_data) {
		$get_value = function ($k) use ($post_data) {
			return isset($post_data[$k]) && !empty($post_data[$k]) ? $post_data[$k] : null;
		};

		return [
			'paymentMethod' => $get_value('payment_method'),
			'clientType' => $get_value('kristall-integration_client_type'),
			'promoCode' => $get_value('kristall-integration_billing_agent'),
			'firstName' => $get_value('billing_first_name'),
			'secondName' => $get_value('billing_last_name'),
			'thirdName' => $get_value('kristall-integration_billing_patronymic'),
			'email' => $get_value('billing_email'),
			'phone' => $get_value('billing_phone'),
			'companyName' => $get_value('kristall-integration_billing_title'),
			'inn' => $get_value('kristall-integration_billing_inn'),
			'ogrn' => $get_value('kristall-integration_billing_ogrn'),
			'ogrnip' => $get_value('kristall-integration_billing_ogrnip'),
		];
	}

	private function interpolate_api_errors($error_message) {
		$tmpl_matches = null;
		preg_match_all("~\{\{product_id:\d+\}\}~", $error_message, $tmpl_matches);
		if ($tmpl_matches && count($tmpl_matches[0])) {
			foreach ($tmpl_matches[0] as $tmpl_match) {
				$product_id = substr($tmpl_match, 13, -2);
				try {
					$title = (new WC_Product($product_id))->get_title();
				} catch (Exception $e) {
					$title = esc_html(sprintf(__('Продукт №%s', 'kristall-integration'), $product_id));
				}
				$error_message = str_replace($tmpl_match, htmlspecialchars($title), $error_message);
			}
		}

		return $error_message;
	}

	private function clear_client_info_session($type = null) {
		$client_info = WC()->session->get('_krl_client_info', null);

		if (!empty($client_info)) {
			if (!$type) {
				WC()->session->set('_krl_client_info', null);
			} else if (isset($client_info[$type])) {
				unset($client_info[$type]);
				WC()->session->set('_krl_client_info', $client_info);
			}
		}
	}

	private function get_client_session_info($type, $phone = null, $inn = null, $ogrn = null) {
		$client_info = WC()->session->get('_krl_client_info', null);

		if (
			($type !== 'personal' && $type !== 'organization') ||
			empty($client_info) ||
			!isset($client_info[$type])
		) {
			return null;
		}

		$data = $client_info[$type];

		// Сверяем данные
		if ($type === 'personal' && $phone !== $data->phone) {
			$this->clear_client_info_session('personal');
			return null;
		} else if ($type === 'organization' && $phone !== $data->phone && $inn !== $data->inn && $ogrn !== $data->ogrn) {
			$this->clear_client_info_session('organization');
			return null;
		}

		return $data;
	}
}
