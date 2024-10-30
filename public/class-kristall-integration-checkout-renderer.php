<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';

class Kristall_Integration_Checkout_Renderer {
	/**
	 * Добавляет к форме кнопки выбора типа клиентов.
	 *
	 * @param array $fields              Массив полей формы оплаты Woocommerce.
	 * @param array $client_type_options Массив с типами клиентов.
	 *
	 * @return void
	 */
	public function add_checkout_tabs(&$fields, $client_type_options) {
		$available_tabs = array_keys($client_type_options);
		$fields[$this->get_field_name('client_type')] = [
			'type'     => 'radio',
			'required' => true,
			'class'    => ['client_info', $this->get_prefixed_key('billing-info__tabs'), $available_tabs[0]],
			'options'  => $client_type_options,
			'default'  => $available_tabs[0],
		];
	}

	/**
	 * Добавляет к форме кнопки выбора типа оплаты.
	 *
	 * @param array $fields              Массив полей формы оплаты Woocommerce.
	 * @param array $cur_payment_methods Массив с текущими типами оплаты.
	 *
	 * @return void
	 */
	public function add_payment_tabs(&$fields, $cur_payment_methods) {
		$payment_methods = ['tinkoff' => esc_html__('Онлайн', 'kristall-integration'), 'cod' => esc_html__('Выставление счета', 'kristall-integration')];

		foreach ($payment_methods as $id => $title) {
			if (!in_array($id, $cur_payment_methods)) {
				unset($payment_methods[$id]);
			}
		}

		if (!count($payment_methods)) {
			$fields['no_payment_method'] = ['type' => 'text'];
			return;
		}
		$available_payment_methods = array_keys($payment_methods);

		$fields[$this->get_field_name('payment_method')] = [
			'type'     => 'radio',
			'required' => true,
			'class'    => [$this->get_prefixed_key('billing-info__tabs'), 'wc_payment_methods'],
			'options'  => $payment_methods,
			'default'  => $available_payment_methods[0],
		];
	}

	/**
	 * Добавляет поле к форме.
	 *
	 * @param array  $fields  Массив полей формы оплаты Woocommerce.
	 * @param string $name    Имя поля.
	 * @param string $group   Группа, к которой относится поле.
	 * @param array  $options Опции поля.
	 *
	 * @return void
	 */
	public function add_form_field(&$fields, $name, $options) {
		$classes = [];

		if (isset($options['class']) && is_array($options['class'])) {
			$options['class'] = array_merge($classes, $options['class']);
		} else {
			$options['class'] = $classes;
		}

		$fields[$name] = $options;
	}

	/**
	 * Выводит сообщение о невозможности покупки.
	 *
	 * @return void
	 */
	public function render_client_types_message() {
		?>
		<div class="<?php echo esc_attr($this->get_prefixed_key('no_client_types')) ?> woocommerce-error">
			<?php esc_html_e('В Вашей корзине находятся товары предназначенные для разных клиентов. Одновременная покупка невозможна.', 'kristall-integration') ?>
		</div>
		<?php
	}

	private function get_prefixed_key($key) {
		return Kristall_Integration_Utils::get_prefixed_key($key);
	}

	private function get_field_name($name) {
		return Kristall_Integration_Utils::get_field_name($name);
	}
}