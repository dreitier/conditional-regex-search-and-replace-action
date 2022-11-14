<?php
declare(strict_types=1);
namespace App\Filesystem;

/**
 * Value object for a modified file
 */
class ModifiedFile {
	public function __construct(
		public string $sourceFile,
		public string $targetFile,
		public string $targetFileShaBefore,
		public int $modifiedLines = 0,
		public bool $shaChanged = false,
	)
	{
	}
}
