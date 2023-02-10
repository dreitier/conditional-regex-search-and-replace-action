<?php
declare(strict_types=1);
namespace App\Mapping;
use App\Variable\Collection as VariableCollection;
use App\Replacer\Collection as ReplacerCollection;
use App\EnvironmentVariable;
use App\Util;

class Collection
{
	const MAPPING_SEPARATOR = "{THEN_UPDATE_FILES}";
	const MATCHER_SEPARATOR = "{OR}";
	const NEXT_MAPPING_SEPARATOR = "{NEXT_MAPPING}";
    const REPLACER_SEPARATOR = "{AND}";

	private array $items = [];

	public function __construct(
		public readonly VariableCollection $variables,
		public readonly ReplacerCollection $replacers,
	)
	{
	}

	public function items(): array
	{
		return $this->items;
	}

	public function upsert(string $string)
	{
		$lines = Util::trimmedExplode(self::NEXT_MAPPING_SEPARATOR, $string);

		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line)) {
				continue;
			}

			$this->items = array_merge($this->items, $this->parseLine($line));
		}
	}

	private function extractMatchers($matchersDefinition): array
	{
		$r = collect(Util::trimmedExplode(self::MATCHER_SEPARATOR, $matchersDefinition))->map(function($item) {
			$assign = Util::trimmedExplode("==", $item);

			if (sizeof($assign) == 1) {
				$assign[] = ".*";
			}

			$variableName = $assign[0];
			$regexVariableMatcher = $assign[1];

			$referencedVariable = $this->variables->get($variableName);

			if (!$referencedVariable) {
				throw new \Exception("You are referencing the variable '{$variableName}'. This variable is not defined.");
			}

			return [$referencedVariable, $regexVariableMatcher];
		});

		return $r->toArray();
	}

	private function extractReplacers(string $replacersDefinition): array
	{
		$r = collect(Util::trimmedExplode(self::REPLACER_SEPARATOR, $replacersDefinition))->map(function($item) {
			$assign = Util::trimmedExplode("=", $item);

			if (sizeof($assign) == 1) {
				$assign[] = "*";
			}

			$glob = $assign[0];
			$referencedRegexReplacers = Util::trimmedExplode("&", $assign[1]);
			$usedRegexReplacers = [];

			foreach ($referencedRegexReplacers as $referencedRegexReplacer) {
				if ($referencedRegexReplacer == "*") {
					$usedRegexReplacers = $this->replacers->items();
					break;
				}

				$resolvedRegexReplacer = $this->replacers->get($referencedRegexReplacer);

				if (!$resolvedRegexReplacer) {
					throw new \Exception("The referenced regex '{$referencedRegexReplacer}' for mapping of glob '{$glob}' does not exist");
				}

				$usedRegexReplacers[] = $resolvedRegexReplacer;
			}

			return [$assign[0], $usedRegexReplacers];
		});

		return $r->toArray();
	}

	public function parseLine($line)
	{
		$r = [];

		$assignments = Util::trimmedExplode(self::MAPPING_SEPARATOR, $line);

		if (sizeof($assignments) == 1) {
			$assignments[] = "**=*";
		}

		$matchersDefinition = $assignments[0];
		$matchers = $this->extractMatchers($matchersDefinition);

		$replacersDefinition = $assignments[1];
		$replacers = $this->extractReplacers($replacersDefinition);

		foreach ($matchers as $matcher) {
			foreach ($replacers as $replacer) {
				$item = new Mapping(
					$matcher[0] /* Variable */,
					$matcher[1] /* string: regexMatcher */,
					$replacer[0] /* glob */,
					$replacer[1] /* referenced regex replacers */
				);

				$r[] = $item;
			}
		}

		return $r;
	}
}
