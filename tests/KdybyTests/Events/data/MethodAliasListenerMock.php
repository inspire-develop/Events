<?php

namespace KdybyTests\Events;

class MethodAliasListenerMock implements \Kdyby\Events\Subscriber
{

	/**
	 * @var array
	 */
	public $calls = [];

	/**
	 * @return array
	 */
	public function getSubscribedEvents(): array
	{
		return [
			'Article::onDiscard' => 'customMethod',
		];
	}

	public function customMethod(EventArgsMock $args)
	{
		$args->calls[] = [__METHOD__, func_get_args()];
		$this->calls[] = [__METHOD__, func_get_args()];
	}

}
