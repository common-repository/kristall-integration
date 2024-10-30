<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-product-utils.php';

class Kristall_Integration_Cart implements Kristall_Integration_Module {
	private $field_names;
	private $option_name;
	private $plugin_name;

	public function __construct($plugin_name, $plugin_settings) {
		$this->plugin_name = $plugin_name;
		$this->option_name = $plugin_settings['option_name'];
		$this->field_names = $plugin_settings['field_names'];
	}

	/*============================================================================
   * Регистрация модуля
	 ============================================================================*/

	public function define_hooks($loader) {
		$loader->add_action('wp_enqueue_scripts', $this, 'enqueue_styles');
		$loader->add_action('wp_enqueue_scripts', $this, 'enqueue_scripts');
		$loader->add_filter('woocommerce_coupons_enabled', $this, 'disable_coupon_field');
		$loader->add_action('woocommerce_after_cart_item_name', $this, 'print_product_id_and_errors');
		$loader->add_action('woocommerce_after_cart_table', $this, 'insert_cart_message');
	}

	public function enqueue_styles() {
		wp_enqueue_style($this->plugin_name . '-cart', plugin_dir_url(__FILE__) . 'css/kristall-integration-cart.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
	}

	public function enqueue_scripts() {
		wp_enqueue_script($this->plugin_name . '-cart', plugin_dir_url(__FILE__) . 'js/kristall-integration-cart.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
	}

	/*============================================================================
   * Экшны и фильтры
	 ============================================================================*/

	/**
	 * Отключает поле промо-кода на странице корзины.
	 */
	public function disable_coupon_field($enabled) {
		if (is_cart()) {
			$enabled = false;
		}

		return $enabled;
	}

	/**
	 * Выводит текст на странице корзины.
	 */
	public function insert_cart_message() {
		$option_name = $this->field_names['cart_message_id'];
		$options = get_option($this->option_name);

		if (!is_array($options) || empty($options[$option_name])) {
			return;
		}

		$post_content = get_post_field('post_content', $options[$option_name]);
		if (!empty($post_content)) {
			echo do_shortcode($post_content);
		}
	}

	/**
	 * Выводит ID продуктов для элементов в корзине и ошибки, если есть.
	 */
	public function print_product_id_and_errors($cart_item) {
		$error_attr = '';
		$sold_individually_attr = '';
		$single_in_cart_attr = '';

		if (Kristall_Integration_Product_Utils::is_sold_individually($cart_item['data'])) {
			$sold_individually_attr = ' data-sold-individually="true"';

			if ($cart_item['quantity'] > 1) {
				$error_attr = ' data-error="sold_individually"';
				echo '<div class="kristall_integration__cart_meta_error">'.esc_html__('Данный продукт продается только в единственном экземпляре.', 'kristall-integration').'</div>';
			}
		}

		if (Kristall_Integration_Product_Utils::is_single_in_cart($cart_item['data'])) {
			$single_in_cart_attr = ' data-single-in-cart="true"';

			if (error_attr != '' && count(Kristall_Integration_Product_Utils::get_cart()) > 1) {
				$error_attr = ' data-error="single_in_cart"';
				echo '<div class="kristall_integration__cart_meta_error">'.esc_html__('Данный продукт должен быть едиственным в корзине.', 'kristall-integration').'</div>';
			}
		}

		echo '<div class="kristall_integration__cart_prod_id" style="display:none" data-id="' . esc_attr($cart_item['data']->get_id()) . '"' . $sold_individually_attr . $single_in_cart_attr . $error_attr . '></div>';
	}
}
