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

    public static function of($baseDirectory)
    {
        return new self($baseDirectory);
    }

    private ?string $cwd = null;

    public function pushdBaseDirectory(): string
    {

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
