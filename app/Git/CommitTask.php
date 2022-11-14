<?php
declare(strict_types=1);
namespace App\Git;

use App\Events\FileContentUpdateBeginEvent;
use App\Events\FileContentUpdateFinishEvent;
use App\Events\LineModifiedEvent;
use App\Events\LogEvent;
use CzProject\GitPhp\Git;
use App\Variable\Collection as VariableCollection;
use App\Events\AfterAllFilesProcessed;

class CommitTask
{
	public function __construct(
		public readonly Options $options,
		public readonly VariableCollection $variables,
	)
	{
	}
	
	public function commit(AfterAllFilesProcessed $afterAllFilesProcessed)
	{
		$relevantFiles = $afterAllFilesProcessed->getModifiedFiles();
		
		if (sizeof($relevantFiles) == 0) {
			LogEvent::info("Not doing a Git commit as no target file content has been modified");
			return;
		}
		
		$baseDirectory = $this->options->filesystem->pushdBaseDirectory();
		
		$gitClient = new Git();
		$repo = $gitClient->open($baseDirectory);
		
		foreach ($relevantFiles as $modifiedFile) {
			$relativePath = str_replace($baseDirectory, "", $modifiedFile->targetFile);
			
			if ($relativePath[0] = "/") {
				$relativePath = substr($relativePath, 1, strlen($relativePath) - 1);
			}

			LogEvent::debug("[git] Adding file $relativePath");
			$repo->addFile($relativePath);
		}
		
		$commitMessage = $this->options->renderCommitMessage($this->variables);
		LogEvent::info("Creating Git commit with message '$commitMessage'");
		$repo->commit($commitMessage);
		LogEvent::info("Git commit executed");
		
		$this->options->filesystem->popd();
	}
}