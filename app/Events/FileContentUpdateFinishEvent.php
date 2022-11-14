<?php
declare(strict_types=1);
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;

class FileContentUpdateFinishEvent
{
	use Dispatchable;

	public function __construct(
		public readonly string $originalFile, 
		public readonly string $targetFile,
	)
	{
	}
}