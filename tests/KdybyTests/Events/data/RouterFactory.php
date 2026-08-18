<?php

namespace KdybyTests\Events;

class RouterFactory
{

	/**
	 * @return \KdybyTests\Events\SampleRouter
	 */
	public function createRouter(): SampleRouter
	{
		return new SampleRouter('nemam');
	}

}
