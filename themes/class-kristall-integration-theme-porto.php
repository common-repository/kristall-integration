<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'themes/interface-kristall-integration-theme.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-product-utils.php';

class Kristall_Integration_Theme_Porto implements Kristall_Integration_Theme {
	private $meta_fields;
	private $plugin_name;

	public function __construct($plugin_name, $plugin_settings) {
		$this->plugin_name = $plugin_name;
		$this->meta_fields = $plugin_settings['meta_fields'];
	}

	/*============================================================================
	 * Регистрация темы
	 ============================================================================*/

	public function define_hooks() {
		add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
		add_action('wp', [$this, 'initialize_layout'], 20);
		add_filter('woocommerce_is_rest_api_request', [$this, 'disable_hooks_on_wc_api_request'], 20);
	}

	public function enqueue_styles() {
		wp_enqueue_style($this->plugin_name . '-theme-porto', plugin_dir_url(__FILE__) . 'css/kristall-integration-theme-porto.css?v=' . KRISTALL_INTEGRATION_ASSETS_VERSION, [], false, 'all');
	}

	/**
	 * Кастомные вкладки включены.
	 */
	public function use_tabs() {
		return true;
	}

	/*============================================================================
	 * Методы
	 ============================================================================*/

	public function get_tabs($post_id) {
		global $porto_settings;

		$tabs = [];
		$last_index = 0;
		$custom_tabs_count = (int)($porto_settings['product-custom-tabs-count'] ?? '2');

		if ($custom_tabs_count) {
			for ($i = 0; $i < $custom_tabs_count; $i++) {
				$index = $i + 1;
				$custom_tab_title = get_post_meta($post_id, 'custom_tab_title' . $index, true);
				$custom_tab_priority = (int)get_post_meta($post_id, 'custom_tab_priority' . $index, true);

				if (!$custom_tab_priority) {
					$custom_tab_priority = 40 + $i;
				}

				$custom_tab_content = get_post_meta($post_id, 'custom_tab_content' . $index, true);

				if ($custom_tab_title && $custom_tab_content) {
					$tabs[] = [
						'id'         => $this->get_prefixed_key('custom-tab-' . ++$last_index),
						'title'      => $custom_tab_title,
						'priority'   => $custom_tab_priority,
						'content'    => $custom_tab_content,
						'persistent' => false,
					];
				}
			}
		}

		return $tabs;
	}

	public function set_tabs($post_id, $tabs) {
		global $porto_settings;
		$custom_tabs_count = (int)($porto_settings['product-custom-tabs-count'] ?? '2');

		if ($custom_tabs_count) {
			for ($i = 0; $i < $custom_tabs_count; $i++) {
				$index = $i + 1;

				if (isset($tabs[$i])) {
					update_post_meta($post_id, 'custom_tab_title' . $index, wp_slash($tabs[$i]['title']));
					update_post_meta($post_id, 'custom_tab_priority' . $index, $tabs[$i]['priority']);
					update_post_meta($post_id, 'custom_tab_content' . $index, wp_slash($tabs[$i]['content']));
				} else {
					update_post_meta($post_id, 'custom_tab_title' . $index, '');
					update_post_meta($post_id, 'custom_tab_priority' . $index, '');
					update_post_meta($post_id, 'custom_tab_content' . $index, '');
				}
			}
		}
	}

