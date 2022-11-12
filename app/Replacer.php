<?php
declare(strict_types=1);
namespace App;

class Replacer {
	public function __construct(
		public readonly string $name,
		public readonly string $regex,
	)
	{
	}
}
