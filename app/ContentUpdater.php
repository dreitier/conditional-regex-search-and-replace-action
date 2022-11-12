<?php
declare(strict_types=1);
namespace App;
use App\Events\GenericEvent;

class ContentUpdater 
{
	public function __construct(
		public readonly Variable $variable,
		public readonly array $replacers = [],
		public readonly VariableCollection $allVariables,
		public readonly ?object $logger = null,
	)
	{
	}

	public function updateFile($path, $newFilePath = null)
	{
		$content = file_get_contents($path);
		$content = $this->update($content);
		$targetFilePath = $newFilePath ?? $path;
		
		file_put_contents($targetFilePath, $content);
	}
	
	public function update(string $content): string
	{
		$contentInEdit = $content;
		
		foreach ($this->replacers as $replacer) {
			$this->logger?->info("      Replacing {$replacer->regex} with '{$this->variable->value}'");
		
			if (preg_match_all('/' . $replacer->regex . '/', $contentInEdit, $r)) {
				$totalMatches = sizeof($r[0]);
				
				for ($i = 0; $i < $totalMatches; $i++) {
					$lineFound = $r[0][$i];
					$newLine = $lineFound;
					
					foreach ($this->allVariables->items() as $ctx) {
						$variableName = $ctx->name;
						
						if (isset($r[$variableName])) {
							$newLine = str_replace($r[$variableName][$i], $ctx->value, $newLine);
							//GenericEvent::dispatch('content_updater.line_replaced', [$variableName, $ctx->value]);
						}
					}
					
					$contentInEdit = str_replace($lineFound, $newLine, $contentInEdit);
					
					$this->logger?->info("      Converted /$lineFound/ to /$newLine/");
				}
			}
		}
		
		return $contentInEdit;
	}
}