	/**
	 * Инициализирует лэйаут страницы продукта.
	 *
	 * @return void
	 */
	public function initialize_layout() {
		// Работаем только на странице продукта с дефолтным лэйаутом
		global $porto_product_layout;
		if (!isset($porto_product_layout) || $porto_product_layout != 'default') {
			return;
		}

		remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
		remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
		remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
		remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
		remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 26);
		remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);

		add_action('woocommerce_before_single_product', [$this, 'open_wrapper_block'], 1);
		add_action('woocommerce_after_single_product', [$this, 'close_wrapper_block'], 100);

		add_action('woocommerce_single_product_summary', [$this, 'render_category_title'], 4);
		add_action('woocommerce_single_product_summary', [$this, 'render_price'], 10);
		add_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 15);
		add_action('woocommerce_single_product_summary', [$this, 'render_meta_title'], 20);
		add_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 30);
		add_action('woocommerce_single_product_summary', [$this, 'render_footer'], 40);
		add_action('woocommerce_after_add_to_cart_button', [$this, 'add_buy_now_button']);
		add_action('woocommerce_after_add_to_cart_form', [$this, 'check_meta_flags']);
	}

	/**
	 * Открывает враппер.
	 *
	 * @return void
	 */
	public function open_wrapper_block() {
		global $product;

		$sold_individually = Kristall_Integration_Product_Utils::is_sold_individually($product);

		echo '<div class="' . esc_attr($this->get_prefixed_key('porto_product')) . ($sold_individually ? ' single-sold-individually' : '') . '" data-id="' . esc_attr($product->get_id()) . '">';
	}

	/**
	 * Закрывает враппер.
	 *
	 * @return void
	 */
	public function close_wrapper_block() {
		echo "</div>";
	}

	/**
	 * Выводит блок категории товара с Кристалла.
	 *
	 * @return void
	 */
	public function render_category_title() {
		global $post;

		$product = wc_get_product($post->ID);
		$type_title = $product->get_meta($this->meta_fields['type_title']);

		if (!empty($type_title)) {
			echo '<div class="kristall_integration__product_header">' . esc_html($type_title) . '</div>';
		}
	}

	/**
	 * Выводит блок цены.
	 *
	 * @return void
	 */
	public function render_price() {
		global $product;

		if ($product->is_in_stock()) {
			if ($product->get_price()) {
			?>
			<div class="<?php echo esc_attr($this->get_prefixed_key('price')) ?>">
				<div class="<?php echo esc_attr($this->get_prefixed_key('price_label')) ?>"><?php esc_html_e('Цена:', 'kristall-integration') ?></div>
				<?php woocommerce_template_single_price() ?>
			</div>
			<?php
			}
		}
	}

	/**
	 * Выводит блок с заголовком характеристик.
	 *
	 * @return void
	 */
	public function render_meta_title() {
		?>
		<div class="<?php echo esc_attr($this->get_prefixed_key('meta_title')) ?>">
			<?php esc_html_e('Характеристики:', 'kristall-integration') ?>
		</div>
		<?php
	}

	/**
	 * Выводит блок с дополнительными данными и информацией.
	 *
	 * @return void
	 */
	public function render_footer() {
		global $post;
		$product_id = $post->ID;
		?>
		<div class="<?php echo esc_attr($this->get_prefixed_key('product-footer')) ?>">
			<a class="<?php echo esc_attr($this->get_prefixed_key('barcode_link')) ?>"
			   data-product="<?php echo esc_attr($product_id) ?>" href="#"><?php esc_html_e('ШТРИХ-КОД', 'kristall-integration') ?></a>
			<?php $this->print_rating_stars() ?>
			<a class="<?php echo esc_attr($this->get_prefixed_key('qrcode_link')) ?>" href="#"><?php esc_html_e('QR-КОД', 'kristall-integration') ?></a>
			<?php $this->print_rating_links() ?>
			<?php $this->print_purchases() ?>
			<?php woocommerce_template_single_sharing() ?>
		</div>
		<?php
	}

	/**
	 * Выводит кнопку "Купить сейчас" в блок корзины.
	 *
	 * @return void
	 */
	public function add_buy_now_button() {
		global $product;
		$btn_text = $product->get_meta($this->meta_fields['product_type']) == '1' ? esc_html__('Начать обучение', 'kristall-integration') : esc_html__('Купить сейчас', 'kristall-integration');
		$sku_type = substr($product->get_sku(), 0, 3);

		if ($sku_type == 'pfr' || $sku_type == 'sou') {
			$btn_text = esc_html__('Заказать сейчас', 'kristall-integration');
		}

		?>
		<span class="single_add_to_cart_button button alt already_in_cart"><?php esc_html_e('В корзине', 'kristall-integration') ?></span>
		<?php echo do_shortcode('[kristall_integration_buy_now text="' . $btn_text . '" class="kristall-integration__buy_now_in_cart"]') ?>
		<?php
	}

	public function check_meta_flags() {
		global $product;

		$single_in_cart = Kristall_Integration_Product_Utils::is_single_in_cart($product);
		$sold_individually = Kristall_Integration_Product_Utils::is_sold_individually($product);

		if ($sold_individually) {
			echo "<script>(function(){";
			echo "document.querySelector('.kristall-integration__porto_product').classList.add('kristall-integration__hide-quantity');";

			if (Kristall_Integration_Product_Utils::in_cart($product)) {
				echo "document.querySelector('.kristall-integration__porto_product').classList.add('kristall-integration__in_cart');";
			}

			echo "})();</script>";
		}

		if ($single_in_cart) {
			echo "<script>(function(){document.querySelector('.kristall-integration__porto_product').classList.add('kristall-integration__hide-in_cart');})()</script>";
		}
	}

	/**
	 * Отключает некоторые хуки Porto, которые приводят к ошибке при вызове Woocommerce Rest API Update
	 */
	function disable_hooks_on_wc_api_request($is_rest_api_request) {
		if ($is_rest_api_request) {
			remove_action('edit_term', 'porto_save_product_cat_meta_values', 100, 3);
			remove_action('delete_term', 'porto_delete_product_cat_meta_values', 10, 5);
		}
		return $is_rest_api_request;
	}


	/*============================================================================
	 * Приватные методы
	============================================================================*/

	private function get_prefixed_key($key) {
		return Kristall_Integration_Utils::get_prefixed_key($key);
	}

	/**
	 * Выводит блок с рейтингом
	 *
	 * @return void
	 */
	private function print_rating_stars() {
		if ((function_exists('wc_review_ratings_enabled') && !wc_review_ratings_enabled()) || (!function_exists('wc_review_ratings_enabled') && 'no' === get_option('woocommerce_enable_review_rating'))) {
			return;
		}

		global $product;

		$rating_count = $product->get_rating_count();
		$review_count = $product->get_review_count();
		$average = $product->get_average_rating();

		?>
		<div class="woocommerce-product-rating">
			<div class="star-rating" title="<?php echo esc_attr($average); ?>">
		<span style="width:<?php echo(100 * ($average / 5)); ?>%">
			<?php /* translators: %s: Rating value */ ?>
			<strong
				class="rating"><?php echo esc_html($average); ?></strong> <?php printf(esc_html__('out of %1$s5%2$s', 'woocommerce'), '', ''); ?>
		</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Выводит блок с ссылками для рейтинга.
	 *
	 * @return void
	 */
	private function print_rating_links() {
		if (
			(function_exists('wc_review_ratings_enabled') && !wc_review_ratings_enabled()) ||
			(!function_exists('wc_review_ratings_enabled') && 'no' === get_option('woocommerce_enable_review_rating'))
		) {
			return;
		}

		global $product;

		$rating_count = $product->get_rating_count();
		$review_count = $product->get_review_count();

		if (comments_open() || wc_reviews_enabled()) {
			if ($rating_count > 0) {
				?>
				<div class="review-link"><a href="<?php echo porto_is_ajax() ? esc_url(get_the_permalink()) : ''; ?>#reviews"
				                            class="woocommerce-review-link"
				                            rel="nofollow"><?php printf(_n('%s customer review', '%s customer reviews', (int)$review_count, 'woocommerce'), '<span class="count">' . ((int)$review_count) . '</span>'); ?></a>
				| <a href="<?php echo porto_is_ajax() ? esc_url(get_the_permalink()) : ''; ?>#review_form"
				     class="woocommerce-write-review-link"
				     rel="nofollow"><?php esc_html_e('Add a review', 'woocommerce'); ?></a></div><?php
			} else {
				?>
				<div class="review-link noreview">
					<a href="<?php echo porto_is_ajax() ? esc_url(get_the_permalink()) : ''; ?>#review_form"
					   class="woocommerce-write-review-link"
					   rel="nofollow"><?php esc_html_e('There are no reviews yet.', 'woocommerce') ?></a>
				</div>
				<?php
			}
		}
	}

	/**
	 * Возвращает количество покупок в строковом представлении.
	 *
	 * @param int    $product_id ID поста/продукта.
	 * @param string $status     Статус продукта.
	 *
	 * @return string
	 */
	private function get_order_count($product_id, $status) {
		global $wpdb;

		$prod_cats = wc_get_product_cat_ids($product_id);

		if (in_array(193, $prod_cats) || in_array(105, $prod_cats)) return '';

		$rtn = 1000 + $product_id;
		$rtn += array_sum($prod_cats);
		$rtn += $wpdb->get_var($wpdb->prepare("
        SELECT count(o.ID)
        FROM {$wpdb->prefix}posts o
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
            ON o.ID = oi.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
            ON oi.order_item_id = oim.order_item_id
        WHERE o.post_status = '%s'
            AND oim.meta_key IN ('_product_id','_variation_id')
            AND oim.meta_value = %d
    ", $status, $product_id));

		return (string)(int)substr_replace($rtn, '00', -2);
	}

	/**
	 * Выводит блок с количеством покупок.
	 *
	 * @return void
	 */
	private function print_purchases() {
		global $product;
		$fake_order_count = $this->get_order_count($product->get_id(), 'wc-processing');

		if (!empty($fake_order_count)) {
		?>
		<div class="<?php echo $this->get_prefixed_key('sales') ?>">
			<?php printf(__('<span>более %s</span> покупок', 'kristall-integration'), $fake_order_count) ?>
		</div>
		<?php
		}
	}
}
