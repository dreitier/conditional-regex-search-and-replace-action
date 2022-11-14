<?php
declare(strict_types=1);
namespace App;
use PHLAK\Splat\Glob;
use App\Events\FileContentUpdateBeginEvent;
use App\Events\FileContentUpdateFinishEvent;
use App\Events\LogEvent;
use App\Mapping\Collection as MappingCollection;
use App\Variable\Collection as VariableCollection;
use App\Events\AfterAllFilesProcessed;

/**
 * Worker horse
 */
class UpdateEnvironments
{
	public function __construct(
		public readonly MappingCollection $mappings,
		public readonly VariableCollection $variables,
		public readonly string $baseDirectory,
		public readonly string $suffixOfUpdatedFile = '',
	)
	{
	}
	
	/**
	 * For each mapping, provided variable and assigned glob the replacement of files will be done.
	 * @param $updater optional callable when a custom update logic is required. Makes it easier for testing purposes.
	 */
	public function process(?callable $updater = null)
	{
		$fileProcessingListener = (new FileProcessingListener())->listen();
		
		if (!$updater) {
			$updater = function(ContentUpdater $contentUpdater, string $absolutePathToFile) {
				$targetFile = $absolutePathToFile . $this->suffixOfUpdatedFile;
				
				FileContentUpdateBeginEvent::dispatch($absolutePathToFile, $targetFile);
				$contentUpdater->updateFile($absolutePathToFile, $targetFile);
				FileContentUpdateFinishEvent::dispatch($absolutePathToFile, $targetFile);
			};
		}
			
		foreach ($this->mappings->items() as $mapping) {
			LogEvent::info("Checking provided variable '{$mapping->variable->name}' with value '{$mapping->variable->value}' against regex '/{$mapping->regexToMatchValue}/':");
			
			if (!$mapping->matches()) {
				LogEvent::info("  Value does not match match");
				continue;
			}
			
			$files = $this->findFiles($mapping->glob);
			$totalFoundFiles = sizeof($files);
			LogEvent::info("  We have a match! Total globbed files for '{$mapping->glob}': {$totalFoundFiles}");
			
			if (!$totalFoundFiles) {
				LogEvent::warn("  Skipping, as no files has been found for glob");
				continue;
			}
			
			$contentUpdater = new ContentUpdater(
				$mapping->variable,
				$mapping->replacers,
				$this->variables,
			);

			foreach ($files as $file) {
				$absolutePath = $file->getPathname();
				
				LogEvent::info("    Trying to update file '{$absolutePath}':");
				$updater($contentUpdater, $absolutePath);
			}
		}
		
		AfterAllFilesProcessed::dispatch($fileProcessingListener->getModifiedSourceFiles());
	}
	
	public function findFiles($glob)
	{
		$files = Glob::in($glob, $this->baseDirectory);
		return $files;
	}
}