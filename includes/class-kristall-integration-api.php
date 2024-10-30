<?php

/**
 * Класс ошибки API-запроса
 */
class Kristall_Integration_API_Error {
  public $status = 'error';
  private $message;
  private $code;
  private $data;

  public function __construct($message, $code, $data = null) {
    $this->message = $message;
    $this->code = $code;
    $this->data = $data;  
  }

  /**
   * Возвращает код ответа от сервера
   * 
   * @return int
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * Возвращает данные от сервера
   * 
   * @return mixed
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Возвращает сообщение об ошибке
   * 
   * @param bool|null $prepend_code Добавить код ошибки к сообщению.
	 *
	 * @return string
   */
  public function getMessage($prepend_code = false) {
    return ($prepend_code ? '[' . $this->code . '] ' : '') . $this->message;
  }
}

/**
 * Класс для запросов к API кристалла
 */
class Kristall_Integration_API {
  private $api_url;
  private $api_key;

  public function __construct($api_url, $api_key) {
    $this->api_url = preg_replace("~/+$~", '', $api_url ?? '') . '/api.php';
    $this->api_key = urlencode($api_key);
  }

  /**
   * Проверяет соединение и корректность API-ключа
   */
  public function check_connection() {
    $settings_error = $this->has_settings_error();

    if ($settings_error) {
      return $settings_error;
    }

    return $this->make_request('', null, null);
  }

  /**
   * Делает API-запрос к МБС Кристалл
   * 
   * @param string $mod Наименование модуля, например "products".
   * @param string $method Путь к методу API, например "wcOrder.verifyData".
   * @param array|null $data Данные для отправки на сервер.
   * @param string|null $format Формат ответа.
   * 
   * @return mixed
   */
  public function request($mod, $method, $data = null, $format = null) {
    $settings_error = $this->has_settings_error();

    if ($settings_error) {
      return $settings_error;
    }

    $api_endpoint = 'mod=' . $mod . '&method=' . $method;
    return $this->make_request($api_endpoint, $data, $format);
  }

  /*============================================================================
   * Приватные методы
	 ============================================================================*/

  /**
   * Делает API-запрос с помощью Curl
   */
  private function make_request($url = '', $data = null, $format = null) {
    $url = $this->api_url . '?api_key=' . $this->api_key . ($format ? '&format=' . $format : '') . ($url ? '&' . $url : '');
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // curl_setopt($ch, CURLOPT_HTTP200ALIASES, [400]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, !empty($data) && is_array($data) ? $data : []);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($http_code == 0) {
      return new Kristall_Integration_API_Error(
        esc_html__('Не возможно соединиться с сервером API.', 'kristall-integration'),
        $http_code
      );
    }

    try {
      $data = $this->decode_response($result, $format);
    } catch (Exception $e) {
      $data = new stdClass;
    }

    if ($http_code >= 400 || (isset($data->code) && is_numeric($data->code) && (int)$data->code >= 400)) {
      if (isset($data->code) && is_numeric($data->code)) {
        $http_code = (int)$data->code;
      }

      return new Kristall_Integration_API_Error(
        (isset($data->message) ? esc_html($data->message) : esc_html__('Произошла непредвиденная ошибка при выполнении запроса к серверу API.', 'kristall-integration')),
        $http_code
      );
    }

    return $this->success($data);
  }

  /**
   * Декодирует ответ от сервера
   * 
   * @param  string  $result  Строка с данными для декодирования.
   * @param  string  $format  Формат входных данных.
   * 
   * @return mixed
   */
  private function decode_response($result, $format) {
    return json_decode($result, false);
  }

  /**
   * Проверяет корректность настроек
   * 
   * @return  Kristall_Integration_API_Error|false
   */
  private function has_settings_error() {
    if (empty($this->api_url)) {
      return new Kristall_Integration_API_Error(esc_html__('Ошибка API: URL-aдрес Кристалла не установлен.', 'kristall-integration'), 500);
    }

    if (empty($this->api_key)) {
      return new Kristall_Integration_API_Error(esc_html__('Ошибка API: API-ключ не установлен.', 'kristall-integration'), 500);
    }

    return false;
  }

  private function success($data = null) {
    $result = new stdClass;
    $result->status = 'ok';
    $result->data = $data;
    return $result;
  }
}
