<?php
declare(strict_types=1);

namespace App\Commands;

use App\Github\GithubTask;
use App\Variable\MissingWellKnownVariableException;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Util;
use App\Variable\Collection as VariableCollection;
use App\Replacer\Collection as ReplacerCollection;
use App\Mapping\Collection as MappingCollection;
use App\Variable\Variable;
use App\UpdateEnvironments;
use Illuminate\Support\Facades\Event;
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
	protected $signature = 'update-environments \
	{--dump} \
	{--mappings=} \
	{--directory=.} \
	{--github} \
	{--require-one-well-known-var} \
	{--require-at-least-one-change} \
	{--commit} \
	{--commit-template=} \
	{--commit-split-up-by=} \
	{--committer-name=} \
	{--committer-email=} \
	{--updated-file-suffix=} \
	{--custom-regexes=} \
	{--custom-variables=} \
	{--skip-regexes-autodetect} \
	{--skip-variables-autodetect}';

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

		return 0;
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

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$variables = new VariableCollection(function ($variableName, $exception) {
			$this->warn("ignoring $variableName: " . $exception->getMessage());
		});

		foreach (['__true__', '__always__'] as $defaultVariable) {
			$variables->add(Variable::of($defaultVariable, '1'));
		}

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

		// configure GitHub, if running in GitHub
		GithubTask::configure($this, $variables);
		// config Git commit, if set
		CommitTask::configure($this, $filesystem, $variables);

		$customRegexes = Util::trimmedExplode(',', $this->option('custom-regexes'));
		$customVariables = Util::trimmedExplode(',', $this->option('custom-variables'));

		try {
            $variables->mergeWellKnownVariables();
            $autodetectVariables = !$this->option("skip-variables-autodetect");
            $variables->upsert($customVariables, $autodetectVariables);
		} catch (MissingWellKnownVariableException $e) {
			$this->failOnOption("require-one-well-known-var", $e, 2);
		} catch (\Exception $e) {
			return $this->fail($e->getMessage());
		}

        $autodetectRegexes = !$this->option("skip-regexes-autodetect");
        $replacers = ReplacerCollection::create($customRegexes, $autodetectRegexes);

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
		MappingCollection  $mappings,
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

		foreach ($mappings->items() as $item) {
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
			['Variable', 'Variable value', 'Variable must match against', 'Match successful?', 'Glob', 'Replacers to execute on globbed files'],
			collect($mappings->items())
				->map(function ($item) {
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
	 * @param \Illuminate\Console\Scheduling\Schedule $schedule
	 * @return void
	 */
	public function schedule(Schedule $schedule)
	{
		// $schedule->command(static::class)->everyMinute();
	}
}
