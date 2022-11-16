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
        public readonly ?string           $commitMessageTemplate = 'new commit',
        public readonly ?string           $splitCommitsByFile = null,
        public readonly ?string           $committerName = null,
        public readonly ?string           $committerEmail = null,
    )
    {
    }

    public function renderCommitMessage(VariableCollection $variables): string
    {
        $args = collect($variables->raw())->map(fn($item) => $item->value)->toArray();

        $parsedTemplate = Blade::render($this->commitMessageTemplate, $args);
        return $parsedTemplate;
    }

    public function createContext(): WorkingContext
    {
        return WorkingContext::of($this);
    }
}
