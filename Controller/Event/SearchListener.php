<?php
App::uses('CrudBaseEvent', 'Crud.Controller/Event');

/**
 * SearchListener
 *
 * Inject search conditions based on a get-argument search parameter
 *
 * i.e. if the request url is
 * 		/foos/index?q=bar
 *
 * The following conditions will be automatically injected:
 * 		'Foo.display_field LIKE' => "%bar%"
 *
 * display_field is dynamic. More specific conditions can be specified
 * by implementing the public function searchConditions in the model
 *
 */
class SearchListener extends CrudBaseEvent {

/**
 * _defaults
 *
 * @var array
 */
	protected $_defaults = array(
		'conditions' => null,
		'searchTerm' => null
	);

/**
 * Constructor
 *
 * Initializes default translations and merge them with
 * user supplied user configurations
 *
 * @param array $config
 * @return void
 */
	public function __construct($config = array()) {
		$this->_config = $this->_defaults;
		if ($config) {
			$this->config($config);
		}
	}

/**
 * Returns a list of all events that will fire in the controller during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
	public function implementedEvents() {
		return array(
			'Crud.init' => array('callable' => 'init'),
			'Crud.beforePaginate' => array('callable' => 'beforePaginate'),
			'Crud.setFlash' => array('callable' => 'setFlash'),
		);
	}

	public function init(CakeEvent $event) {
		if (is_null($this->_config['conditions'])) {
			$model = $event->subject->model;
			$conditions = array(
				$model->alias . '.' . $model->displayField . ' LIKE' => "{searchTerm}%"
			);
			$this->config('conditions', $conditions);
		}

		if (is_null($this->_config['searchTerm']) && isset($event->subject->request->query['q'])) {
			$this->config('searchTerm', $event->subject->request->query['q']);
		}
	}

/**
 * beforePaginate
 *
 * Before paginating, inject conditions for the search term in the url
 *
 * @param CakeEvent $event
 * @return void
 */
	public function beforePaginate(CakeEvent $event) {
		$searchTerm = $this->config('searchTerm');
		if (is_null($searchTerm)) {
			return;
		}

		$model = $event->subject->model;
		$alias = $model->alias;

		$paginator = $event->subject->Components->load('Paginator');
		if (isset($paginator->settings[$alias])) {
			$this->_addConditions($paginator->settings[$alias], $searchTerm, $model);
		} else {
			$this->_addConditions($paginator->settings, $searchTerm, $model);
		}
	}

/**
 * SetFlash Crud Event callback
 *
 * @throws CakeException if called with invalid args
 * @param CakeEvent $e
 * @return void
 */
	public function setFlash(CakeEvent $event) {
	}

/**
 * _addConditions
 *
 * @param array $paginate
 * @param string $searchTerm
 * @param object $model
 * @return void
 */
	protected function _addConditions(&$paginate, $searchTerm, $model) {
		$conditions = $this->config('conditions');
		if (is_callable($conditions)) {
			$return = $conditions($paginate, $searchTerm, $model);
			if ($return) {
				$paginate = $return;
			}
			return;
		}

		$replace = array('searchTerm' => $this->config('searchTerm'));
		$options = array('before' => '{', 'after' => '}');
		if (is_array($conditions)) {
			foreach($conditions as &$val) {
				$val = String::insert($val, $replace, $options);
			}
		} else {
			$conditions = String::insert($conditions, $replace, $options);
		}

		if ($conditions) {
			$paginate['conditions'][] = $conditions;
		}
	}
}
