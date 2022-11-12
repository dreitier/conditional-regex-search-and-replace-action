<?php
declare(strict_types=1);
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;

class GenericEvent
{
	use Dispatchable;

	public function __construct(
		public readonly string $eventName, 
		public readonly array $args = [],
	)
	{
	}
}