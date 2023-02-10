<?php
declare(strict_types=1);

namespace App\Replacer;

use App\EnvironmentVariable;

class Collection
{
    const AUTODETECT_SUFFIX = '_REGEX';

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
                yield new Replacer(strtolower($candidate), $regexForReplacer);
            }
        }
    }

    public function locateAndMerge(array $possibleEnvironmentVariableNames)
    {
        foreach (self::fromEnvironment($possibleEnvironmentVariableNames) as $replacer) {
            $this->add($replacer);
        }
    }

    private function mergeFromEnvironmentVariables($environmentVariables = [])
    {
        $r = collect($environmentVariables)
            ->map(fn($item) => new Replacer($item->friendlyName, $item->value))
            ->toArray();

        foreach ($r as $variable) {
            $this->add($variable);
        }

        return $this;
    }

    public static function create(array $customRegexNames = [], $autodetectFromEnvironment = true): Collection
    {
        $r = new static();

        if ($autodetectFromEnvironment) {
            $regexesFromEnvironment = EnvironmentVariable::findBySuffix(self::AUTODETECT_SUFFIX, true, false);
            $r->mergeFromEnvironmentVariables($regexesFromEnvironment);
        }

        $r->locateAndMerge($customRegexNames);

        return $r;
    }
}
