<?php
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-settings.php';
require_once KRISTALL_INTEGRATION_MAIN_DIR . 'includes/class-kristall-integration-utils.php';

class Kristall_Integration_Admin_Renderer {
	protected static function get_option_name() {
		return Kristall_Integration_Settings::get_plugin_settings()['option_name'];
	}

	/**
	 * Проводит очистку от HTML.
	 *
	 * @param string  $description  Текст для очистки.
	 *
	 * @return string
	 */
	private function esc_description($description) {
    $allowed_tags = [
			'span'   => [
				'id'    => [],
				'class' => [],
			],
      'a'      => [
        'href'  => [],
        'title' => [],
				'id'    => [],
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
    return wp_kses($description, $allowed_tags);
  }

	/**
	 * Выводит числовое поле.
	 *
	 * @param string      $name        Имя поля.
	 * @param mixed       $default     Значение по-умолчанию.
	 * @param string|null $description Опциональный текст описания поля.
	 *
	 * @return void
	 */
	public function render_number_field($name, $default, $description = null) {
		$options = get_option(self::get_option_name());
		$value = $options[$name] ?? $default;

		?>
		<fieldset>
			<input
				type="number"
				name="<?php echo esc_attr(self::get_option_name()) ?>[<?php echo esc_attr($name) ?>]"
				min="0"
				value="<?php echo esc_attr($value); ?>"
				style="width: 120px;"
			/>
			<?php if ($description) { ?>
				<p class="description"><?php echo $this->esc_description($description) ?></p>
			<?php } ?>
		</fieldset>
		<?php
	}

	/**
	 * Выводит текстовое поле.
	 *
	 * @param string      $name        Имя поля.
	 * @param mixed       $default     Значение по-умолчанию.
	 * @param string|null $description Опциональный текст описания поля.
	 *
	 * @return void
	 */
	public function render_text_field($name, $default, $description = null, $help_btn_id = null) {
		$options = get_option(self::get_option_name());
		$value = $options[$name] ?? $default;

		?>
		<fieldset style="display: relative">
			<input
				type="text"
				name="<?php echo esc_attr(self::get_option_name()) ?>[<?php echo esc_attr($name) ?>]"
				value="<?php echo esc_attr($value); ?>"
				class="regular-text"
			/>
			<?php if ($help_btn_id) { ?>
				<a href="#" class="krl-input-help" id="<?php echo esc_attr($help_btn_id); ?>">?</a>
			<?php } ?>
			<?php if ($description) { ?>
				<p class="description"><?php echo $this->esc_description($description) ?></p>
			<?php } ?>
		</fieldset>
		<?php
	}

	/**
	 * Выводит поле выбора цвета.
	 *
	 * @param string      $name        Имя поля.
	 * @param mixed       $default     Значение по-умолчанию.
	 * @param string|null $description Опциональный текст описания поля.
	 *
	 * @return void
	 */
	public function render_color_field($name, $default, $description = null) {
		$options = get_option(self::get_option_name());
		$value = $options[$name] ?? $default;

		?>
		<fieldset class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('color-picker-wrapper')) ?>">
			<input
				type="text"
				name="<?php echo esc_attr(self::get_option_name()) ?>[<?php echo esc_attr($name) ?>]"
				value="<?php echo esc_attr($value); ?>"
				class="regular-text <?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('color-picker')) ?>"
				data-kristall-cp-option-name="<?php echo esc_attr($name); ?>"
			/>
			<?php if ($description) { ?>
				<p class="description"><?php echo $this->esc_description($description) ?></p>
			<?php } ?>
		</fieldset>
		<?php
	}

	/**
	 * Выводит предпросмотр кнопок выбора типа клиента на странице оплаты.
	 *
	 * @return void
	 */
	public function render_buttons_preview_block() {
		?>
		<div id="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('btns-preview')) ?>">
			<p class="description"><?php esc_html_e('ПРЕДПРОСМОТР', 'kristall-integration') ?></p>

			<div class="<?php echo esc_attr(Kristall_Integration_Utils::get_prefixed_key('billing-info__tabs')) ?>">
					<span class="woocommerce-input-wrapper">
						<input type="radio" class="input-radio" checked="checked" name="preview-billing-info-tabs"
						       id="preview-billing-info-tabs-1">
						<label for="preview-billing-info-tabs-1" class="radio"><?php esc_html_e('Физическое лицо', 'kristall-integration') ?></label>
						<input type="radio" class="input-radio" name="preview-billing-info-tabs"
						       id="preview-billing-info-tabs-2">
						<label for="preview-billing-info-tabs-2" class="radio"><?php esc_html_e('Юридическое лицо / ИП', 'kristall-integration') ?></label>
					</span>
			</div>
		</div>
		<?php
	}
}
