<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'themes/interface-kristall-integration-theme.php';

class Kristall_Integration_Theme_Default implements Kristall_Integration_Theme {
	public function __construct($plugin_name, $plugin_settings) {}

	public function define_hooks() {}

	/**
	 * Кастомные вкладки выключены.
	 */
	public function use_tabs() {
		return false;
	}

	public function set_tabs($post_id, $tabs) {}

	public function get_tabs($post_id) {
		return [];
	}
}
