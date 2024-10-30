<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';

class Kristall_Integration_Tab_Utils {
	/**
	 * @param mixed $post Опциональный объект поста/продукта. Если параметр не передан или равен null, то вернется массив вне зависимости от условий.
	 *
	 * @return array|null
	 */
	public static function get_default_product_description_tab($post = null) {
		if (!isset($post) || $post->post_content) {
			return [
				'id'         => Kristall_Integration_Utils::get_prefixed_key('default-tab-description', '-'),
				'title'      => esc_html__('Описание', 'kristall-integration'),
				'priority'   => 10,
				'persistent' => true,
			];
		}

		return null;
	}

		/**
	 * Возвращает массив c дефолтными вкладками Woocommerce.
	 *
	 * @param bool $full_list Если true, то метод возвращает полный список вкладок, без обращения к объекту продукта.
	 *
	 * @return array
	 */
	public static function get_default_product_tabs($full_list = false) {
		global $post;
		$product = $full_list ? null : wc_get_product($post);
		$tabs = [];

		// Описание
		$description_tab = self::get_default_product_description_tab($full_list ? null : $post);
		if (isset($description_tab)) {
			$tabs[] = $description_tab;
		}

		// Вкладка "Детали" - содержит атрибуты.
		if ($full_list || ($product && ($product->has_attributes() || apply_filters('wc_product_enable_dimensions_display', $product->has_weight() || $product->has_dimensions())))) {
			$tabs[] = [
				'id'         => Kristall_Integration_Utils::get_prefixed_key('default-tab-additional_info', '-'),
				'title'      => esc_html__('Детали', 'kristall-integration'),
				'priority'   => 20,
				'persistent' => true,
			];
		}

		// Вкладка с отзывами.
		if ($full_list || comments_open()) {
			$tabs[] = [
				'id'         => Kristall_Integration_Utils::get_prefixed_key('default-tab-comments', '-'),
				'title'      => esc_html__('Отзывы', 'kristall-integration'),
				'priority'   => 30,
				'persistent' => true,
			];
		}

		return $tabs;
	}

	/**
	 * Возвращает массив вкладок по-умолчанию, определенных пользователем.
	 *
	 * @return array
	 */
	public static function get_user_default_tabs() {
		$settings = Kristall_Integration_Settings::get_plugin_settings();
		$options = get_option($settings['option_name']) ?? [];
		$field_name = $settings['field_names']['default_tabs'];

		if (!isset($options[$field_name])) {
			return [];
		}

		$tabs = $options[$field_name];

		// Устанавливаем служебные поля
		foreach ($tabs as $index => &$actual_tab) {
			$actual_tab['id'] = Kristall_Integration_Utils::get_prefixed_key('custom-tab-' . ($index + 1));
			$actual_tab['persistent'] = false;
		}

		return $tabs;
	}

	/**
	 * Сортирует массив с вкладками по приоритету. Изменяет исходный массив.
	 *
	 * @param array $tabs Исходный массив
	 *
	 * @return void
	 */
	public static function sort_tabs(&$tabs) {
		function tabs_sort($a, $b) {
			return $a['priority'] - $b['priority'];
		}

		usort($tabs, 'tabs_sort');
	}

