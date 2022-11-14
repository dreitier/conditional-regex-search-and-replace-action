<?php
namespace App\Filesystem;
use App\Events\LogEvent;

class Context
{
	public function __construct(
		public readonly string $baseDirectory,
		public readonly string $suffixOfUpdatedFile = '',
	)
	{
		if (!is_dir($baseDirectory)) {
			LogEvent::fatal("Directory '$baseDirectory' does not exist");
		}
	}
	
	private ?string $cwd = null;
	
	public function pushdBaseDirectory() : string
	{
		throw_if(!empty($this->cwd), "pushd before popd");

		$cwd = getcwd();
		chdir($this->baseDirectory);
		
		$this->cwd = $this->baseDirectory;
		return $this->cwd;
	}
	
	public function popd(): string
	{
		throw_if(!$this->cwd, "No pushd before popd");
		
		$newDir = $this->cwd;
		chdir($newDir);
		$this->cwd = null;
		
		return $newDir;
	}
}