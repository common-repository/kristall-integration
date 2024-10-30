<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';

if (!function_exists('is_plugin_active')) {
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

/**
 * Базовый класс плагина.
 */
class Kristall_Integration {
	/**
	 * Загрузчик.
	 *
	 * @var Kristall_Integration_Loader $loader Обслуживает и регистрирует все хуки.
	 */
	protected $loader;

	public function __construct() {
		$this->load_dependencies();
		$this->register_modules();
	}

	/**
	 * Возвращает уникальное имя плагина, которое используется для идентификации.
	 *
	 * @return string Имя плагина.
	 */
	public function get_plugin_name() {
		return Kristall_Integration_Settings::get_plugin_name();
	}

	/**
	 * Возвращает настройки плагина.
	 *
	 * @return array Настройки плагина.
	 */
	public function get_plugin_settings() {
		return Kristall_Integration_Settings::get_plugin_settings();
	}

	/**
	 * Возвращает ссылку на инстанс, который управляет хуками
	 *
	 * @return Kristall_Integration_Loader Управляет хуками.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Запускает хуки.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Загружает зависимости и инициализирует плагин.
	 */
	private function load_dependencies() {
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-kristall-integration-loader.php';
		
		// Админ часть плагина
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-kristall-integration-admin.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-kristall-integration-product-settings.php';

		if (is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
			require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-kristall-integration-yoast-integration.php';
		}

		// Публичная часть плагина
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-kristall-integration-cart.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-kristall-integration-checkout.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-kristall-integration-product.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-kristall-integration-shortcodes.php';

		$this->loader = new Kristall_Integration_Loader();
	}

	/**
	 * Регистрирует модули плагина.
	 */
	private function register_modules() {
		// Админ часть плагина
		$this->register_module('Kristall_Integration_Admin');
		$this->register_module('Kristall_Integration_Product_Settings');

		if (is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php')) {
			$this->register_module('Kristall_Integration_Yoast_Integration');
		}

		// Публичная часть плагина
		$this->register_module('Kristall_Integration_Cart');
		$this->register_module('Kristall_Integration_Checkout');
		$this->register_module('Kristall_Integration_Product');
		$this->register_module('Kristall_Integration_Shortcodes');
	}

	/**
	 * Регистрирует модуль по имени класса.
	 *
	 * @param string $module_name Имя класса модуля.
	 */
	private function register_module(string $module_name) {
		$instance = new $module_name(
			$this->get_plugin_name(),
			$this->get_plugin_settings()
		);
		$instance->define_hooks($this->loader);
		return $instance;
	}
}
