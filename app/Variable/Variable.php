<?php
declare(strict_types=1);
namespace App\Variable;

class Variable
{
	public function __construct(
		public readonly string $name,
		public readonly string $value,
	)
	{
	}
}