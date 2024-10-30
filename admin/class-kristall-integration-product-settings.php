<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'themes/interface-kristall-integration-theme.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-tab-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'themes/class-kristall-integration-theme-factory.php';

class Kristall_Integration_Product_Settings implements Kristall_Integration_Module {
	private $meta_fields;
	private $plugin_name;

	/**
	 * Текущая тема.
	 *
	 * @var Kristall_Integration_Theme Текущая тема.
	 */
	private $theme;

	public function __construct($plugin_name, $plugin_settings) {
		$this->plugin_name = $plugin_name;
		$this->meta_fields = $plugin_settings['meta_fields'];
		$this->theme = Kristall_Integration_Theme_Factory::get_theme();
	}

	/*============================================================================
   * Регистрация модуля
	 ============================================================================*/

	public function define_hooks($loader) {
		$loader->add_action('admin_enqueue_scripts', $this, 'enqueue_styles');
		$loader->add_action('admin_enqueue_scripts', $this, 'enqueue_scripts');
		$loader->add_action('woocommerce_product_options_general_product_data', $this, 'configure_form_fields');
		$loader->add_action('woocommerce_process_product_meta', $this, 'process_product_meta');
		$loader->add_filter('default_content', $this, 'set_new_product_default_description', 10, 2);

		if ($this->theme->use_tabs()) {
			$loader->add_action('add_meta_boxes', $this, 'create_tabs_meta_box', 1);
			// Удостоверимся, что наш обработчик формы располагается в самом конце очереди
			$loader->add_action('save_post', $this, 'save_tabs_config', 9999999);
		}
	}

