<?php

/**
 * Test: Kdyby\Events\Extension.
 *
 * @testCase
 */

namespace KdybyTests\Events;

use Kdyby\Events\DI\EventsExtension;
use Kdyby\Events\Event;
use Kdyby\Events\EventManager;
use Kdyby\Events\IExceptionHandler;
use Nette\Application\Application;
use Nette\Configurator;
use Nette\Utils\Arrays;
use Nette\Security\User;
use ReflectionProperty;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

class ExtensionTest extends \Tester\TestCase
{

	/**
	 * @param string $configFile
	 * @return \Nette\DI\Container
	 */
	public function createContainer($configFile)
	{
		$config = new Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5($configFile)]]);
		EventsExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		return $config->createContainer();
	}

	public function testRegisterListeners()
	{
		$container = $this->createContainer('subscribers');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);
		Assert::equal(2, count($manager->getListeners()));
	}

	public function testRegisterListenersWithSameArguments()
	{
		$container = $this->createContainer('subscribersWithSameArgument');
		$manager = $container->getService('events.manager');

		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);
		Assert::same(['onFoo'], array_keys($manager->getListeners()));
		Assert::count(2, $manager->getListeners('onFoo'));
	}

	public function testValidateDirect()
	{
		Assert::exception(function () {
			$this->createContainer('validate.direct');
		}, \Nette\Utils\AssertionException::class, 'Please, do not register listeners directly to service @events.manager. %a%');
	}

	public function testValidateMissing()
	{
		try {
			$this->createContainer('validate.missing');
			Assert::fail('Expected exception');

		} catch (\Nette\Utils\AssertionException $e) {
			Assert::match(
				'Please, specify existing class for service \'events.subscriber.%a%\' explicitly, and make sure, that the class exists and can be autoloaded.',
				$e->getMessage()
			);

		} catch (\Nette\DI\ServiceCreationException $e) {
			Assert::match("Service 'events.subscriber.0': Class 'NonExistingClass_%a%' not found%a?%.", $e->getMessage());

		} catch (\Exception $e) {
			Assert::fail($e->getMessage());
		}
	}

	public function testValidateFake()
	{
		Assert::exception(function () {
			$this->createContainer('validate.fake');
		}, \Nette\Utils\AssertionException::class, 'Subscriber @events.subscriber.%a% doesn\'t implement Kdyby\Events\Subscriber.');
	}

	public function testValidateInvalid()
	{
		Assert::exception(function () {
			$this->createContainer('validate.invalid');
		}, \Nette\Utils\AssertionException::class, 'Event listener KdybyTests\Events\FirstInvalidListenerMock::onFoo() is not implemented.');
	}

	public function testValidateInvalid2()
	{
		Assert::exception(function () {
			$this->createContainer('validate.invalid2');
		}, \Nette\Utils\AssertionException::class, 'Event listener KdybyTests\Events\SecondInvalidListenerMock::onBar() is not implemented.');
	}

	/**
	 * Properties that accept an Event hold it directly; strictly array-typed ones (Nette 3.2+ lifecycle
	 * hooks) hold it wrapped in an array. Both are a bound event.
	 *
	 * @param \Kdyby\Events\Event|array $property
	 * @return \Kdyby\Events\Event
	 */
	private function assertBoundEvent($property, $expectedName)
	{
		if (is_array($property)) {
			Assert::count(1, $property);
			$property = reset($property);
		}

		Assert::type(Event::class, $property);
		Assert::same($expectedName, $property->getName());

		return $property;
	}

	public function testAutowire()
	{
		$container = $this->createContainer('autowire');

		$app = $container->getService('application');
		/** @var \Nette\Application\Application $app */
		$this->assertBoundEvent($app->onStartup, Application::class . '::onStartup');
		$this->assertBoundEvent($app->onRequest, Application::class . '::onRequest');
		$this->assertBoundEvent($app->onResponse, Application::class . '::onResponse');
		$this->assertBoundEvent($app->onError, Application::class . '::onError');
		$this->assertBoundEvent($app->onShutdown, Application::class . '::onShutdown');

		// not all properties are affected
		Assert::true(is_bool($app->catchExceptions));
		Assert::true(!is_object($app->errorPresenter));

		$user = $container->getService('user');
		/** @var \Nette\Security\User $user */
		$this->assertBoundEvent($user->onLoggedIn, User::class . '::onLoggedIn');
		$this->assertBoundEvent($user->onLoggedOut, User::class . '::onLoggedOut');
	}

	public function testAutowireArrayTypedProperty()
	{
		$container = $this->createContainer('arrayProperty');

		/** @var \KdybyTests\Events\ArrayPropertyMock $mock */
		$mock = $container->getService('arrayPropertyMock');

		// the property is strictly typed as array, so it must stay an array holding the invokable Event
		Assert::true(is_array($mock->onArrayOnly));
		Assert::count(1, $mock->onArrayOnly);
		Assert::type(Event::class, $mock->onArrayOnly[0]);
		Assert::same(ArrayPropertyMock::class . '::onArrayOnly', $mock->onArrayOnly[0]->getName());

		// a property that accepts Event is still bound directly
		Assert::type(Event::class, $mock->onUnion);
		Assert::same(ArrayPropertyMock::class . '::onUnion', $mock->onUnion->getName());

		// listeners registered before the binding must survive it and still be invoked
		$log = new \stdClass();
		$log->preRegisteredCalled = FALSE;
		Arrays::invoke($mock->onArrayOnly, $log);
		Assert::true($log->preRegisteredCalled);
	}

	public function testInherited()
	{
		$container = $this->createContainer('inherited');

		/** @var \KdybyTests\Events\LeafClass $leafObject */
		$leafObject = $container->getService('leaf');

		Assert::true($leafObject->onCreate instanceof Event);
		Assert::same(LeafClass::class . '::onCreate', $leafObject->onCreate->getName());

		$leafObject->create();

		/** @var \KdybyTests\Events\InheritSubscriber $subscriber */
		$subscriber = $container->getService('subscriber');

		/** @var \KdybyTests\Events\SecondInheritSubscriber $subscriber */
		$subscriber2 = $container->getService('subscriber2');

		Assert::same([
			LeafClass::class . '::onCreate' => 2,
			// not subscribed for middle class
		], $subscriber->eventCalls);

		Assert::same([
			LeafClass::class . '::onCreate' => 1,
			// not subscribed for middle class
		], $subscriber2->eventCalls);
	}

	public function testOptimize()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$bazArgs = new EventArgsMock();
		$manager->dispatchEvent('onFoo', $bazArgs);
		Assert::false($container->isCreated('foo'));
		Assert::true($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('onFoo')));

		$bazArgsSecond = new EventArgsMock();
		$manager->dispatchEvent('App::onFoo', $bazArgsSecond);
		Assert::same(1, count($manager->getListeners('App::onFoo')));

		$baz = $container->getService('baz');
		/** @var \KdybyTests\Events\NamespacedEventListenerMock $baz */
		$bar = $container->getService('bar');
		/** @var \KdybyTests\Events\EventListenerMock $bar */

		Assert::same([
			[EventListenerMock::class . '::onFoo', [$bazArgs]],
		], $bar->calls);

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$bazArgsSecond]],
		], $baz->calls);
	}

	public function testOptimizeDispatchNamespaceFirst()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$bazArgs = new EventArgsMock();
		$manager->dispatchEvent('App::onFoo', $bazArgs);
		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::true($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('App::onFoo')));

		$baz = $container->getService('baz');
		/** @var \KdybyTests\Events\NamespacedEventListenerMock $baz */

		Assert::same([
			[NamespacedEventListenerMock::class . '::onFoo', [$bazArgs]],
		], $baz->calls);
	}

	public function testOptimizeStandalone()
	{
		$container = $this->createContainer('optimize');
		$manager = $container->getService('events.manager');
		/** @var \Kdyby\Events\EventManager $manager */
		Assert::true($manager instanceof EventManager);

		Assert::false($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		$foo = new FooMock();
		$bazArgs = new StartupEventArgs($foo, 123);
		$manager->dispatchEvent('onStartup', $bazArgs);
		Assert::true($container->isCreated('foo'));
		Assert::false($container->isCreated('bar'));
		Assert::false($container->isCreated('baz'));
		Assert::same(1, count($manager->getListeners('onStartup')));

		/** @var \KdybyTests\Events\NamespacedEventListenerMock $baz */
		$baz = $container->getService('foo');

		Assert::same([
			[LoremListener::class . '::onStartup', [$bazArgs]],
		], $baz->calls);
	}

	public function testExceptionHandler()
	{
		$container = $this->createContainer('exceptionHandler');
		$manager = $container->getService('events.manager');

		// getter not needed, so hack it via reflection
		$rp = new ReflectionProperty(EventManager::class, 'exceptionHandler');
		$handler = $rp->getValue($manager);

		Assert::true($handler instanceof IExceptionHandler);
	}

	public function testAutowireAlias()
	{
		$container = $this->createContainer('alias');
		Assert::same($container->getService('alias'), $container->getService('application'));
	}

	public function testFactoryAndAccessor()
	{
		$container = $this->createContainer('factory.accessor');

		$foo = $container->getService('foo');
		Assert::type(Event::class, $foo->onBar);

		$fooAccessor = $container->getService('fooAccessor');
		$foo2 = $fooAccessor->get();
		Assert::same($foo, $foo2);

		$fooFactory = $container->getService('fooFactory');
		$foo3 = $fooFactory->create();
		Assert::type(Event::class, $foo3->onBar);
		Assert::notSame($foo, $foo3);
	}

	public function testGlobalDispatchFirst()
	{
		$container = $this->createContainer('globalDispatchFirst');
		$container->getService('events.manager');

		$mock = $container->getService('dispatchOrderMock');
		Assert::true($mock->onGlobalDispatchFirst->globalDispatchFirst);
		Assert::false($mock->onGlobalDispatchLast->globalDispatchFirst);
		Assert::true($mock->onGlobalDispatchDefault->globalDispatchFirst);
	}

	public function testGlobalDispatchLast()
	{
		$container = $this->createContainer('globalDispatchLast');
		$container->getService('events.manager');

		$mock = $container->getService('dispatchOrderMock');
		Assert::true($mock->onGlobalDispatchFirst->globalDispatchFirst);
		Assert::false($mock->onGlobalDispatchLast->globalDispatchFirst);
		Assert::false($mock->onGlobalDispatchDefault->globalDispatchFirst);
	}

}

(new ExtensionTest())->run();
