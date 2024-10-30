<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php';

class Kristall_Integration_Utils {
	/**
	 * Добавляет к исходному ключу префикс с именем плагина.
	 *
	 * @param string $key       Исходный ключ
	 * @param string $delimiter Разделитель
	 *
	 * @return string
	 */
	public static function get_prefixed_key($key, $delimiter = '__') {
		return Kristall_Integration_Settings::get_plugin_name() . $delimiter . $key;
	}

	/**
	 * Добавляет к исходному имени поля префикс с именем плагина.
	 *
	 * @param string $key Исходное имя поля
	 *
	 * @return string
	 */
	public static function get_field_name($key) {
		return self::get_prefixed_key($key, '_');
	}

	/**
	 * Возвращает путь к API плагина.
	 *
	 * @return string
	 */
	public static function get_api_namespace() {
		return Kristall_Integration_Settings::get_plugin_name() . '/v' . Kristall_Integration_Settings::get_api_version();
	}

	/**
	 * Возвращает текущий токен
	 * 
	 * @return string
	 */
	public static function create_rest_token() {
		if (
			!class_exists('woocommerce') ||
			!function_exists('WC') ||
			!isset(WC()->session)
		) return '';

		try {
			if (!WC()->session->has_session()) {
				WC()->session->set_customer_session_cookie(true);
			}
	
			$token = WC()->session->get('_krl_rest_token', null);
			if (empty($token)) {
				$token = sprintf(
					'%04x%04x%04x',
					mt_rand(0, 0xffff),
					mt_rand(0, 0xffff),
					mt_rand(0, 0xffff)
				);
				WC()->session->set('_krl_rest_token', $token);
			}
		} catch (Exception $e) {
			$token = '';
		}

		return $token;
	}

	/**
	 * Проверяет токен на соотвествие
	 * 
	 * @param string $token Переданный токен
	 *
	 * @return bool
	 */
	public static function verify_rest_token($token) {
		if (!function_exists('WC') || !isset(WC()->session)) return false;

		try {
			if (!WC()->session->has_session()) {
				WC()->session->set_customer_session_cookie(true);
			}
	
			$session_token = WC()->session->get('_krl_rest_token', null);
	
			$result = !empty($session_token) && $session_token === $token;	
		} catch (Exception $e) {
			$result = false;
		}

		return $result;
	}

	public static function is_ajax() {
		return defined('XMLRPC_REQUEST') ||
			defined('REST_REQUEST') ||
			(defined('WP_INSTALLING') && WP_INSTALLING) ||
			wp_doing_ajax() ||
			wp_is_json_request();
	}
}
