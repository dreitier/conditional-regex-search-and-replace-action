<?php
declare(strict_types=1);
namespace App\Replacer;
use App\EnvironmentVariable;

class Collection
{
	private array $items = [];
	
	public function add(Replacer $replacer): Collection
	{
		$this->items[$replacer->name] = $replacer;
		return $this;
	}
	
	public function get($name): ?Replacer
	{
		return $this->items[$name] ?? null;
	}
	
	public function items(): array
	{
		return array_values($this->items);
	}
	
	public static function fromEnvironment(array $possibleEnvironmentVariableNames)
	{
		foreach ($possibleEnvironmentVariableNames as $candidate) {
			$regexForReplacer = EnvironmentVariable::envVar(strtoupper($candidate), null);
			
			if ($regexForReplacer) {
				yield new Replacer($candidate, $regexForReplacer);
			}
		}
	}
	
	public function mergeFromEnvironment(array $possibleEnvironmentVariableNames) 
	{
		foreach (self::fromEnvironment($possibleEnvironmentVariableNames) as $replacer) {
			$this->add($replacer);
		}
	}
}