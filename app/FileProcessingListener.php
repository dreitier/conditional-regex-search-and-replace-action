<?php
declare(strict_types=1);
namespace App;
use PHLAK\Splat\Glob;
use App\Events\FileContentUpdateBeginEvent;
use App\Events\FileContentUpdateFinishEvent;
use App\Events\LineModifiedEvent;
use App\Events\LogEvent;
use App\Mapping\Collection as MappingCollection;
use App\Variable\Collection as VariableCollection;
use Illuminate\Support\Facades\Event;
use App\Filesystem\ModifiedFile;

/**
 * Worker horse
 */
class FileProcessingListener
{
	private ?string $shaActiveFile = null;
	private ?ModifiedFile $activeFile = null;
	private array $modifiedSourceFiles = [];

	public function listen(): FileProcessingListener
	{
		Event::listen(
			FileContentUpdateBeginEvent::class,
			[$this, 'beforeUpdateFile']
		);
		
		Event::listen(
			FileContentUpdateFinishEvent::class,
			[$this, 'afterUpdateFile']
		);

		Event::listen(
			LineModifiedEvent::class,
			[$this, 'onModifiedLine']
		);
		
		return $this;
	}
	
	public function beforeUpdateFile(FileContentUpdateBeginEvent $event)
	{
		$usedFile = file_exists($event->targetFile) ? $event->targetFile : $event->originalFile;

		$this->activeFile = new ModifiedFile(
			sourceFile: $event->originalFile,
			targetFile: $event->targetFile,
			targetFileShaBefore: sha1_file($usedFile)
		);
	}
	
	public function afterUpdateFile(FileContentUpdateFinishEvent $event)
	{
		throw_if(!$this->activeFile, "No active file; mis-order in fired events");
		
		$this->activeFile->shaChanged = $this->activeFile->targetFileShaBefore != sha1_file($this->activeFile->targetFile);
		$this->modifiedSourceFiles[] = $this->activeFile;
		$this->activeFile = null;
	}

	public function onModifiedLine(LineModifiedEvent $event)
	{
		throw_if(!$this->activeFile, "No active file; mis-order in fired events");
		$this->activeFile->modifiedLines++;
	}
	
	public function getModifiedSourceFiles(): array
	{
		return $this->modifiedSourceFiles;
	}
}