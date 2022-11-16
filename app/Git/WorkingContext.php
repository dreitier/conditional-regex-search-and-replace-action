<?php
declare(strict_types=1);

namespace App\Git;

use App\Filesystem\Context as FilesystemContext;
use App\Variable\Collection as VariableCollection;
use CzProject\GitPhp\GitRepository;
use Illuminate\Support\Facades\Blade;

class WorkingContext
{
    public function __construct(
        public readonly Options $options,
        public readonly Client  $client)
    {
    }

    public static function of(Options $options)
    {
        return new self($options, new Client());
    }

    private ?Repository $repository = null;
    private ?string $cwdBeforeOpen = null;

    /**
     * Changes the working directory to the repository path and sets user name and email if present.
     * @return Repository
     * @throws \Throwable
     */
    public function open(): Repository
    {
        throw_if(!empty($this->repository), "repository already opened");
        $this->cwdBeforeOpen = getcwd();
        chdir($this->getWorkingDirectory());

        $repository = $this->client->open($this->getWorkingDirectory());
        $this->repository = $repository;

        if (!empty($this->options->committerName)) {
            $repository->setUserName($this->options->committerName);
        }

        if (!empty($this->options->committerEmail)) {
            $repository->setUserEmail($this->options->committerEmail);
        }

        return $this->repository;
    }

    /**
     * chdirs back to the previous working directory
     *
     * @return $this
     * @throws \Throwable
     */
    public function close()
    {
        throw_if(empty($this->repository), "Trying to close the repository even as it has been already closed.");

        chdir($this->cwdBeforeOpen);
        $this->cwdBeforeOpen = null;

        return $this;
    }

    public function getWorkingDirectory(): string
    {
        return $this->options->filesystem->baseDirectory;
    }
}
