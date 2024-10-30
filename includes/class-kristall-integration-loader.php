<?php

/**
 * Регистрирует все экшны и фильтры
 */
class Kristall_Integration_Loader {
	/**
	 * Список зарегистрированных экшнов.
	 *
	 * @access   protected
	 * @var      array $actions Зарегистрированные экшны, которые быдут запускаться при загрузке плагина.
	 */
	protected $actions;

	/**
	 * Список зарегистрированных фильтров.
	 *
	 * @access   protected
	 * @var      array $filters Зарегистрированные фильтры, которые быдут запускаться при загрузке плагина.
	 */
	protected $filters;

	public function __construct() {
		$this->actions = [];
		$this->filters = [];
	}

	/**
	 * Добавляет новый экшн для регистрации
	 *
	 * @param string $hook          Имя экшна WordPress.
	 * @param object $component     Ссылка на инстанс объекта, в котором находится обработчик.
	 * @param string $callback      Имя обработчика в $component.
	 * @param int    $priority      Опционально. Приоритет выполнения. По-умолчанию 10.
	 * @param int    $accepted_args Опционально. Количество аргументов, передаваемых в $callback. По-умолчанию 1.
	 */
	public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
		$this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
	}

	/**
	 * Добавляет новый фильтр для регистрации
	 *
	 * @param string $hook          Имя фильтра WordPress.
	 * @param object $component     Ссылка на инстанс объекта, в котором находится обработчик.
	 * @param string $callback      Имя обработчика в $component.
	 * @param int    $priority      Опционально. Приоритет выполнения. По-умолчанию 10.
	 * @param int    $accepted_args Опционально. Количество аргументов, передаваемых в $callback. По-умолчанию 1.
	 */
	public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
		$this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
	}

	/**
	 * Регистрирует все экшны и фильтры.
	 */
	public function run() {
		foreach ($this->filters as $hook) {
			add_filter(
				$hook['hook'],
				[$hook['component'],
				 $hook['callback']],
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ($this->actions as $hook) {
			add_action(
				$hook['hook'],
				[$hook['component'],
				 $hook['callback']],
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}

	/**
	 * Вспомогательная функция для регистрации экшнов и хуков в одной коллекции.
	 *
	 * @access   private
	 *
	 * @param array  $hooks         Коллекция хуков для регистрации (действия или фильтры).
	 * @param string $hook          Имя фильтра для регистрации.
	 * @param object $component     Ссылка на инстанс объекта, в котором находится обработчик.
	 * @param string $callback      Имя обработчика в $component.
	 * @param int    $priority      Приоритет выполнения.
	 * @param int    $accepted_args Количество аргументов, передаваемых в $callback.
	 *
	 * @return   array                       Коллекция зарегистрированных экшнов и фильтров.
	 */
	private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
		$hooks[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];

		return $hooks;
	}
}
