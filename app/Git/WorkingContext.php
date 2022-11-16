<?php
declare(strict_types=1);

namespace App\Git;

use App\Filesystem\Context as FilesystemContext;
use App\Variable\Collection as VariableCollection;
use CzProject\GitPhp\GitRepository;
use Illuminate\Support\Facades\Blade;

class Options
{
    public function __construct(
        public readonly FilesystemContext $filesystem,
        public readonly string            $commitMessageTemplate,
        public readonly string            $splitCommitsByFile,
        public readonly ?string           $committerName = null,
        public readonly ?string           $committerEmail = null,
    )
    {
    }

    public function renderCommitMessage(VariableCollection $variables): string
    {
        $parsedTemplate = Blade::render($this->commitMessageTemplate, $variables->raw());
        return $parsedTemplate;
    }

    public function createContext(): Context
    {
        return new Context($this);
    }
}

class Context
{
    public function __construct(
        public readonly Options $options,
        public readonly Client  $client)
    {

    }

    private ?Repository $repository = null;
    private ?string $cwdBeforeOpen = null;

    public function open(): Repository
    {
        throw_if(!empty($this->repository), "repository already opened");
        $this->cwdBeforeOpen = getcwd();
        chdir($this->getRepositoryDirectory());

        $repository = $this->client->open($this->getRepositoryDirectory());

        if (!empty($this->options->committerName)) {
            $repository->setUserName($this->options->committerName);
        }

        if (!empty($this->options->committerEmail)) {
            $repository->setUserName($this->options->committerEmail);
        }

        $this->repository = $repository;
        return $this->repository;
    }

    public function close()
    {
        throw_if(empty($this->repository), "repository already closed");

        chdir($this->cwdBeforeOpen);
        $this->cwdBeforeOpen = null;

        return $this;
    }

    public function getRepositoryDirectory(): string
    {
        return $this->options->filesystem->baseDirectory;
    }
}
