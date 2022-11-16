<?php
declare(strict_types=1);

namespace App\Github;

use App\Events\FileContentUpdateBeginEvent;
use App\Events\FileContentUpdateFinishEvent;
use App\Events\LineModifiedEvent;
use App\Events\LogEvent;
use App\Filesystem\Context as FilesystemContext;
use App\Git\Options as GitCommitOptions;
use CzProject\GitPhp\Git;
use App\Variable\Collection as VariableCollection;
use App\Events\AfterAllFilesProcessed;
use Illuminate\Support\Facades\Event;

class GithubTask
{
    const ENV_VAR_FOR_VARIABLE_PATH = "GITHUB_OUTPUT";

    private ?string $path = null;

    public function __construct(
        public readonly VariableCollection $variables,
    )
    {
        $path = getenv(self::ENV_VAR_FOR_VARIABLE_PATH);

        if (!$path) {
            LogEvent::warn('Environment variable "' . self::ENV_VAR_FOR_VARIABLE_PATH . '" is not available, but --github has been provided. Can not set output variables.');
            return;
        }

        $this->path = $path;
    }

    const TOTAL_MODIFIED_FILES = 'total_modified_files';

    private function exportVariable($key, $value) {
        if (!$this->path) {
            return;
        }

        LogEvent::debug('Exporting variable "' . $key . '=' . $value . '" to "' . $this->path . '"');
        file_put_contents($this->path, $key . '=' . $value, FILE_APPEND);
    }

    public function exportVariables(AfterAllFilesProcessed $afterAllFilesProcessed)
    {
        $total = sizeof($afterAllFilesProcessed->getModifiedFiles());
        // export stats
        $this->exportVariable(self::TOTAL_MODIFIED_FILES, (int)$total);

        // export (custom) variables, provided by user
        foreach ($this->variables->items() as $customVariable) {
            $this->exportVariable($customVariable->name, $customVariable->value);
        }
    }

    public static function configure(\Illuminate\Console\Command $command,
                                     VariableCollection          $variables)
    {
        if (!$command->option("github")) {
            return;
        }

        LogEvent::info("Enabling GitHub Actions support");

        $githubTask = new GithubTask($variables);

        Event::listen(
            AfterAllFilesProcessed::class,
            [$githubTask, 'exportVariables']
        );
    }
}