	public function enqueue_styles() {
		wp_enqueue_style($this->plugin_name . '-tab-settings', plugin_dir_url(__FILE__) . 'css/kristall-integration-tab-settings.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
		wp_enqueue_style($this->plugin_name . '-product-settings', plugin_dir_url(__FILE__) . 'css/kristall-integration-product-settings.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
	}

	public function enqueue_scripts() {
		wp_enqueue_editor();
		wp_enqueue_script($this->plugin_name . '-sortable', plugin_dir_url(__FILE__) . 'js/sortable.min.js', [], KRISTALL_INTEGRATION_ASSETS_VERSION, false);
		wp_enqueue_script($this->plugin_name . '-tab-settings', plugin_dir_url(__FILE__) . 'js/kristall-integration-tab-settings.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
		wp_enqueue_script($this->plugin_name . '-product-settings', plugin_dir_url(__FILE__) . 'js/kristall-integration-product-settings.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
	}

	/*============================================================================
   * Экшны и фильтры
	 ============================================================================*/

	/**
	 * Устанавливает описание по-умолчанию для новых продуктов.
	 */
	public function set_new_product_default_description($content, $post) {
		if ($content !== '' || $post->post_type !== 'product') {
			return $content;
		}
		return '[kristall_integration_description]';
	}

	/**
	 * Конфигурирует поля формы.
	 */
	public function configure_form_fields() {
		global $post;

		echo '<h4 class="'.esc_attr($this->get_prefixed_key('options_title')).'">'.esc_html__('Кристалл Интеграция', 'kristall-integration')."</h4>";

		//
		$single_in_cart = $this->get_meta_value($post, 'single_in_cart', '0');
		woocommerce_wp_checkbox([
			'id'                => $this->get_field_name('single_in_cart'),
			'label'             => esc_html__('Одиночный продукт', 'kristall-integration'),
			'value'             => $single_in_cart === '1' ? 'yes' : 'no',
			'cbvalue' 					=> 'yes',
			'custom_attributes' => [ 'disabled' => true ],
			'desc_tip'          => true,
			'description'       => esc_html__('Флаг, обозначающий что продукт может быть единственным в корзине. Устанавливаается автоматически при выгрузке товаров.', 'kristall-integration')
		]);

		//
		$sold_individually = $this->get_meta_value($post, 'sold_individually', '0');
		woocommerce_wp_checkbox([
			'id'                => $this->get_field_name('sold_individually'),
			'label'             => __('Единственный экземпляр', 'kristall-integration'),
			'value'             => $sold_individually === '1' ? 'yes' : 'no',
			'cbvalue' 					=> 'yes',
			'custom_attributes' => [ 'disabled' => true ],
			'desc_tip'          => true,
			'description'       => esc_html__('Флаг, обозначающий что продукт продается только в единичном экземпляре. Устанавливаается автоматически при выгрузке товаров.', 'kristall-integration')
		]);

		// Товар или услуга
		woocommerce_wp_radio([
			'id'      => $this->get_field_name('product_type'),
			'label'   => esc_html__('Тип', 'kristall-integration'),
			'options' => [
				'0' => esc_html__('Товар', 'kristall-integration'),
				'1' => esc_html__('Услуга', 'kristall-integration'),
			],
			'value'   => $this->get_meta_value($post, 'product_type', '0')
		]);

		// Страна-производитель
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('manufacturer'),
			'label'       => esc_html__('Страна-производитель', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'manufacturer'),
		]);

		// Номер таможенной декларации
		woocommerce_wp_text_input([
			'id'    => $this->get_field_name('customs_declaration'),
			'label' => esc_html__('Номер таможенной декларации', 'kristall-integration'),
			'value' => $this->get_meta_value($post, 'customs_declaration'),
		]);

		// Единица измерения
		woocommerce_wp_text_input([
			'id'    => $this->get_field_name('unit'),
			'label' => esc_html__('Единица измерения (сокращенно)', 'kristall-integration'),
			'value' => $this->get_meta_value($post, 'unit'),
		]);

		// Количество академических часов
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('duration'),
			'label'       => esc_html__('Количество академических часов', 'kristall-integration'),
			'desc_tip'    => true,
			'type'        => 'number',
			'description' => esc_html__('Вводится простое, целое число', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'duration'),
		]);

		// Вид обучения
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('study_type'),
			'label'       => esc_html__('Вид обучения', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'study_type'),
		]);

		// Форма обучения
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('study_form'),
			'label'       => esc_html__('Форма обучения', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'study_form'),
		]);

		// Доступ к обучению
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('study_access'),
			'label'       => esc_html__('Доступ к обучению', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'study_access'),
		]);

		// Итоговая проверка знаний
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('exam'),
			'label'       => esc_html__('Итоговая проверка знаний', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'exam'),
		]);

		// Итоговый документ
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('document'),
			'label'       => esc_html__('Итоговый документ', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'document'),
		]);

		// Периодичность обучения
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('study_period'),
			'label'       => esc_html__('Периодичность обучения', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'study_period'),
		]);

		// Шорт-код продукта
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('short_code'),
			'label'       => esc_html__('Шорт-код продукта', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Позволяет производить поиск продуктов по этому коду. Вводится свободным текстом', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'short_code'),
		]);

		// Заголовок типа товара
		woocommerce_wp_text_input([
			'id'          => $this->get_field_name('type_title'),
			'label'       => esc_html__('Заголовок типа товара', 'kristall-integration'),
			'desc_tip'    => true,
			'description' => esc_html__('Например, курс профессиональной переподготовки', 'kristall-integration'),
			'value'       => $this->get_meta_value($post, 'type_title'),
		]);
	}

	/**
	 * Добавляет блок для настроек вкладок.
	 */
	public function create_tabs_meta_box() {
		add_meta_box(
			$this->get_prefixed_key('product_tabs'),
			esc_html__('Вкладки на странице продукта', 'kristall-integration'),
			[$this, 'add_content_to_tabs_meta_box'],
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Выводит елементы и контролы в блоке настроек вкладок.
	 */
	public function add_content_to_tabs_meta_box($post) {
		$status = $post->post_status;
		$actual_tabs = [];
		$default_tabs = Kristall_Integration_Tab_Utils::get_default_product_tabs();

		if ($status === 'new' || $status === 'auto-draft') {
			$actual_tabs = Kristall_Integration_Tab_Utils::get_user_default_tabs();

			// Вкладка описания всегда должна присутствовать, но при добавлении нового продукта, если
			// описание не установлено, она отсутствует. Исправляем это.
			foreach ($default_tabs as $default_tab) {
				if ($default_tab['id'] !== $this->get_prefixed_key('default-tab-description', '-')) {
					continue;
				}

				$default_tabs = array_merge(
					[Kristall_Integration_Tab_Utils::get_default_product_description_tab()],
					$default_tabs
				);

				break;
			}
		} else {
			$actual_tabs = $this->theme->get_tabs($post->ID);
		}

		$tabs = array_merge($actual_tabs, $default_tabs);
		Kristall_Integration_Tab_Utils::sort_tabs($tabs);

		/*
		 * $tabs[] = [
		 *   'id' => string,
		 *   'title' => string,
		 *   'priority' => int,
		 *   'content' => string,
		 *   'persistent' => bool,
		 * ]
		*/

		Kristall_Integration_Tab_Utils::print_tab_list_settings($tabs, $actual_tabs, $this->get_field_name('product_tabs_input'));

		foreach ($tabs as $tab) {
			echo '<div class="' . esc_attr($this->get_prefixed_key('product_tab_content', '__')) . '" id="' . esc_attr($tab['id']) . '_content" data-id="' . esc_attr($tab['id']) . '">';
			if ($tab['persistent']) {
				echo '<div style="color: #666;font-size: 16px;padding: 80px 30px;text-align:center;">'.esc_html__('Стандартная вкладка', 'kristall-integration').'<br><small>'.esc_html__('Невозможно изменить содержание стандартной вкладки', 'kristall-integration').'</small></div>';
			} else {
				echo '<textarea style="display:none">' . esc_html($tab['content']) . '</textarea>';
			}
			echo '</div>';
		}
	}

	/**
	 * Сохраняет кастомные мета поля.
	 */
	public function process_product_meta($product_id) {
		$product = wc_get_product($product_id);

		$this->update_meta_data($product, 'product_type', '0');
		$this->update_meta_data($product, 'manufacturer');
		$this->update_meta_data($product, 'customs_declaration');
		$this->update_meta_data($product, 'unit');
		$this->update_meta_data($product, 'duration');
		$this->update_meta_data($product, 'study_type');
		$this->update_meta_data($product, 'study_form');
		$this->update_meta_data($product, 'study_access');
		$this->update_meta_data($product, 'exam');
		$this->update_meta_data($product, 'document');
		$this->update_meta_data($product, 'study_period');
		$this->update_meta_data($product, 'short_code');
		$this->update_meta_data($product, 'type_title');

		$product->save();
	}

	/**
	 * Сохраняет кастомные вкладки.
	 */
	public function save_tabs_config($post_id) {
		if (!$this->theme->use_tabs() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}

		if (!isset($_POST[$this->get_field_name('product_tabs_input')])) {
			return;
		}

		$json_data = stripslashes($_POST[$this->get_field_name('product_tabs_input')]);
		$tabs_data = Kristall_Integration_Tab_Utils::parse_tabs_json($json_data);
		$this->theme->set_tabs($post_id, $tabs_data);
	}

	/*============================================================================
	 * Приватные методы
	 ============================================================================*/

	private function get_prefixed_key($key) {
		return Kristall_Integration_Utils::get_prefixed_key($key);
	}

	private function get_field_name($name) {
		return Kristall_Integration_Utils::get_field_name($name);
	}

	private function get_meta_value($product, $meta_key, $default = '') {
		$meta_value = get_post_meta($product->ID, $this->meta_fields[$meta_key], true);
		return empty($meta_value) ? $default : $meta_value;
	}

	private function update_meta_data($product, $key, $default = '') {
		$data_key = $this->get_field_name($key);
		$meta_key = $this->meta_fields[$key];

		$meta_value = sanitize_text_field($_POST[$data_key] ?? $default);

		$product->update_meta_data($meta_key, $meta_value);
	}
}
