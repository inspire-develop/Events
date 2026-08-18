<?php

namespace KdybyTests\Events;

class ArrayPropertyMock
{

	use \Nette\SmartObject;

	/**
	 * Strictly array-typed property - cannot hold an Event instance, so the event has to be wrapped.
	 *
	 * @var array|callable[]
	 */
	public array $onArrayOnly = [];

	/**
	 * @var array|callable[]|\Kdyby\Events\Event
	 */
	public array|\Kdyby\Events\Event $onUnion = [];

	public function __construct()
	{
		// a listener registered before the container binds the event to the property
		$this->onArrayOnly[] = static function (\stdClass $log): void {
			$log->preRegisteredCalled = TRUE;
		};
	}

}
