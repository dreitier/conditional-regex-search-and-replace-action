<?php
declare(strict_types=1);
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;

class LineModifiedEvent
{
	use Dispatchable;

	public function __construct(
		public readonly string $variableName, 
		public readonly string $oldValue,
		public readonly string $newValue,
	)
	{
	}
}