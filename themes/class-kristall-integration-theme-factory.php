<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'themes/interface-kristall-integration-theme.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php';

class Kristall_Integration_Theme_Factory {
	/**
	 * Текущая тема.
	 *
	 * @var Kristall_Integration_Theme Текущая тема.
	 */
	protected static $current_theme = null;

	/**
	 * Массив с поддерживаемыми темами.
	 *
	 * @var string[] Массив с поддерживаемыми темами.
	 */
	protected static $themes = [
		'default' => 'Default',
		'porto'   => 'Porto',
	];

	/**
	 * Возвращает инстанс текущей темы.
	 *
	 * @return Kristall_Integration_Theme
	 */
	public static function get_theme() {
		if (isset(self::$current_theme)) {
			return self::$current_theme;
		}

		$theme_name = self::get_theme_name();
		require KRISTALL_INTEGRATION_MAIN_DIR . 'themes/class-kristall-integration-theme-' . $theme_name . '.php';

		$theme_class = 'Kristall_Integration_Theme_' . self::$themes[$theme_name];
		self::$current_theme = new $theme_class(
			Kristall_Integration_Settings::get_plugin_name(),
			Kristall_Integration_Settings::get_plugin_settings()
		);
		self::$current_theme->define_hooks();

		return self::$current_theme;
	}

	/**
	 * Инициализирует тему. Не требуется вызывать если уже вызван метод .get_theme()
	 *
	 * @return void
	 */
	public static function init_theme() {
		self::get_theme();
	}

	/**
	 * Возвращает имя текущей темы.
	 *
	 * @return string
	 */
	protected static function get_theme_name() {
		$theme_name = strtolower(wp_get_theme()->get('Name'));
		if (strpos($theme_name, 'porto') !== false) {
			return 'porto';
		} else {
			return 'default';
		}
	}
}
