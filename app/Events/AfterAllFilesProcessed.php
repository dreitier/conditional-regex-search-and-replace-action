<?php
declare(strict_types=1);
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;

class AfterAllFilesProcessed
{
	use Dispatchable;

	public function __construct(
		public readonly array $processedFiles, 
	)
	{
	}
	
	public function getModifiedFiles()
	{
		$changedFiles = collect($this->processedFiles)
			->where(fn($item) => $item->modifiedLines > 0 && $item->shaChanged == true)
			->all();
			
		return $changedFiles;
	}
}