<?php
declare(strict_types=1);

namespace App\Git;

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

class CommitTask
{
    public function __construct(
        public readonly Options            $options,
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

        $context = $this->options->createContext();

        try {
            // pushd to working directory
            $repository = $context->open();

            foreach ($relevantFiles as $modifiedFile) {
                $relativePath = str_replace($context->getWorkingDirectory(), "", $modifiedFile->targetFile);

                if ($relativePath[0] = "/") {
                    $relativePath = substr($relativePath, 1, strlen($relativePath) - 1);
                }

                LogEvent::debug("[git] Adding file $relativePath");
                $repository->addFile($relativePath);
            }

            $commitMessage = $this->options->renderCommitMessage($this->variables);
            LogEvent::info("Creating Git commit with message '$commitMessage'");
            $repository->commit($commitMessage);
            LogEvent::info("Git commit executed");
        } catch (\Exception $e) {
            LogEvent::warn("Failed to commit: " . $e->getMessage());

            if ($e instanceof \CzProject\GitPhp\GitException) {
                if (null !== ($runnerResult = $e->getRunnerResult())) {
                    LogEvent::debug("[git] command: " . $runnerResult->getCommand());
                    LogEvent::debug("[git] exit-code: " . $runnerResult->getExitCode());
                    LogEvent::debug("[git] output: " . $runnerResult->toText());
                }
            }
        } finally {
            // popd from working directory to previous cwd
            $context->close();
        }
    }

    public static function configure(\Illuminate\Console\Command $command,
                                     FilesystemContext           $filesystem,
                                     VariableCollection          $variables)
    {
        if (!$command->option('commit')) {
            return;
        }

        $options = new GitCommitOptions(
            $filesystem,
            $command->option('commit-template') ?? 'chore: @if(isset($docker_image_tag))bumped docker image tag to {{ $docker_image_tag->value }}@endif',
            $command->option('commit-split-up-by') ?? '*',
            $command->option('committer-name') ?? null,
            $command->option('committer-email') ?? null,
        );

        $commitTask = new CommitTask(
            $options,
            $variables,
        );

        Event::listen(
            AfterAllFilesProcessed::class,
            [$commitTask, 'commit']
        );
    }
}
