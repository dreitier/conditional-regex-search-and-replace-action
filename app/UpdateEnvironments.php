<?php
declare(strict_types=1);
namespace App;
use PHLAK\Splat\Glob;

/**
 * Worker horse
 */
class UpdateEnvironments
{
	public function __construct(
		public readonly MappingCollection $mappings,
		public readonly VariableCollection $variables,
		public readonly string $baseDirectory,
		private readonly ?object $logger = null,
	)
	{
	}
	
	/**
	 * For each mapping, provided variable and assigned glob the replacement of files will be done.
	 * @param $updater optional callable when a custom update logic is required. Makes it easier for testing purposes.
	 */
	public function process(?callable $updater = null) 
	{
		if (!$updater) {
			$updater = function(ContentUpdater $contentUpdater, string $absolutePathToFile) {
				$contentUpdater->updateFile($absolutePathToFile, $absolutePathToFile . ".new");
			};
		}
		
			
		foreach ($this->mappings->items() as $mapping) {
			if (!$mapping->matches()) {
				$this->logger?->info("  Value does not match regex /" . $mapping->regexToMatchValue . "/");
				continue;
			}
			
			$this->logger?->info("  Mapping $type is present and matches /{$mapping->regexToMatchValue}/");
			
			$files = $this->findFiles($mapping->glob);
			$totalFoundFiles = sizeof($files);
			$this->logger?->info("  Total found files for {$mapping->glob}: {$totalFoundFiles}");
			
			if (!$totalFoundFiles) {
				continue;
			}
			
			$contentUpdater = new ContentUpdater(
				$mapping->variable,
				$mapping->replacers,
				$this->variables, 
				$this->logger
			);

			foreach ($files as $file) {
				$absolutePath = $file->getPathname();
				
				$this->logger?->info("    Trying to update file {$absolutePath}:");
				$updater($contentUpdater, $absolutePath);
			}
		}
	}
	
	public function findFiles($glob)
	{
		$files = Glob::in($glob, $this->baseDirectory);
		return $files;
	}
}