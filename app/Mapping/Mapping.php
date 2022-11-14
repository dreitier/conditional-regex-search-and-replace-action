<?php
declare(strict_types=1);
namespace App\Mapping;
use App\Variable\Variable;

class Mapping
{
	public function __construct(
		public readonly Variable $variable, 
		public readonly string $regexToMatchValue, 
		public readonly string $glob,
		public readonly array $replacers = []
	)
	{
	}
	
	public function matches(): int
	{
		return preg_match("/" . $this->regexToMatchValue . "/", $this->variable->value);
	}
	
	public function isOfType($type): bool 
	{
		return trim($type) == trim($this->variable->name);
	}
}