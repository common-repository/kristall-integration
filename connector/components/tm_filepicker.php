<?php

defined('KRISTALL_INTEGRATION_CONNECTOR_ENABLED') || exit;

if(is_admin()) {
	add_action('wp_ajax_krConnectTmflp', function() {
		$path_lib = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/tm_filepicker/';
		include $path_lib . 'dialog.php';
		wp_die();
	});
	
	add_action('wp_ajax_krConnectTmflpCalls', function() {
		$path_lib = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/tm_filepicker/';
		include $path_lib . 'ajax_calls.php';
		wp_die();
	});
	
	add_action('wp_ajax_krConnectTmflpFD', function() {
		$path_lib = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/tm_filepicker/';
		include $path_lib . 'force_download.php';
		wp_die();
	});
	
	add_action('wp_ajax_krConnectTmflpFDExt', function() {
		$path_lib = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/tm_filepicker/';
		include $path_lib . 'execute.php';
		wp_die();
	});
	
	add_action('wp_ajax_krConnectTmflpFUpld', function() {
		$path_lib = KRISTALL_INTEGRATION_MAIN_DIR . 'connector/tm_filepicker/';
		include $path_lib . 'upload.php';
		wp_die();
	});
}
