<?php
declare(strict_types=1);
namespace App\Replacer;

class Replacer {
	public function __construct(
		public readonly string $name,
		public readonly string $regex,
	)
	{
	}
}
