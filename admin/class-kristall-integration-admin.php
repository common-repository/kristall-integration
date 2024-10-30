<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-validator.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-tab-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-api.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'admin/class-kristall-integration-admin-renderer.php';

class Kristall_Integration_Admin implements Kristall_Integration_Module {
	/**
	 * API адрес по-умолчанию.
	 *
	 * @var string $default_api_url API адрес по-умолчанию.
	 */
	private $default_api_url;

	private $field_names;
	private $option_group;
	private $option_name;

	/**
	 * ID страницы.
	 *
	 * @var string $page_id ID страницы.
	 */
	private $page_id = 'kristall_integration_settings_page';

	private $plugin_name;
	private $release;

	/**
	 * Рендерер.
	 * @var Kristall_Integration_Admin_Renderer $renderer Рендерер.
	 */
	private $renderer;

	private $version;

	public function __construct($plugin_name, $plugin_settings) {
		$this->plugin_name = $plugin_name;
		$this->version = $plugin_settings['version'];
		$this->release = $plugin_settings['release'];
		$this->default_api_url = $plugin_settings['default_api_url'];
		$this->option_group = $plugin_settings['option_group'];
		$this->option_name = $plugin_settings['option_name'];
		$this->field_names = $plugin_settings['field_names'];
		$this->renderer = new Kristall_Integration_Admin_Renderer();
	}

	/*============================================================================
   * Регистрация модуля
	 ============================================================================*/

	public function define_hooks($loader) {
		$loader->add_action('admin_enqueue_scripts', $this, 'enqueue_styles');
		$loader->add_action('admin_enqueue_scripts', $this, 'enqueue_scripts');
		if (defined('KRISTALL_INTEGRATION_CONNECTOR_ENABLED')) {
			add_action('admin_enqueue_scripts', 'krl_extended_functionality_enqueue_assets');
		}
		$loader->add_action('admin_menu', $this, 'add_admin_menu_link', 9);
		$loader->add_filter('plugin_action_links', $this, 'add_plugin_settings_link', 10, 2);
		$loader->add_action('admin_init', $this, 'register_and_build_fields');
		$loader->add_action('admin_head', $this, 'register_tinymce_plugin_and_buttons');
	}

