<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php';

class Kristall_Integration_Product_Utils {
	protected static $cart_cached = null;

	/**
	 * Возвращает массив с типами клиентов, которым разрешена продажа продукта.
	 *
	 * @param mixed $product Пост/продукт, для которого нужно получить типы клиентов.
	 *
	 * @return string[]
	 */
	public static function get_allowed_client_types($product) {
		$allowed_types = [
			'personal',
			'entrepreneur',
			'organization',
		];

		$settings = Kristall_Integration_Settings::get_plugin_settings();
		$options = get_option($settings['option_name']);
		$field_names = $settings['field_names'];

		if (!isset($options) || !isset($product)) {
			return $allowed_types;
		}

		$denied_categories_str = preg_replace(
			"/[^\d,]/",
			'',
			$options[$field_names['denied_categories_p']] ?? ''
		);

		if (empty($denied_categories_str)) {
			return $allowed_types;
		}

		$denied_categories = explode(',', $denied_categories_str);

		if (count($denied_categories) == 0) {
			return $allowed_types;
		}

		$restriction_found = false;
		$terms = get_the_terms($product->ID, 'product_cat');
		$terms = is_array($terms) ? $terms : [];

		foreach ($terms as $term) {
			if (in_array((string)$term->term_id, $denied_categories)) {
				$restriction_found = true;
				break;
			}
		}

		if ($restriction_found) {
			$index = array_search('personal', $allowed_types);
			unset($allowed_types[$index]);
		}

		return $allowed_types;
	}

	/**
	 * Возвращает именованный массив [тип клиента => текст, ...]
	 *
	 * @param array $allowed_client_types Разрешенные типы клиентов для покупки продукта
	 *
	 * @return array
	 */
	public static function get_client_type_options($allowed_client_types) {
		$labels = [
			'personal'     => esc_html__('Физическое лицо', 'kristall-integration'),
			'organization' => esc_html__('Юридическое лицо / ИП', 'kristall-integration'),
		];

		$client_type_options = [];
		foreach ($labels as $client_type => $label) {
			if (in_array($client_type, $allowed_client_types)) {
				$client_type_options[$client_type] = $label;
			}
		}

		return $client_type_options;
	}

	/**
	 * Возвращает объект корзины.
	 */
	public static function get_cart() {
		if (is_null(self::$cart_cached)) {
			self::$cart_cached = WC()->cart->get_cart();
		}

		return self::$cart_cached;
	}

	/**
	 * Возвращает возвращает объект продукта в корзине или null.
	 */
	public static function get_cart_item($product) {
		$cart = self::get_cart();
		$product_id = $product->get_id();

		foreach($cart as $cart_item) {
      $product_in_cart_id = $cart_item['data']->get_id();
      if ($product_in_cart_id === $product_id) return $cart_item;
    }

		return null;
	}

	/**
	 * Возвращает true если продукт должен быть единственным в корзине, иначе false
	 */
	public static function is_single_in_cart($product) {
		// $meta_fields = Kristall_Integration_Settings::get_plugin_settings()['meta_fields'];
		// return $product->get_meta($meta_fields['single_in_cart']) === '1';
		return false;
	}

	/**
	 * Возвращает true если продукт должен продаваться в единственном экземпляре, иначе false
	 */
	public static function is_sold_individually($product) {
		$meta_fields = Kristall_Integration_Settings::get_plugin_settings()['meta_fields'];
		$sku_part = substr($product->get_sku(), 0, 3);
		return ($sku_part == 'edu' || $sku_part == 'prd' || $sku_part == 'usl') && $product->get_meta($meta_fields['sold_individually']) === '1';
	}

	/**
	 * Возвращает true если продукт в корзине, иначе false
	 */
	public static function in_cart($product) {
		return !is_null(self::get_cart_item($product));
	}
}
