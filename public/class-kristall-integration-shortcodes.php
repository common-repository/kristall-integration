<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/interface-kristall-integration-module.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';

class Kristall_Integration_Shortcodes implements Kristall_Integration_Module {
	private $option_name;

	/**
	 * Префикс для шорткодов.
	 *
	 * @var string $prefix Префикс для шорткодов.
	 */
	private $prefix;

	private $ajax_enabled;

	/**
	 * Массив с доступными шорткодами.
	 *
	 * @var string[] $tab_shortcodes Массив с доступными шорткодами.
	 */
	private $tab_shortcodes = [
		'description',
		'service_description',
		'learnplan',
		'requirements',
		'discount',
		'fines',
		'document',
		'faq',
		'howtobuy',
		'page_link_by_id'
	];

	public function __construct($plugin_name, $plugin_settings) {
		$this->prefix = str_replace('-', '_', $plugin_name);
		$this->option_name = $plugin_settings['option_name'];
		$this->ajax_enabled = 'yes' === get_option('woocommerce_enable_ajax_add_to_cart');
	}

	/*============================================================================
	 * Регистрация шорткодов
	 ============================================================================*/

	public function define_hooks($loader) {
		$loader->add_action('rest_api_init', $this, 'register_api_routes');

		foreach ($this->tab_shortcodes as $shortcode) {
			add_shortcode($this->prefix . '_' . $shortcode, [$this, 'do_' . $shortcode . '_shortcode']);
		}
		add_shortcode($this->prefix . '_product_title', [$this, 'do_product_title_shortcode']);
		add_shortcode($this->prefix . '_buy_now', [$this, 'do_buy_now_shortcode']);
		add_shortcode($this->prefix . '_page_link_by_id', [$this, 'do_page_link_by_id_shortcode']);
		add_shortcode($this->prefix . '_category_link_by_id', [$this, 'do_category_link_by_id_shortcode']);
	}

	/*============================================================================
	 * Публичное API
	 ============================================================================*/

	public function register_api_routes() {
		register_rest_route(Kristall_Integration_Utils::get_api_namespace(), '/template/(?P<shortcode>[a-z_]+)', [
			'methods'             => 'GET',
			'callback'            => [$this, 'api_shortcode_handler'],
			'args'                => [
				'shortcode' => [
					'validate_callback' => function($param, $request, $key) {
						return in_array($param, $this->tab_shortcodes);
					},
				],
			],
			'permission_callback' => function() {
				return current_user_can('edit_others_posts') || is_admin();
			},
		]);
	}

	public function api_shortcode_handler($data) {
		return ["content" => $this->get_shortcode_content('shortcode_id_' . $data['shortcode'])];
	}

	/*============================================================================
	 * Методы и хуки
	 ============================================================================*/

	/**
	 * Возвращает контент для шорткода [kristall_integration_description]. Шаблон "Описание продукта (товар)".
	 *
	 * @return string
	 */
	public function do_description_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_description'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_service_description]. Шаблон "Описание продукта (услуга)".
	 *
	 * @return string
	 */
	public function do_service_description_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_service_description'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_learnplan]. Шаблон "Учебный план".
	 *
	 * @return string
	 */
	public function do_learnplan_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_learnplan'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_requirements]. Шаблон "Требования (документы)".
	 *
	 * @return string
	 */
	public function do_requirements_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_requirements'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_discount]. Шаблон "Рассрочка и скидки".
	 *
	 * @return string
	 */
	public function do_discount_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_discount'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_fines]. Шаблон "Штрафы".
	 *
	 * @return string
	 */
	public function do_fines_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_fines'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_document]. Шаблон "Итоговый документ".
	 *
	 * @return string
	 */
	public function do_document_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_document'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_faq]. Шаблон "Вопрос/ответ".
	 *
	 * @return string
	 */
	public function do_faq_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_faq'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_howtobuy]. Шаблон "Как купить".
	 *
	 * @return string
	 */
	public function do_howtobuy_shortcode() {
		return do_shortcode($this->get_shortcode_content('shortcode_id_howtobuy'));
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_product_title]. Наименование продукта.
	 *
	 * @return string
	 */
	public function do_product_title_shortcode() {
		if (!is_product()) {
			return '';
		}
		global $product;
		return $product->get_title();
	}

	/**
	 * Возвращает контент для шорткода [kristall_integration_buy_now]. Кнопка "Купить сейчас".
	 *
	 * @return string
	 */
	public function do_buy_now_shortcode($attrs) {
		if (!is_product() || !$this->ajax_enabled) {
			return '';
		}
		global $post, $product;

		$default = [
			'text'  => esc_html__('Купить сейчас', 'kristall-integration'),
			'class' => ''
		];
		$a = shortcode_atts($default, $attrs);
		$class_name = $this->get_prefixed_key('buy_now_btn'). ' button ' . $a['class'];
		$token = Kristall_Integration_Utils::create_rest_token();

		return '<button type="button" class="'.esc_attr($class_name).'" data-id="'.esc_attr($post->ID).'" data-type="'.esc_attr($product->get_type()).'" data-key="'.esc_attr($token).'" disabled="disabled">'.esc_html($a['text']).'</button>';
	}

	/**
	 * Возвращает ссылку на страницу по ID.
	 *
	 * @return string
	 */
	public function do_page_link_by_id_shortcode($attrs) {
		$default = [
			'page_id'  => -1,
			'text'  => '',
			'id' => '',
			'class' => '',
			'target' => ''
		];
		$a = shortcode_atts($default, $attrs);

		$link = '#';
		if ($a['page_id'] != -1) {
			$link = get_permalink((int)$a['page_id']);

			if ($link instanceof WP_Error) {
				$link = '#';
			}
		}

		return '<a href="'.$link.'"' . ($a['class'] ? ' class="' . esc_attr($a['class']) . '"' : '') . ($a['id'] ? ' id="' . esc_attr($a['id']) . '"' : '') . ($a['target'] ? ' target="' . esc_attr($a['target']) . '"' : '') . '>' . esc_html($a['text']) . '</a>';
	}

		/**
	 * Возвращает ссылку на категорию по ID.
	 *
	 * @return string
	 */
	public function do_category_link_by_id_shortcode($attrs) {
		$default = [
			'cat_id'  => -1,
			'text'  => '',
			'id' => '',
			'class' => '',
			'target' => ''
		];
		$a = shortcode_atts($default, $attrs);

		$link = '#';
		if ($a['cat_id'] != -1) {
			$link = get_term_link((int)$a['cat_id'], 'product_cat');

			if ($link instanceof WP_Error) {
				$link = '#';
			}
		}

		return '<a href="'.$link.'"' . ($a['class'] ? ' class="' . esc_attr($a['class']) . '"' : '') . ($a['id'] ? ' id="' . esc_attr($a['id']) . '"' : '') . ($a['target'] ? ' target="' . esc_attr($a['target']) . '"' : '') . '>' . esc_html($a['text']) . '</a>';
	}

	/*============================================================================
	 * Приватные методы
	 ============================================================================*/

	private function get_prefixed_key($key) {
		return Kristall_Integration_Utils::get_prefixed_key($key);
	}

	private function get_shortcode_content($key) {
		$options = (get_option($this->option_name) ?? []);

		if (!isset($options[$key]) || !is_numeric($options[$key])) {
			return '';
		}

		$content = get_post_field('post_content', (int)$options[$key]);

		return empty($content) ? '' : $content;
	}
}
