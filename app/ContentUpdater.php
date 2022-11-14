<?php
declare(strict_types=1);
namespace App;
use App\Events\LineModifiedEvent;
use App\Events\LogEvent;
use App\Variable\Variable;
use App\Variable\Collection as VariableCollection;

class ContentUpdater 
{
	public function __construct(
		public readonly Variable $variable,
		public readonly array $replacers = [],
		public readonly VariableCollection $allVariables,
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
			LogEvent::info("      Replacing '/{$replacer->regex}/' with '{$this->variable->value}'");
		
			if (preg_match_all('/' . $replacer->regex . '/', $contentInEdit, $r)) {
				$totalMatches = sizeof($r[0]);
				
				for ($i = 0; $i < $totalMatches; $i++) {
					$lineFound = $r[0][$i];
					$newLine = $lineFound;
					
					foreach ($this->allVariables->items() as $ctx) {
						$variableName = $ctx->name;
						
						if (isset($r[$variableName])) {
							$oldValue = $r[$variableName][$i];
							$newValue = $ctx->value;
							$newLine = str_replace($oldValue, $newValue, $newLine);
							
							LineModifiedEvent::dispatch($variableName, $oldValue, $newValue);
						}
					}
					
					$contentInEdit = str_replace($lineFound, $newLine, $contentInEdit);
					
					LogEvent::info("      Converted line '$lineFound' to '$newLine'");
				}
			}
		}
		
		return $contentInEdit;
	}
}