<?php
declare(strict_types=1);
namespace App;

use Illuminate\Support\Str;

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
	 * @param string $friendlyName Variable name in lower-case written name.
	 * @return EnvironmentVariable
	 * @throws \Exception if environment variable is empty
	 */
	public static function fromFriendlyName(string $friendlyName): EnvironmentVariable
	{
		$friendlyName = trim(strtolower($friendlyName));
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

    public static function findBySuffix(string $suffix, $toLower = true, $removeSuffix = true): array
    {
        $r = [];
        $environmentVariables = getenv();
        $ucSuffix = strtoupper($suffix);

        foreach ($environmentVariables as $environmentVariable => $value) {
            $ucEnvironmentVariable = strtoupper($environmentVariable);

            if (Str::endsWith($ucEnvironmentVariable, $ucSuffix)) {
                $friendlyName = $ucEnvironmentVariable;

                if ($removeSuffix) {
                    $friendlyName = str_replace($ucSuffix, '', $ucEnvironmentVariable);
                }

                if ($toLower) {
                    $friendlyName = strtolower($friendlyName);
                }

                $r[] = new static ($environmentVariable, $friendlyName, $value);
            }
        }

        return $r;
    }
}
