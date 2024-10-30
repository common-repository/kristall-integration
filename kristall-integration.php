<?php
/**
 * Plugin Name: Kristall Integration
 * Description: Быстрое создание собственной витрины Интернет-магазина товаров и услуг в сети Интернет с возможностью выгрузки на неё номенклатуры из МБС Кристалл без дополнительных рутинных действий и ручной правки карточек товара или услуг в Wordpress и Woocommerce. Полностью автоматическое осуществление продаж и регистрация заказов в МБС Кристалл.
 * Plugin URI: https://po365.ru
 * Author: Transtrade
 * Author URI: https://npokristal.ru
 * Version: 3.2.0
 * Requires PHP: 7.4
 * Text Domain: kristall-integration
 */

if (!defined('WPINC')) {
  die;
}

if (!defined('KRISTALL_INTEGRATION_MAIN_FILE')) {
  define('KRISTALL_INTEGRATION_MAIN_FILE', plugin_basename(__FILE__));
}

if (!defined('KRISTALL_INTEGRATION_MAIN_DIR')) {
  define('KRISTALL_INTEGRATION_MAIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('KRISTALL_INTEGRATION_PLUGIN_URL')) {
  define('KRISTALL_INTEGRATION_PLUGIN_URL', plugins_url(null, __FILE__));
}

if (!defined('KRISTALL_INTEGRATION_VERSION')) {
  define('KRISTALL_INTEGRATION_VERSION', '3.2.0');
}

if (!defined('KRISTALL_INTEGRATION_RELEASE')) {
  define('KRISTALL_INTEGRATION_RELEASE', '04.04.2023');
}

if (!defined('KRISTALL_INTEGRATION_ASSETS_VERSION')) {
  define('KRISTALL_INTEGRATION_ASSETS_VERSION', '111');
}

/**
 * Базовый класс
 */
require KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration.php';

/**
 * Подключаем дополнительную функциональность
 */

if (file_exists(KRISTALL_INTEGRATION_MAIN_DIR . 'connector/kristall-integration-connector.php')) {
  require_once KRISTALL_INTEGRATION_MAIN_DIR . 'connector/kristall-integration-connector.php';
}

/**
 * Инициализация
 */
function run_kristall_integration() {
  $plugin = new Kristall_Integration();
  $plugin->run();
}

run_kristall_integration();
