<?php
declare(strict_types=1);
namespace App;

class EnvironmentVariable
{
	public function __construct(
		public readonly string $name, 
		public readonly string $friendlyName,
		public readonly string $value,
	)
	{
	}
	
	/**
	 * Converts the given variable name to upper-case and tries to find that environment variable name.
	 *
	 * @param string $friendlyName Variable name in lower-case writte name.
	 * @return VariableContext
	 * @throws Exception if environment variable is empty
	 */
	public static function fromFriendlyName(string $friendlyName): EnvironmentVariable
	{
		$friendlyName = trim($friendlyName);
		$name = strtoupper($friendlyName);
		
		$value = self::envVar($name);
		
		if (empty($value)) {
			throw new \Exception("value of environment variable $name is empty");
		}
		
		return new EnvironmentVariable($name, $friendlyName, $value);
	}

	public static function fromFriendlyNames(array $friendlyNames, ?callable $onMissing = null): array
	{
		$r = [];
		
		foreach ($friendlyNames as $friendlyName) {
			try {
				$r[] = self::fromFriendlyName($friendlyName);
			}
			catch (\Exception $e) {
				if ($onMissing) {
					$onMissing($friendlyName, $e);
				}
			}
		}
		
		return $r;		
	}

	public static function envVar($key, ?string $default = ''): ?string
	{
		$r = trim(env($key) ?? '');
		
		if (empty($r)) {
			return $default;
		}
		
		return $r;
	}
	
	public static function envVarAsArray($key, string $separator = ',', ?array $default = null): ?array
	{
		$value = self::envVar($key, null);
		
		if ($value === null) {
			return $default;
		}
		
		return Util::trimmedExplode($separator, $value);
	}
}