	/**
	 * Выводит UI для настройки вкладок.
	 *
	 * @param array  $tabs        Массив со всеми вкладками, состоящий из кастомных вкладок и стандартных вкладок Woocommerce.
	 * @param array  $actual_tabs Массив с кастомными вкладками, добавленными через плагин.
	 * @param string $input_name  Имя скрытого поля в HTML.
	 *
	 * @return void
	 */
	public static function print_tab_list_settings($tabs, $actual_tabs, $input_name) {
		if (empty($input_name)) {
			$input_name = Kristall_Integration_Utils::get_field_name('product_tabs_input');
		}

		/*
		 * $tabs[] = [
		 *   'id'         => string,
		 *   'title'      => string,
		 *   'priority'   => int,
		 *   'content'    => string,
		 *   'persistent' => bool,
		 * ]
		*/

		?>
		<div class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_list')) ?>">
			<input type="hidden" value="<?php echo esc_attr(json_encode(['data' => $actual_tabs])) ?>"
			       name="<?php echo esc_attr($input_name) ?>"/>
			<?php foreach ($tabs as $tab) { ?>
				<div
					class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_item')) . ($tab['persistent'] ? ' persistent' : '') ?>"
					data-id="<?php echo esc_attr($tab['id']) ?>"
					data-target="<?php echo esc_attr($tab['id'] . '_content') ?>"
					<?php echo $tab['persistent'] ? ' data-priority="' . esc_attr($tab['priority']) . '"' : '' ?>
				>
					<span><?php echo esc_html($tab['title']) ?></span>
					<span
						class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_item_remove')) ?>">&times;</span>
				</div>
			<?php } ?>

			<div class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_add')) ?>">
				<div class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_item')) ?> persistent"><?php esc_html_e('+ Добавить', 'kristall-integration') ?></div>
				<div class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu')) ?>">
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="learnplan" data-title="<?php echo esc_attr(esc_html__('Учебный план', 'kristall-integration')) ?>"><?php esc_html_e('Учебный план', 'kristall-integration') ?></a>
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="requirements" data-title="<?php echo esc_attr(esc_html__('Требования (документы)', 'kristall-integration')) ?>"><?php esc_html_e('Требования (документы)', 'kristall-integration') ?></a>
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="discount" data-title="<?php echo esc_attr(esc_html__('Рассрочка и скидки', 'kristall-integration')) ?>"><?php esc_html_e('Рассрочка и скидки', 'kristall-integration') ?></a>
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="fines" data-title="<?php echo esc_attr(esc_html__('Штрафы', 'kristall-integration')) ?>"><?php esc_html_e('Штрафы', 'kristall-integration') ?></a>
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="document" data-title="<?php echo esc_attr(esc_html__('Итоговый документ', 'kristall-integration')) ?>"><?php esc_html_e('Итоговый документ', 'kristall-integration') ?></a>
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="faq" data-title="<?php echo esc_attr(esc_html__('Вопрос/ответ', 'kristall-integration')) ?>"><?php esc_html_e('Вопрос/ответ', 'kristall-integration') ?></a>
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="howtobuy" data-title="<?php echo esc_attr(esc_html__('Как купить', 'kristall-integration')) ?>"><?php esc_html_e('Как купить', 'kristall-integration') ?></a>
					<div class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_separator')) ?>"></div>
					<a class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('product_tab_menu_item')) ?>"
					   href="#" data-type="custom" data-title="<?php echo esc_attr(esc_html__('Произвольная вкладка', 'kristall-integration')) ?>"><?php esc_html_e('Произвольная вкладка', 'kristall-integration') ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Парсит JSON строку с вкладками в массив.
	 *
	 * @param string $tabs_json        Строка с JSON представлением массива вкладок.
	 * @param bool   $content_required Удалять ли вкладки, если содержимое не установлено или содержит пустую строку.
	 *
	 * @return array
	 */
	public static function parse_tabs_json($tabs_json, $content_required = true) {
		$tabs_data = [];

		try {
			$tabs_data = json_decode($tabs_json, true);
			if (!is_array($tabs_data) || !is_array($tabs_data['data'])) {
				return [];
			}
			$tabs_data = $tabs_data['data'];
		} catch (Exception $e) {
			return [];
		}

		$allowed_title_html = [
			'span'   => [
				'class' => [],
			],
			'b'      => [],
			'strong' => [],
			'i'      => [],
			'em'     => [],
			'mark'   => [],
			'small'  => [],
			'del'    => [],
			'ins'    => [],
			'sub'    => [],
			'sup'    => [],
			'u'      => [],
		];
		foreach ($tabs_data as $index => &$tab_data) {
			if (!is_array($tab_data) || !is_string($tab_data['title']) || !is_string($tab_data['content']) || !is_numeric($tab_data['priority'])) {
				unset($tabs_data[$index]);
				continue;
			}

			unset($tab_data['id']);
			unset($tab_data['persistent']);
			$tab_data['title'] = wp_kses(trim($tab_data['title']), $allowed_title_html);
			$tab_data['content'] = trim($tab_data['content']);
			$tab_data['priority'] = (int)$tab_data['priority'];

			if (empty($tab_data['title']) || (empty($tab_data['content']) && $content_required)) {
				unset($tabs_data[$index]);
			}
		}

		return $tabs_data;
	}
}
