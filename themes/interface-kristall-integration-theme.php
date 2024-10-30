<?php

interface Kristall_Integration_Theme {
	/**
	 * @param string $plugin_name  Имя плагина.
	 * @param array  $plugin_settings Массив с настройками плагина.
	 */
	public function __construct($plugin_name, $plugin_settings);

	/**
	 * Регистрирует хуки.
	 */
	public function define_hooks();

	/**
	 * Возвращает true если тема поддерживает кастомные вкладки.
	 *
	 * @return bool
	 */
	public function use_tabs();

	/**
	 * Сохраняет кастомные вкладки.
	 *
	 * @param int   $post_id ID поста/продукта.
	 * @param array $tabs    Массив с вкладками.
	 *
	 * @return void
	 */
	public function set_tabs($post_id, $tabs);

	/**
	 * Возвращает массив с кастомными вкладками для поста/продукта.
	 *
	 * @param int $post_id ID поста/продукта.
	 *
	 * @return array
	 */
	public function get_tabs($post_id);
}
