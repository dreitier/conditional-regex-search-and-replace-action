<?php
declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Util;
use App\Variable\Collection as VariableCollection;
use App\Variable\Variable;
use App\Replacer\Collection as ReplacerCollection;
use App\Mapping\Mapping;
use App\Mapping\Collection as MappingCollection;
use App\ContentUpdater;
use App\UpdateEnvironments;
use App\Events\GenericEvent;
use Illuminate\Support\Facades\Event;
use App\EnvironmentVariable;
use App\MissingWellKnownVariableException;
use App\Events\LogEvent;
use App\Git\CommitTask;
use App\Git\Options as GitCommitOptions;
use App\Filesystem\Context as FilesystemContext;
use App\Events\AfterAllFilesProcessed;

use function Termwind\{render};

class UpdateEnvironmentsCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'update-environments {--dump} {--mappings=} {--directory=environments} {--github} {--require-one-well-known-var} {--require-at-least-one-change} {--commit} {--commit-template=} {--commit-split-up-by=} {--updated-file-suffix=} {--custom-regexes=} {--custom-variables=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Update environments';

	/**
	 * Fail this command and exit
	 * @param string $msg
	 */
	private function fail($msg, $exitCode = 1): int
	{
		$this->error($msg);
		return $exitCode;
	}
	
	public function failOnOption($optionName, $exception, $exitCode = 2): ?int
	{
		$msg = $exception->getMessage();
		
		if ($this->option($optionName)) {
			return $this->fail($msg, $exitCode);
		}
		
		$this->warn($msg);
	}
	
	public function handleLog(LogEvent $event)
	{
		switch ($event->type) {
			case 'warn':
				$this->warn($event->message);
				break;
			case 'info':
				$this->info($event->message);
				break;
			case 'debug':
				$this->info('[DEBUG] ' . $event->message);
				break;
			default:
				$this->fail($event->message, $event->errorCode);
		}
	}
	
	private function configureGitCommitTask(FilesystemContext $filesystem, VariableCollection $variables)
	{
		if (!$this->option('commit')) {
			return;
		}
		
		$options = new GitCommitOptions(
			$filesystem,
			$this->option('commit-template') ?? 'chore: @if(isset($docker_image_tag))bumped docker image tag to {{ $docker_image_tag->value }}@endif',
			$this->option('commit-split-up-by') ?? '*'
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
	
	private function configureGithub()
	{
		$this->info("Enabling GitHub Actions output");

		Event::listen(
			AfterAllFilesProcessed::class,
			function (AfterAllFilesProcessed $afterAllFilesProcessed) {
				$total = sizeof($afterAllFilesProcessed->getModifiedFiles());
				$varName = "GITHUB_ENV";
				$path = getenv($varName);
				
				if (!$path) {
					LogEvent::warn('Environment variable ' . $varName . ' is not available, but --github has been provided. Can not set output variables.');
					return;
				}

				file_put_contents($path, 'total_modified_files=' . (int)$total, FILE_APPEND);
			}
		);
	}
	
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$variables = new VariableCollection(function($variableName, $exception) {
			$this->warn("ignoring $variableName: " . $exception->getMessage());
		});

		$providedMappings = $this->option('mappings') ?? '';
		
		if (empty($providedMappings)) {
			$this->fail("No mappings provided");
		}
		
		$directory = $this->option('directory');
		$suffixOfUpdatedFile = $this->option("updated-file-suffix") ?? '';
		$filesystem = new FilesystemContext($directory, $suffixOfUpdatedFile);
		
		if ($this->option('require-at-least-one-change')) {
			Event::listen(
				AfterAllFilesProcessed::class,
				function (AfterAllFilesProcessed $afterAllFilesProcessed) {
					$total = sizeof($afterAllFilesProcessed->getModifiedFiles());
					
					if ($total == 0) {
						LogEvent::fatal("At least one change should be made, but have done nothing", 3);
					}
				}
			);
		}
		
		Event::listen(
			LogEvent::class,
			[$this, 'handleLog']
		);
		
		$this->configureGitCommitTask($filesystem, $variables);

		$customRegexes = Util::trimmedExplode(',', $this->option('custom-regexes'));
		
		if ($this->option("github")) {
			$this->configureGithub();
		}
		
		$customVariables = Util::trimmedExplode(',', $this->option('custom-variables'));
		
		try {
			$variables = $variables->mergeWellKnownVariables();

			if (!empty($customVariables)) {
				$variables->locateAndMerge($customVariables);
			}
		}
		catch (MissingWellKnownVariableException $e) {
			$this->failOnOption("require-one-well-known-var", $e, 2);
		}
		catch (\Exception $e) {
			return $this->fail($e->getMessage());
		}
		
		$replacers = new ReplacerCollection();
		$possibleReplacerNames = array_unique(
			array_merge(
				collect($variables->variableNames())
					->map(fn($item) => $item . "_regex")
					->toArray(), 
				$customRegexes
			), 
			SORT_REGULAR
		);
		
		$replacers->mergeFromEnvironment($possibleReplacerNames);
		
		$mappings = new MappingCollection($variables, $replacers);
		$mappings->upsert($providedMappings);
		
		$updateEnvironments = new UpdateEnvironments($mappings, $variables, $directory, $suffixOfUpdatedFile);
		
		if ($this->option("dump")) {
			$this->dump($variables, $replacers, $mappings, $updateEnvironments);
			return;
		}

		$updateEnvironments->process();
    }
	
	private function dump(
		VariableCollection $variables,
		ReplacerCollection $replacers,
		MappingCollection $mappings,
		UpdateEnvironments $updateEnvironments
	) 
	{
		$this->info("Available variables from environment:");
		$this->table(['Name', 'Value'], collect($variables->items())->map(fn($item) => [$item->name, $item->value])->toArray());
		
		$this->info("");
		$this->info("Available replacers from environment:");
		$this->table(['Name', 'Regex'], collect($replacers->items())->map(fn($item) => [$item->name, $item->regex])->toArray());
		
		$this->info("");
		$this->info("Specified globs:");
		$files = [];
		
		$processedGlobs = [];
		$lastHeader = null;
		
		foreach ($mappings->items() as $item) 
		{
			if (isset($processedGlobs[$item->glob])) {
				continue;
			}
			
			$processedGlobs[$item->glob] = true;
			
			foreach ($updateEnvironments->findFiles($item->glob) as $absolutePath) {
				$globColumn = '';
				if ($lastHeader != $item->glob) {
					$globColumn = $item->glob;
				}
				
				$files[] = [$globColumn, $absolutePath];
			}
		}
		
		$this->table(['Glob', 'Found files'], $files);
		
		$this->info("");
		$this->info("Created mappings:");
		$this->table(
			['Variable', 'Variable value', 'Variable must match against', 'Success?', 'Glob', 'Replacers to execute on globbed files'],
			collect($mappings->items())
			->map(function($item) {
				$r = [];
				$r[] = $item->variable->name;
				$r[] = $item->variable->value;
				$r[] = $item->regexToMatchValue;
				$r[] = $item->matches() > 0 ? 'Yes' : 'No';
				$r[] = $item->glob;
				$r[] = collect($item->replacers)->map(fn($item) => $item->name)->join(", ");
				return $r;
			})
			->toArray()
		);
	}

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }
}