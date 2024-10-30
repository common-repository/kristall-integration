<?php

/**
 * Инициализирует класс.
 *
 */
interface Kristall_Integration_Module {
	public function __construct($plugin_name, $plugin_settings);

	/**
	 * Регистрирует хуки модуля.
	 *
	 * @param Kristall_Integration_Loader $loader Инстанс лоадера, который регистрирует все экшны и фильтры.
	 */
	public function define_hooks($loader);
}
