<?php

class Kristall_Integration_Validator_Result {
	private $message = null;
	private $valid = false;
	private $value;

	public function __construct($valid, $value, $message = null) {
		$this->valid = $valid;
		$this->value = $value;
		$this->message = $message;
	}

	/**
	 * Возвращает true если значение валидно.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return $this->valid;
	}

	/**
	 * Возвращает сконвертированное значение если валидация успешна, иначе возвращает значение "как есть".
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Возвращает сообщение об ошибке если валидация провалена, иначе возвращает null.
	 *
	 * @return mixed
	 */
	public function get_message() {
		return $this->message;
	}
}

class Kristall_Integration_Validator {
	/**
	 * Сообщения об ошибках
	 *
	 * @var array $messages Сообщения об ошибках.
	 */
	private $messages;

	public function __construct($messages = null) {
		$this->messages = [
			'required'       => esc_html__('Поле "%s" обязательно для заполнения.', 'kristall-integration'),
			'int'            => esc_html__('Поле "%s" должно содержать целое число.', 'kristall-integration'),
			'url'            => esc_html__('Поле "%s" должно содержать URL-адрес.', 'kristall-integration'),
			'color'          => esc_html__('Поле "%s" должно содержать корректное значение цвета. Например, #fff, #a2c4c9, rgb(100, 170, 250), rgba(100, 170, 250, 0.3).', 'kristall-integration'),
			'id_string_list' => esc_html__('Поле "%s" должно содержать целое число или набор целых чисел, разделенных запятой.', 'kristall-integration'),
			'email'          => esc_html__('Поле "%s" должно содержать корректный E-mail адрес.', 'kristall-integration'),
			'phone'          => esc_html__('Поле "%s" должно содержать корректный телефонный номер.', 'kristall-integration'),
			'inn'            => esc_html__('Поле "%s" должно содержать корректный ИНН, состоящий из 10-ти или 12-ти цифр.', 'kristall-integration'),
			'ogrnip'         => esc_html__('Поле "%s" должно содержать корректный ОГРНИП, состоящий из 15-ти цифр.', 'kristall-integration'),
			'ogrn'           => esc_html__('Поле "%s" должно содержать корректный ОГРН, состоящий из 13-ти цифр.', 'kristall-integration'),
		];
		$this->merge_options($messages, $this->messages);
	}