	/**
   * Регистрирует стили.
   *
   * @return void
   */
	public function enqueue_styles() {
		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/kristall-integration-admin.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
		wp_enqueue_style($this->plugin_name . '-spectrum', plugin_dir_url(__FILE__) . 'css/spectrum.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
		wp_enqueue_style($this->plugin_name . '-common', plugin_dir_url(__FILE__) . '../common/css/kristall-integration-common.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
	}

	/**
   * Регистрирует JavaScript скрипты.
   *
   * @return void
   */
	public function enqueue_scripts() {
		wp_enqueue_script($this->plugin_name . '-sortable', plugin_dir_url(__FILE__) . 'js/sortable.min.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, false);
		wp_enqueue_script($this->plugin_name . '-tab-settings', plugin_dir_url(__FILE__) . 'js/kristall-integration-tab-settings.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
		wp_enqueue_script($this->plugin_name . '-spectrum', plugin_dir_url(__FILE__) . 'js/spectrum.min.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/kristall-integration-admin.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);
		wp_enqueue_script($this->plugin_name . '-common', plugin_dir_url(__FILE__) . '../common/js/kristall-integration-common.js?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, ['jquery'], false, false);

		wp_localize_script($this->plugin_name, 'kristallIntegrationConfig', [
			'pluginUrl' => esc_url_raw(plugin_dir_url(__FILE__)),
		]);
	}

	/*============================================================================
   * Экшны и фильтры
	 ============================================================================*/

	/**
	 * Добавляет ссылку в меню.
	 */
	public function add_admin_menu_link() {
		add_menu_page(
			esc_html(sprintf(__('Плагин интеграции с МБС «Кристалл». Релиз v.%1$s от %2$s', 'kristall-integration'), $this->version, $this->release)),
			esc_html__('Кристалл Интеграция', 'kristall-integration'),
			'manage_options',
			$this->plugin_name,
			[$this, 'display_settings_page'],
			$this->get_logo(),
		// 26
		);
	}

	/**
	 * Добавляет ссылку на страницу настроек в списке плагинов.
	 */
	public function add_plugin_settings_link($links_array, $file) {
		if (defined('KRISTALL_INTEGRATION_MAIN_FILE') && $file === KRISTALL_INTEGRATION_MAIN_FILE) {
			$url = admin_url('admin.php?page=' . $this->plugin_name);
			$settings_link = '<a href="' . esc_url_raw($url) . '">' . esc_html__('Настройки', 'kristall-integration') . '</a>';
			return [$settings_link] + $links_array;
		}
		return $links_array;
	}

	/**
	 * Регистрирует и выводит поля ввода и элементы управления.
	 */
	public function register_and_build_fields() {
		// Основные настройки

		add_settings_section(
			$this->get_prefixed_key('general_settings'),
			esc_html__('Основные настройки', 'kristall-integration'),
			null,
			$this->page_id
		);

		add_settings_field(
			$this->field_names['api_url'],
			esc_html__('URL-aдрес МБС Кристалл', 'kristall-integration'),
			[$this, 'render_api_url_field'],
			$this->page_id,
			$this->get_prefixed_key('general_settings')
		);

		add_settings_field(
			$this->field_names['api_key'],
			esc_html__('API-ключ', 'kristall-integration'),
			[$this, 'render_api_key_field'],
			$this->page_id,
			$this->get_prefixed_key('general_settings')
		);

		// Запрет на покупку

		add_settings_section(
			$this->get_prefixed_key('restriction_settings'),
			esc_html__('Запрет на покупку', 'kristall-integration'),
			null,
			$this->page_id
		);

		add_settings_field(
			$this->field_names['denied_categories_p'],
			esc_html__('Физическим лицам', 'kristall-integration'),
			[$this, 'render_denied_categories_p_field'],
			$this->page_id,
			$this->get_prefixed_key('restriction_settings')
		);

		// Настройки корзины

		add_settings_section(
			$this->get_prefixed_key('cart_settings'),
			esc_html__('Настройки корзины', 'kristall-integration'),
			null,
			$this->page_id
		);

		add_settings_field(
			$this->field_names['cart_message_id'],
			esc_html__('ID страницы для вывода в корзине', 'kristall-integration'),
			[$this, 'render_cart_message_id_field'],
			$this->page_id,
			$this->get_prefixed_key('cart_settings')
		);

		add_settings_field(
			$this->field_names['offer_message_id'],
			esc_html__('ID страницы для флажка оферты', 'kristall-integration'),
			[$this, 'render_offer_message_id_field'],
			$this->page_id,
			$this->get_prefixed_key('cart_settings')
		);

		add_settings_field(
			$this->field_names['offer_error_message'],
			esc_html__('Сообщение об ошибке оферты', 'kristall-integration'),
			[$this, 'render_offer_error_message_field'],
			$this->page_id,
			$this->get_prefixed_key('cart_settings')
		);

		// Вкладки и шорт-коды

		add_settings_section(
			$this->get_prefixed_key('tabs_settings'),
			esc_html__('Вкладки и шорт-коды', 'kristall-integration'),
			null,
			$this->page_id
		);

		add_settings_field(
			$this->field_names['default_tabs'],
			null,
			[$this, 'render_default_tabs_field'],
			$this->page_id,
			$this->get_prefixed_key('tabs_settings'),
			['class' => $this->get_prefixed_key('default_tabs-controls')]
		);

		add_settings_section(
			$this->get_prefixed_key('shortcode_ids_controls'),
			'<a href="#" class="' . esc_attr($this->get_prefixed_key('shortcode_ids_toggle')) . '">'.esc_html__('ID шаблонов для шорт-кодов', 'kristall-integration').'</a>',
			null,
			$this->page_id
		);

		add_settings_field(
			$this->field_names['shortcode_id_description'],
			esc_html__('ID шаблона «Описание продукта (товар)»', 'kristall-integration'),
			[$this, 'render_shortcode_id_description_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_service_description'],
			esc_html__('ID шаблона «Описание продукта (услуга)»', 'kristall-integration'),
			[$this, 'render_shortcode_id_service_description_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_learnplan'],
			esc_html__('ID шаблона «Учебный план»', 'kristall-integration'),
			[$this, 'render_shortcode_id_learnplan_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_requirements'],
			esc_html__('ID шаблона «Требования (документы)»', 'kristall-integration'),
			[$this, 'render_shortcode_id_requirements_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_discount'],
			esc_html__('ID шаблона «Рассрочка и скидки»', 'kristall-integration'),
			[$this, 'render_shortcode_id_discount_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_fines'],
			esc_html__('ID шаблона «Штрафы»', 'kristall-integration'),
			[$this, 'render_shortcode_id_fines_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_document'],
			esc_html__('ID шаблона «Итоговый документ»', 'kristall-integration'),
			[$this, 'render_shortcode_id_document_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_faq'],
			esc_html__('ID шаблона «Вопрос/ответ»', 'kristall-integration'),
			[$this, 'render_shortcode_id_faq_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		add_settings_field(
			$this->field_names['shortcode_id_howtobuy'],
			esc_html__('ID шаблона «Как купить»', 'kristall-integration'),
			[$this, 'render_shortcode_id_howtobuy_field'],
			$this->page_id,
			$this->get_prefixed_key('shortcode_ids_controls'),
			['class' => $this->get_prefixed_key('shortcode_ids_controls_field')]
		);

		// Настройки цветов

		add_settings_section(
			$this->get_prefixed_key('color_settings'),
			esc_html__('Настройки кнопок', 'kristall-integration'),
			[$this, 'render_preview_block'],
			$this->page_id
		);

		add_settings_field(
			$this->field_names['btn_bg'],
			esc_html__('Фон кнопок', 'kristall-integration'),
			[$this, 'render_btn_bg_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_text_color'],
			esc_html__('Цвет текста', 'kristall-integration'),
			[$this, 'render_btn_text_color_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_border_color'],
			esc_html__('Цвет границы', 'kristall-integration'),
			[$this, 'render_btn_border_color_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_active_bg'],
			esc_html__('Фон активной кнопки', 'kristall-integration'),
			[$this, 'render_btn_active_bg_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_active_text_color'],
			esc_html__('Цвет активного текста', 'kristall-integration'),
			[$this, 'render_btn_active_text_color_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_active_border_color'],
			esc_html__('Цвет границы активной кнопки', 'kristall-integration'),
			[$this, 'render_btn_active_border_color_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_active_shadow_color'],
			esc_html__('Тень активной кнопки', 'kristall-integration'),
			[$this, 'render_btn_active_shadow_color_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_hover_bg'],
			esc_html__('Фон кнопок при наведении', 'kristall-integration'),
			[$this, 'render_btn_hover_bg_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_hover_text_color'],
			esc_html__('Цвет текста при наведении', 'kristall-integration'),
			[$this, 'render_btn_hover_text_color_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		add_settings_field(
			$this->field_names['btn_hover_border_color'],
			esc_html__('Цвет границы при наведении', 'kristall-integration'),
			[$this, 'render_btn_hover_border_color_field'],
			$this->page_id,
			$this->get_prefixed_key('color_settings')
		);

		if (defined('KRISTALL_INTEGRATION_CONNECTOR_ENABLED')) {
			admin_add_extended_controls($this->page_id);
		}

		register_setting($this->option_group, $this->option_name, [$this, 'validate_and_sanitize_input']);
	}

	/*============================================================================
   * Страница настроек плагина и ее поля
	 ============================================================================*/

	/**
	 * Страница настроек
	 */
	public function display_settings_page() {
		?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title() ?></h2>
			<?php settings_errors($this->option_group); ?>

			<form action="options.php" method="POST">
				<?php
				settings_fields($this->option_group);
				do_settings_sections($this->page_id);
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Выводит поле "Основные настройки -> API-ключ"
	 */
	public function render_api_key_field() {
		$this->renderer->render_text_field(
			$this->field_names['api_key'],
			'',
			esc_html__('Укажите Ваш API-ключ. Он находится в настройках личного кабинета Кристалла.', 'kristall-integration'),
			'krl_api_key_help'
		);
	}

	/**
	 * Выводит поле "Основные настройки -> URL-aдрес МБС Кристалл"
	 */
	public function render_api_url_field() {
		$this->renderer->render_text_field(
			$this->field_names['api_url'],
			$this->default_api_url,
			esc_html__('Введите URL-aдрес МБС Кристалл, должен начинаться с http(s). Например, https://example.com', 'kristall-integration'),
			'krl_api_url_help'
		);
	}

	/**
	 * Выводит поле "Запрет на покупку -> Физическим лицам"
	 */
	public function render_denied_categories_p_field() {
		$this->renderer->render_text_field(
			$this->field_names['denied_categories_p'],
			'',
			esc_html__('Перечислите через запятую ID категорий товаров, не допускаемых к продаже частным лицам.', 'kristall-integration')
		);
	}

	/**
	 * Выводит поле "Настройки корзины -> ID страницы для вывода в корзине"
	 */
	public function render_cart_message_id_field() {
		$this->renderer->render_text_field(
			$this->field_names['cart_message_id'],
			'',
			esc_html__('Введите ID страницы, содержание которой будет выведено под списком товаров в корзине. Оставьте пустым, если не хотите выводить какой-либо текст.', 'kristall-integration')
		);
	}

	/**
	 * Выводит поле "Настройки корзины -> ID страницы для вывода в корзине"
	 */
	public function render_offer_message_id_field() {
		$this->renderer->render_text_field(
			$this->field_names['offer_message_id'],
			'',
			esc_html__('Введите ID страницы, содержание которой будет выведено в качестве текста для флажка оферты. Оставьте пустым, если не хотите выводить флажок оферты.', 'kristall-integration')
		);
	}

	/**
	 * Выводит поле "Настройки корзины -> ID страницы для вывода в корзине"
	 */
	public function render_offer_error_message_field() {
		$this->renderer->render_text_field(
			$this->field_names['offer_error_message'],
			esc_html__('Для продолжения необходимо принять условия оферты.', 'kristall-integration'),
			esc_html__('Введите сообщение об ошибке, которое будет выводиться если пользователь не отметил флажок оферты.', 'kristall-integration')
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> Вкладки"
	 */
	public function render_default_tabs_field() {
		$user_tabs = Kristall_Integration_Tab_Utils::get_user_default_tabs();
		$default_tabs = Kristall_Integration_Tab_Utils::get_default_product_tabs(true);
		$tabs = array_merge($user_tabs, $default_tabs);

		Kristall_Integration_Tab_Utils::sort_tabs($tabs);
		Kristall_Integration_Tab_Utils::print_tab_list_settings($tabs, $user_tabs, "{$this->option_name}[{$this->field_names['default_tabs']}]");
		echo '<div class="description" style="margin-top: 4px">' . esc_html__('Настройте стандартный набор вкладок, которые будут отображаться по-умолчанию при добавлении нового продукта.', 'kristall-integration') . '</div>';
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Описание продукта (товар)»"
	 */
	public function render_shortcode_id_description_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_description'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Описание продукта (услуга)»"
	 */
	public function render_shortcode_id_service_description_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_service_description'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Учебный план»"
	 */
	public function render_shortcode_id_learnplan_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_learnplan'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Требования (документы)»"
	 */
	public function render_shortcode_id_requirements_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_requirements'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Рассрочка и скидки»"
	 */
	public function render_shortcode_id_discount_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_discount'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Штрафы»"
	 */
	public function render_shortcode_id_fines_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_fines'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Итоговый документ»"
	 */
	public function render_shortcode_id_document_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_document'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Вопрос/ответ»"
	 */
	public function render_shortcode_id_faq_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_faq'],
			''
		);
	}

	/**
	 * Выводит поле "Вкладки и шорт-коды -> ID шаблона «Как купить»"
	 */
	public function render_shortcode_id_howtobuy_field() {
		$this->renderer->render_text_field(
			$this->field_names['shortcode_id_howtobuy'],
			''
		);
	}

	/**
	 * Выводит превью для секции "Настройки кнопок"
	 */
	public function render_preview_block() {
		$this->renderer->render_buttons_preview_block();
	}

	/**
	 * Выводит поле "Настройки кнопок -> Фон кнопок"
	 */
	public function render_btn_bg_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_bg'],
			'#fff'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Цвет текста"
	 */
	public function render_btn_text_color_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_text_color'],
			'#424242'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Цвет границы"
	 */
	public function render_btn_border_color_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_border_color'],
			'#ddd'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Фон активной кнопки"
	 */
	public function render_btn_active_bg_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_active_bg'],
			'#1e88e5'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Цвет активного текста"
	 */
	public function render_btn_active_text_color_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_active_text_color'],
			'#fff'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Цвет границы активной кнопки"
	 */
	public function render_btn_active_border_color_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_active_border_color'],
			'rgba(0, 0, 0, 0)'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Тень активной кнопки"
	 */
	public function render_btn_active_shadow_color_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_active_shadow_color'],
			'rgba(102, 179, 251, 0.5)'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Фон кнопок при наведении"
	 */
	public function render_btn_hover_bg_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_hover_bg'],
			'#1d2127'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Цвет текста при наведении"
	 */
	public function render_btn_hover_text_color_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_hover_text_color'],
			'#fff'
		);
	}

	/**
	 * Выводит поле "Настройки кнопок -> Цвет границы при наведении"
	 */
	public function render_btn_hover_border_color_field() {
		$this->renderer->render_color_field(
			$this->field_names['btn_hover_border_color'],
			'rgba(0, 0, 0, 0)'
		);
	}

	/**
	 * Производит очистку и валидацию данных.
	 */
	public function validate_and_sanitize_input($options) {
		$validator = new Kristall_Integration_Validator([
			'required' => esc_html__('Поле "%s" обязательно для заполнения. Значение поля сброшено на значение по-умолчанию.', 'kristall-integration'),
			'int'      => esc_html__('Поле "%s" должно содержать целое число. Значение поля сброшено на значение по-умолчанию.', 'kristall-integration'),
			'url'      => esc_html__('Поле "%s" должно содержать URL-адрес. Значение поля сброшено на значение по-умолчанию.', 'kristall-integration'),
			'color'    => esc_html__('Поле "%s" должно содержать корректное значение цвета, например #fff, #a2c4c9, rgb(100, 170, 250), rgba(100, 170, 250, 0.3). Значение поля сброшено на значение по-умолчанию.', 'kristall-integration'),
		]);

		$api_key_valid = $this->validate_option(
			$validator,
			'string',
			$options,
			'api_key',
			'',
			[
				'required' => true,
				'label' => esc_html__('API-ключ', 'kristall-integration')
			]
		);

		$api_url_valid = $this->validate_option(
			$validator,
			'url',
			$options,
			'api_url',
			$this->default_api_url,
			['label' => esc_html__('URL-aдрес МБС Кристалл', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'id_string_list',
			$options,
			'denied_categories_p',
			$options[$this->field_names['denied_categories_p']], // Не сбразываем значение поля
			[
				'required' => false,
				'label'    => esc_html__('Физическим лицам', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'cart_message_id',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID страницы для флажка оферты', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'offer_message_id',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID страницы для вывода в корзине', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'string',
			$options,
			'offer_error_message',
			esc_html__('Для продолжения необходимо принять условия оферты.', 'kristall-integration'),
			[
				'required' => !empty($options[$this->field_names['offer_message_id']]),
				'label'    => esc_html__('Сообщение об ошибке оферты', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_description',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Описание продукта (товар)»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_service_description',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Описание продукта (услуга)»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_learnplan',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Учебный план»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_requirements',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Требования (документы)»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_discount',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Рассрочка и скидки»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_fines',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Штрафы»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_document',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Итоговый документ»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_faq',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Вопрос/ответ»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'int',
			$options,
			'shortcode_id_howtobuy',
			'',
			[
				'required' => false,
				'label'    => esc_html__('ID шаблона «Как купить»', 'kristall-integration'),
			]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_bg',
			'#fff',
			['label' => esc_html__('Фон кнопок', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_text_color',
			'#424242',
			['label' => esc_html__('Цвет текста', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_border_color',
			'#ddd',
			['label' => esc_html__('Цвет границы', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_active_bg',
			'#1e88e5',
			['label' => esc_html__('Фон активной кнопки', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_active_text_color',
			'#fff',
			['label' => esc_html__('Цвет активного текста', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_active_border_color',
			'rgba(102, 179, 251, 0.5)',
			['label' => esc_html__('Цвет границы активной кнопки', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_active_shadow_color',
			'rgba(102, 179, 251, 0.5)',
			['label' => esc_html__('Тень активной кнопки', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_hover_bg',
			'#1d2127',
			['label' => esc_html__('Фон кнопок при наведении', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_hover_text_color',
			'#fff',
			['label' => esc_html__('Цвет текста при наведении', 'kristall-integration')]
		);

		$this->validate_option(
			$validator,
			'color',
			$options,
			'btn_hover_border_color',
			'#fff',
			['label' => esc_html__('Цвет границы при наведении', 'kristall-integration')]
		);

		// Обрабатываем табы
		$options[$this->field_names['default_tabs']] = $this->get_default_tabs_options($options);

		// Проверяем подключение к API
		if ($api_key_valid && $api_url_valid) {
			$api = new Kristall_Integration_API(
				$options[$this->field_names['api_url']],
				$options[$this->field_names['api_key']]
			);

			$api_result = $api->check_connection();
			if ($api_result->status == 'error') {
				if ($api_result->getCode() == 403) {
					add_settings_error($this->option_group, $this->option_name, '[403] ' . esc_html__('Произошла ошибка доступа при проверке подключения к API. Проверьте Ваш API-ключ.', 'kristall-integration'));
				} else {
					add_settings_error($this->option_group, $this->option_name, $api_result->getMessage(true));
				}
			}

			if (!isset($api_result->data) || !isset($api_result->data->params) || !isset($api_result->data->params->api_key) || empty($api_result->data->params->api_key)) {
				add_settings_error($this->option_group, $this->option_name, '[403] ' . esc_html__('Произошла ошибка при подключении к API. Проверьте правильность ввода URL-адреса МБС Кристалл.', 'kristall-integration'));
			}
		}

		return $options;
	}

	/*============================================================================
	 * Регистрация JavaScript плагина для TinyMCE
	 ============================================================================*/

	public function register_tinymce_plugin_and_buttons() {
		if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
			return;
		}
		if (get_user_option('rich_editing') == 'true') {
			add_filter('mce_external_plugins', [$this, 'add_tinymce_plugin']);
			add_filter('mce_buttons', [$this, 'register_tinymce_button']);
		}
	}

	public function add_tinymce_plugin($plugin_array) {
		$plugin_array[$this->get_prefixed_key('tinymce-shortcodes', '-')] = plugin_dir_url(__FILE__) . 'js/kristall-integration-tinymce.js';
		return $plugin_array;
	}

	public function register_tinymce_button($buttons) {
		$buttons[] = $this->get_prefixed_key('tinymce-shortcodes', '-');
		return $buttons;
	}

	/*============================================================================
   * Вспомогательные методы
	 ============================================================================*/

	private function get_prefixed_key($key, $delimiter = '_') {
		return Kristall_Integration_Utils::get_prefixed_key($key, $delimiter);
	}

	private function get_logo() {
		$img = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48cGF0aCBmaWxsPSIjMDg3MzgwIiBkPSJNMzc4LjcgMzJIMTMzLjNMMjU2IDE4Mi43IDM3OC43IDMyek01MTIgMTkyIDQwNC42IDUwLjcgMjg5LjYgMTkySDUxMnpNMTA3LjQgNTAuNjcgMCAxOTJoMjIyLjRsLTExNS0xNDEuMzN6TTI0NC4zIDQ3NC45YzMgMy4zIDcuMyA1LjEgMTEuNyA1LjFzOC42NTMtMS44MjggMTEuNjctNS4wNjJMNTEwLjYgMjI0SDEuMzY1TDI0NC4zIDQ3NC45eiIvPjwvc3ZnPg==';
		return 'data:image/svg+xml;base64,' . $img;
	}

	private function validate_option($validator, $method, &$options, $field_name, $default, $v_options = null) {
		$option_key = $this->field_names[$field_name];

		$result = $validator->{$method}(
			$options[$option_key],
			$v_options
		);

		if (!$result->is_valid()) {
			$options[$option_key] = $default;
			add_settings_error($this->option_group, $this->option_name, $result->get_message());
			return false;
		}

		$options[$option_key] = $result->get_value();

		return true;
	}

	private function get_default_tabs_options($options) {
		$key = $this->field_names['default_tabs'];
		if (!is_string($options[$key])) {
			return [];
		}
		return Kristall_Integration_Tab_Utils::parse_tabs_json($options[$key], false);
	}
}
