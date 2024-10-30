<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-product-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'themes/class-kristall-integration-theme-factory.php';

class Kristall_Integration_Product implements Kristall_Integration_Module {
	private $meta_fields;
	private $plugin_name;

	public function __construct($plugin_name, $plugin_settings) {
		$this->plugin_name = $plugin_name;
		$this->meta_fields = $plugin_settings['meta_fields'];

		// Инициализируем тему
		Kristall_Integration_Theme_Factory::init_theme();
	}

	/*============================================================================
   * Регистрация модуля
	 ============================================================================*/

	public function define_hooks($loader) {
		$loader->add_action('wp_enqueue_scripts', $this, 'enqueue_styles');
		$loader->add_action('wp_enqueue_scripts', $this, 'enqueue_scripts');
		$loader->add_action('woocommerce_product_meta_end', $this, 'render_custom_meta_data');
		$loader->add_action('woocommerce_share', $this, 'add_partner_link', 100);
	}

	public function enqueue_styles() {
		wp_enqueue_style($this->plugin_name . '-product', plugin_dir_url(__FILE__) . 'css/kristall-integration-product.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
	}

	public function enqueue_scripts() {
		if (!is_admin() && !Kristall_Integration_Utils::is_ajax() && function_exists('is_product') && is_product()) {
			wp_enqueue_script($this->plugin_name . '-qrcode', plugin_dir_url(__FILE__) . 'js/qrcode.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
			wp_enqueue_script($this->plugin_name . '-barcode', plugin_dir_url(__FILE__) . 'js/jsbarcode.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
			wp_enqueue_script($this->plugin_name . '-common', plugin_dir_url(__FILE__) . '../common/js/kristall-integration-common.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
			wp_enqueue_script($this->plugin_name . '-product', plugin_dir_url(__FILE__) . 'js/kristall-integration-product.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
		}
	}

	/*============================================================================
   * Экшны и фильтры
	 ============================================================================*/

	/**
	 * Выводит характеристики продукта.
	 *
	 * @return void
	 */
	public function render_custom_meta_data() {
		global $post;
		$product = wc_get_product($post->ID);
		$product_types = ['0', '1'];

		$this->render_meta_attribute($product, 'product_type', esc_html__('Тип', 'kristall-integration'), function($value) use ($product_types) {
			if (!isset($value) || !in_array($value, $product_types)) {
				return '';
			}
			return $value == '1' ? esc_html__('Услуга', 'kristall-integration') : esc_html__('Товар', 'kristall-integration');
		});

		$this->render_meta_attribute($product, 'manufacturer', esc_html__('Страна-производитель', 'kristall-integration'));
		$this->render_meta_attribute($product, 'customs_declaration', esc_html__('Номер таможенной декларации', 'kristall-integration'));
		$this->render_meta_attribute($product, 'unit', esc_html__('Ед.изм', 'kristall-integration'));
		$this->render_meta_attribute($product, 'duration', esc_html__('Объём программы, ч.', 'kristall-integration'));
		$this->render_meta_attribute($product, 'study_type', esc_html__('Вид обучения', 'kristall-integration'));
		$this->render_meta_attribute($product, 'study_form', esc_html__('Форма обучения', 'kristall-integration'));
		$this->render_meta_attribute($product, 'study_access', esc_html__('Доступ к обучению', 'kristall-integration'));
		$this->render_meta_attribute($product, 'exam', esc_html__('Итоговая проверка знаний', 'kristall-integration'));
		$this->render_meta_attribute($product, 'document', esc_html__('Итоговый документ', 'kristall-integration'));
		$this->render_meta_attribute($product, 'study_period', esc_html__('Периодичность обучения', 'kristall-integration'));

		$allowed_client_types = Kristall_Integration_Product_Utils::get_allowed_client_types($post);
		if (!in_array('personal', $allowed_client_types)) {
			$this->render_only_organizations_warning();
		}
	}

	/**
	 * Добавляет кнопку для получения партнерского кода после блока "Поделиться".
	 *
	 * @return void
	 */
	function add_partner_link() {
		if (is_product()) {
			?>
			<a href="#" target="_blank" rel="noopener noreferrer"
			   class="<?php echo esc_attr($this->get_prefixed_key('partner_link')) ?>"><?php esc_html_e('Партнёрская ссылка', 'kristall-integration') ?></a>
			<?php
		}
	}

	/*============================================================================
	 * Приватные методы
	 ============================================================================*/

	private function get_prefixed_key($key) {
		return Kristall_Integration_Utils::get_prefixed_key($key);
	}

	/**
	 * Выводит значение отдельного атрибута.
	 *
	 * @param mixed  $product  Продукт.
	 * @param string $meta_key Ключ мета поля.
	 * @param string $label    Текст аттрибута.
	 * @param mixed  $cb       Функция для изменения вывода значения атрибута. Принимает значение атрибута и возвращает измененное значение.
	 *
	 * @return void
	 */
	private function render_meta_attribute($product, $meta_key, $label, $cb = null) {
		$meta_value = $product->get_meta($this->meta_fields[$meta_key]);

		if (isset($cb)) {
			$meta_value = $cb($meta_value);
		}

		if (!empty($meta_value)) {
			echo '<div>'.esc_html($label).': <strong>'.esc_html($meta_value).'</strong></div>';
		}
	}

	/**
	 * Ввыводит предупреждение о том, что продукт не допущен к продаже частным лицам.
	 *
	 * @return void
	 */
	private function render_only_organizations_warning() {
		?>
		<div class="<?php echo esc_attr($this->get_prefixed_key('warning')) ?>"><?php esc_html_e('Товар может быть приобретен только юридическим лицом или ИП.', 'kristall-integration') ?>
		</div>
		<?php
	}
}
