<?php

App::uses('AppController', 'Controller');
App::uses('Controller', 'Controller');
App::uses('CakeEvent', 'Event');
App::uses('SearchListener', 'Crud.Controller/Event');
App::uses('ControllerTestCase', 'TestSuite');
App::uses('Icecream', 'Frisko.Model');

class SearchListenerTestController extends Controller {

	public $components = array('Crud.Crud');
}

class SearchListenerTest extends ControllerTestCase {

/**
 * setupBeforeClass
 *
 */
	public static function setupBeforeClass() {
		$class = new ReflectionClass('SearchListener');
		$property = $class->getProperty('_config');
		$property->setAccessible(true);
	}

	public function setUp() {
		parent::setUp();

		$this->SearchListener = new SearchListener();
	}

	public function tearDown() {
		parent::tearDown();

		unset($this->SearchListener);
	}

/**
 * Test the default config matches the expected
 *
 * @return void
 */
	public function testDefaultConfig() {
		$config = $this->SearchListener->config();
		$expected = array(
			'conditions' => null,
			'searchTerm' => null
		);

		$this->assertEquals($expected, $config);
	}

	public function testCanOverrideConditions() {
		$override = array(
			'conditions' => array(
				'Some.model' => '%{searchTerm}%'
			)
		);
		$config = $this->SearchListener->config($override);

		$expected = array(
			'conditions' => array(
				'Some.model' => '%{searchTerm}%'
			),
			'searchTerm' => null
		);
		$this->assertEquals($expected, $config);
	}

	public function testCanOverrideConfigSearchTerm() {
		$override = array(
			'searchTerm' => 'abc'
		);
		$config = $this->SearchListener->config($override);

		$expected = array(
			'conditions' => null,
			'searchTerm' => 'abc'
		);
		$this->assertEquals($expected, $config);
	}

	public function testInititializeConditions() {
		$subject = $this->generate('SearchListenerTest', array());
		$subject->model = ClassRegistry::init(array(
			'class' => 'Post',
			'name' => 'Post',
			'table' => false
		));
		$subject->model->displayField = 'title';

		$Event = new CakeEvent('Crud.init', $subject);

		$this->SearchListener->init($Event);

		$expected = array(
			'conditions' => array(
				'Post.title LIKE' => '{searchTerm}%'
			),
			'searchTerm' => null
		);

		$actual = $this->_getPropertyValue($this->SearchListener, '_config');
		$this->assertSame($expected, $actual);
	}

	public function testInititializeFromRequest() {
		$subject = $this->generate('SearchListenerTest', array());
		$subject->model = ClassRegistry::init(array(
			'class' => 'Post',
			'name' => 'Post',
			'table' => false
		));
		$subject->model->displayField = 'title';
		$subject->request = new CakeRequest('/?q=crud');

		$Event = new CakeEvent('Crud.init', $subject);

		$this->SearchListener->init($Event);

		$expected = array(
			'conditions' => array(
				'Post.title LIKE' => '{searchTerm}%'
			),
			'searchTerm' => 'crud'
		);

		$actual = $this->_getPropertyValue($this->SearchListener, '_config');
		$this->assertSame($expected, $actual);
	}

	public function testBeforePaginateBasic() {
		$this->SearchListener->config('searchTerm', 'foo');

		$subject = $this->generate('SearchListenerTest', array());
		$subject->model = ClassRegistry::init(array(
			'class' => 'Post',
			'name' => 'Post',
			'table' => false
		));
		$subject->model->displayField = 'title';

		$Event = new CakeEvent('Crud.init', $subject);
		$this->SearchListener->init($Event);
		$Event = new CakeEvent('Crud.beforePaginate', $subject);
		$this->SearchListener->beforePaginate($Event);

		$expected = array(
			array(
				'Post.title LIKE' => 'foo%'
			)
		);
		$actual = $subject->Components->load('Paginator')->settings['conditions'];
		$this->assertSame($expected, $actual);
	}

	public function testBeforePaginateNoClobber() {
		$this->SearchListener->config('searchTerm', 'foo');

		$subject = $this->generate('SearchListenerTest', array());
		$subject->model = ClassRegistry::init(array(
			'class' => 'Post',
			'name' => 'Post',
			'table' => false
		));
		$subject->model->displayField = 'title';
		$subject->Components->load('Paginator')->settings['conditions'][] = array(
			'Post.title LIKE' => '%something%'
		);

		$Event = new CakeEvent('Crud.init', $subject);
		$this->SearchListener->init($Event);
		$Event = new CakeEvent('Crud.beforePaginate', $subject);
		$this->SearchListener->beforePaginate($Event);

		$expected = array(
			array(
				'Post.title LIKE' => '%something%'
			),
			array(
				'Post.title LIKE' => 'foo%'
			)
		);
		$actual = $subject->Components->load('Paginator')->settings['conditions'];
		$this->assertSame($expected, $actual);
	}

	public function testBeforePaginateClosure() {
		$subject = $this->generate('SearchListenerTest', array());
		$subject->model = ClassRegistry::init(array(
			'class' => 'Post',
			'name' => 'Post',
			'table' => false
		));
		$subject->model->displayField = 'title';

		$this->SearchListener->config('conditions', function($paginate, $searchTerm, $model) {
			$paginate['conditions'][] = "anything at all with '$searchTerm' in it";
			return $paginate;
		});
		$this->SearchListener->config('searchTerm', 'abc');

		$Event = new CakeEvent('Crud.init', $subject);
		$this->SearchListener->init($Event);
		$Event = new CakeEvent('Crud.beforePaginate', $subject);
		$this->SearchListener->beforePaginate($Event);

		$expected = array(
			'page' => 1,
			'limit' => 20,
			'maxLimit' => 100,
			'paramType' => 'named',
			'conditions' => array(
				'anything at all with \'abc\' in it'
			)
		);
		$actual = $subject->Components->load('Paginator')->settings;
		$this->assertSame($expected, $actual);
	}

	protected function _getPropertyValue($object, $property) {
		$class = new ReflectionClass($object);
		$property = $class->getProperty($property);
		$property->setAccessible(true);
		return $property->getValue($object);
	}

}
