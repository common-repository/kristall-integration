<?php

class Kristall_Integration_Settings {
	/**
	 * Версия API Кристалла.
	 *
	 * @var int $api_version Версия API.
	 */
	protected static $api_version = 1;

	/**
	 * Уникальное имя плагина.
	 *
	 * @var string $plugin_name Уникальное имя плагина, которое используется для идентификации.
	 */
	protected static $plugin_name = 'kristall-integration';

	/**
	 * Настройки плагина.
	 *
	 * @var array $plugin_settings Настройки плагинв.
	 */
	protected static $plugin_settings = [
		'version'         => KRISTALL_INTEGRATION_VERSION,
		'release'         => KRISTALL_INTEGRATION_RELEASE,
		'default_api_url' => 'https://npokristal.ru',
		'option_group'    => 'kristal_integration_option_group',
		'option_name'     => 'kristall_options_array',
		'field_names'     => [
			'api_key'                          => 'api_key',
			'api_url'                          => 'kristall_api_url',
			'denied_categories_p'              => 'catListDisabledFiz',
			'cart_message_id'                  => 'cart_message_id',
			'offer_message_id'                 => 'offer_message_id',
			'offer_error_message'              => 'offer_error_message',
			'default_tabs'                     => 'default_tabs',
			'shortcode_id_description'         => 'shortcode_id_description',
			'shortcode_id_service_description' => 'shortcode_id_service_description',
			'shortcode_id_learnplan'           => 'shortcode_id_learnplan',
			'shortcode_id_requirements'        => 'shortcode_id_requirements',
			'shortcode_id_discount'            => 'shortcode_id_discount',
			'shortcode_id_fines'               => 'shortcode_id_fines',
			'shortcode_id_document'            => 'shortcode_id_document',
			'shortcode_id_faq'                 => 'shortcode_id_faq',
			'shortcode_id_howtobuy'            => 'shortcode_id_howtobuy',
			'btn_bg'                           => 'wooCheckoutButtonBgColor',
			'btn_text_color'                   => 'wooCheckoutButtonTxtColor',
			'btn_border_color'                 => 'wooCheckoutButtonBorderColor',
			'btn_active_bg'                    => 'wooCheckoutButtonBgColorActive',
			'btn_active_text_color'            => 'wooCheckoutButtonTxtColorActive',
			'btn_active_border_color'          => 'wooCheckoutButtonBorderColorActive',
			'btn_active_shadow_color'          => 'wooCheckoutButtonBgColorShadow',
			'btn_hover_bg'                     => 'wooCheckoutButtonBgColorHover',
			'btn_hover_text_color'             => 'wooCheckoutButtonTxtColorHover',
			'btn_hover_border_color'           => 'wooCheckoutButtonBorderColorHover',
		],
		'meta_fields'     => [
			'product_type'        => 'is_service',
			'manufacturer'        => 'country',
			'customs_declaration' => 'customs_declaration',
			'unit'                => 'unit',
			'duration'            => 'num_hours',
			'type_title'          => 'prodtypekristall',
			'study_type'          => 'study_type',
			'study_form'          => 'study_form',
			'study_access'        => 'study_access',
			'exam'                => 'exam',
			'document'            => 'document',
			'study_period'        => 'study_period',
			'short_code'          => 'short_code',
			'single_in_cart'      => 'single_in_cart',
			'sold_individually'   => 'sold_individually'
		],
	];

	/**
	 * Возвращает имя плагина
	 *
	 * @return string
	 */
	public static function get_plugin_name() {
		return self::$plugin_name;
	}

	/**
	 * Возвращает массив настроек плагина
	 *
	 * @return array
	 */
	public static function get_plugin_settings() {
		return self::$plugin_settings;
	}

	/**
	 * Возвращает версию API
	 *
	 * @return int
	 */
	public static function get_api_version() {
		return self::$api_version;
	}
}