	/**
	 * Проводит проверку значения на число и, в случае успеха, принудительно конвертирует его.
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function int($value, $options = null) {
		$options = $this->get_options($options);
		$value = $this->sanitize_text_field($value);
		$value = $this->trim_value($value, $options);

		$required_or_optional = $this->validate_required_or_optional($value, $options);
		if ($required_or_optional) {
			return $required_or_optional;
		}

		if (is_numeric($value)) {
			return $this->result(true, intval($value));
		}

		return $this->result(
			false,
			$value,
			$this->get_message('int', $options['label'], $options['message'])
		);
	}

	/**
	 * Проводит проверку значения на валидный URL. Принимает дополнительные опциональные ключи опций "protocols", "require_protocol".
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['protocols' => ['http', 'https'], 'require_protocol' => true, 'required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function url($value, $options = null) {
		$options = $this->get_options($options, [
			'protocols'        => ['http', 'https'],
			'require_protocol' => true,
		]);

		$value = $this->esc_url_raw($value);
		$value = $this->trim_value(strval($value), $options);

		$required_or_optional = $this->validate_required_or_optional($value, $options);
		if ($required_or_optional) {
			return $required_or_optional;
		}

		$protocol_pattern = '(?:' . implode('|', $options['protocols']) . '):\/\/';

		if (!$options['require_protocol']) {
			$protocol_pattern = '(?:' . $protocol_pattern . ')?';
		}

		$pattern = "/^" . $protocol_pattern . "[-a-z\d@:%._+~#=]{1,256}\.[a-z\d()]{1,6}\b([-a-z\d()!@:%_+.~#?&\/=]*)$/i";

		if (preg_match($pattern, $value)) {
			return $this->result(true, $value);
		}

		return $this->result(false, $value, $this->get_message('url', $options['label']));
	}

	/**
	 * Проводит проверку значения на валидное значение цвета в CSS. Принимает HEX, rgb(), rgba().
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function color($value, $options = null) {
		$string_result = $this->string($value, $options);
		if (!$string_result->is_valid() || empty($string_result->get_value())) {
			return $string_result;
		}

		$value = $string_result->get_value();

		$pattern_hex = "/^#[\da-f]{3}$|^#[\da-f]{6}$/i";
		$pattern_rgb = "/^rgb\((0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d), *(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d), *(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d)\)$/i";
		$pattern_rgba = "/^rgba\((0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d), *(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d), *(0|255|25[0-4]|2[0-4]\d|1\d\d|0?\d?\d), *(0|0?\.\d\d?\d?|1(\.0)?)\)$/i";

		if (preg_match($pattern_hex, $value) || preg_match($pattern_rgb, $value) || preg_match($pattern_rgba, $value)) {
			return $this->result(
				true,
				preg_replace("/, */", ', ', $value)
			);
		}

		return $this->result(false, $value, $this->get_message('color', $options['label']));
	}

	/**
	 * Проводит проверку значения на строку и, в случае успеха, принудительно конвертирует его.
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function string($value, $options = null) {
		$options = $this->get_options($options);
		$value = $this->sanitize_text_field($value);
		$value = $this->trim_value(strval($value), $options);

		$required_or_optional = $this->validate_required_or_optional($value, $options);
		if ($required_or_optional) {
			return $required_or_optional;
		}

		return $this->result(true, $value);
	}

	/**
	 * Проводит проверку значения на строку со списком чисел, разделенных запятыми.
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function id_string_list($value, $options = null) {
		$string_result = $this->string($value, $options);
		if (!$string_result->is_valid() || empty($string_result->get_value())) {
			return $string_result;
		}

		$value = $string_result->get_value();
		$pattern = "/^\d+(?:( *, *\d+)+)?$/";

		if (!preg_match($pattern, $value)) {
			return $this->result(false, $value, $this->get_message('id_string_list', $options['label']));
		}

		$value = preg_replace("/ *, */", ',', $value);
		$value = implode(
			', ',
			array_unique(explode(',', $value))
		);

		return $this->result(true, $value);
	}

	/**
	 * Проводит проверку значения на валидный номер телефона.
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function phone($value, $options = null) {
		$string_result = $this->string($value, $options);
		if (!$string_result->is_valid() || empty($string_result->get_value())) {
			return $string_result;
		}

		$value = preg_replace("/[+() -]/", '', $string_result->get_value());
		$pattern = "/^\d{11}$/";

		if (!preg_match($pattern, $value)) {
			return $this->result(false, $string_result->get_value(), $this->get_message('phone', $options['label']));
		}

		$formatted_phone =
			'+' . $value[0] . '(' . $value[1] . $value[2] . $value[3] . ')' .
			' ' . $value[4] . $value[5] . $value[6] .
			'-' . $value[7] . $value[8] . $value[9] . $value[10];

		return $this->result(true, $formatted_phone);
	}

	/**
	 * Проводит проверку значения на валидный email.
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function email($value, $options = null) {
		$string_result = $this->string($value, $options);
		if (!$string_result->is_valid() || empty($string_result->get_value())) {
			return $string_result;
		}

		$value = sanitize_email($string_result->get_value());

		if (!is_email($value)) {
			return $this->result(false, $value, $this->get_message('email', $options['label']));
		}

		return $this->result(true, $value);
	}

	/**
	 * Проводит проверку значения на валидный номер ИНН.
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function inn($value, $options = null) {
		$string_result = $this->string($value, $options);
		if (!$string_result->is_valid() || empty($string_result->get_value())) {
			return $string_result;
		}

		$value = $string_result->get_value();
		$length = strlen($value);

		if (($length != 10 && $length != 12) || !preg_match("/^\d+$/", $value)) {
			return $this->result(false, $value, $this->get_message('inn', $options['label']));
		}

		$check_result = false;
		$check_digit = function($inn, $coefficients) {
			$n = 0;
			foreach ($coefficients as $i => $k) {
				$n += $k * (int)$inn[$i];
			}
			return $n % 11 % 10;
		};

		switch ($length) {
			case 10:
				$n10 = $check_digit($value, [2, 4, 10, 3, 5, 9, 4, 6, 8]);
				if ($n10 === (int)$value[9]) {
					$check_result = true;
				}
				break;
			case 12:
				$n11 = $check_digit($value, [7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);
				$n12 = $check_digit($value, [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]);
				if (($n11 === (int)$value[10]) && ($n12 === (int)$value[11])) {
					$check_result = true;
				}
				break;
		}

		if (!$check_result) {
			return $this->result(false, $value, $this->get_message('inn', $options['label']));
		}

		return $this->result(true, $value);
	}

	/**
	 * Проводит проверку значения на валидный номер ОГРНИП (для организаций).
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function ogrnip($value, $options = null) {
		$string_result = $this->string($value, $options);
		if (!$string_result->is_valid() || empty($string_result->get_value())) {
			return $string_result;
		}

		$value = $string_result->get_value();

		if (strlen($value) != 15 || !preg_match("/^\d+$/", $value)) {
			return $this->result(false, $value, $this->get_message('ogrnip', $options['label']));
		}

		$n15 = (int)substr(bcsub(substr($value, 0, -1), bcmul(bcdiv(substr($value, 0, -1), '13', 0), '13')), -1);

		if ($n15 !== (int)$value[14]) {
			return $this->result(false, $value, $this->get_message('ogrnip', $options['label']));
		}

		return $this->result(true, $value);
	}

	/**
	 * Проводит проверку значения на валидный номер ОГРН (для ИП).
	 *
	 * @param  mixed  $value    Значение.
	 * @param  array  $options  Опциональный массив с опциями. Значение по-умолчанию ['required' => true, 'trim' => true, 'sanitize' => true, 'label' => '', 'message' => null]
	 *
	 * @return Kristall_Integration_Validator_Result
	 */
	public function ogrn($value, $options = null) {
		$string_result = $this->string($value, $options);
		if (!$string_result->is_valid() || empty($string_result->get_value())) {
			return $string_result;
		}

		$value = $string_result->get_value();

		if (strlen($value) != 13 || !preg_match("/^\d+$/", $value)) {
			return $this->result(false, $value, $this->get_message('ogrn', $options['label']));
		}

		$n13 = (int)substr(bcsub(substr($value, 0, -1), bcmul(bcdiv(substr($value, 0, -1), '11', 0), '11')), -1);

		if ($n13 !== (int)$value[12]) {
			return $this->result(false, $value, $this->get_message('ogrn', $options['label']));
		}

		return $this->result(true, $value);
	}

	/*============================================================================
	 * Защищенные и приватные методы
	 ============================================================================*/

	/**
	 * Производит вливание ключей исходного массива в целевой только если ключ исходного массива присутствует в целевом. Модифицирует целевой массив.
	 *
	 * @param  array  $source  Исходный массив.
	 * @param  array  $target  Целевой массив для слияния.
	 *
	 * @return void
	 */
	private function merge_options($source, &$target) {
		if (is_array($source)) {
			foreach ($source as $option => $value) {
				if (isset($target[$option])) {
					$target[$option] = $value;
				}
			}
		}
	}

	private function get_options($options, $extra_options = null) {
		$v_options = [
			'required' => true,
			'trim'     => true,
			'sanitize' => true,
			'label'    => '',
			'message'  => null,
		];

		if (is_array($extra_options)) {
			$v_options = array_merge($v_options, $extra_options);
		}

		$this->merge_options($options, $v_options);

		return $v_options;
	}

	private function sanitize_text_field($str) {
		return isset($str) ? sanitize_text_field((string)$str) : '';
	}

	private function trim_value($value, $options) {
		if (is_string($value) && $options['trim']) {
			return trim($value);
		}

		return $value;
	}

	private function validate_required_or_optional($value, $options) {
		if ($this->is_empty($value)) {
			if ($options['required']) {
				return $this->result(
					false, $value, $this->get_message('required', $options['label'])
				);
			} else {
				return $this->result(true, $value);
			}
		}

		return false;
	}

	private function is_empty($value) {
		return !isset($value) || (is_string($value) && empty($value));
	}

	private function result($valid, $value, $message = null) {
		return new Kristall_Integration_Validator_Result($valid, $value, $message);
	}

	private function get_message($message_key, $label, $message_rewrite = null) {
		$template = $message_rewrite ?? $this->messages[$message_key];

		return str_replace('%s', $label, $template);
	}

	private function esc_url_raw($str) {
		return isset($str) ? esc_url_raw((string)$str) : '';
	}
}
