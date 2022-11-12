<?php
declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Util;
use App\VariableCollection;
use App\Variable;
use App\ReplacerCollection;
use App\MappingCollection;
use App\ContentUpdater;
use App\Mapping;
use App\UpdateEnvironments;
use App\Events\GenericEvent;
use Illuminate\Support\Facades\Event;
use App\EnvironmentVariable;
use App\MissingWellKnownVariableException;

use function Termwind\{render};

class UpdateEnvironmentsCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'update-environments {--dump} {--github} {--require-well-known-var}';

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
	private function fail($msg, $exitCode = 1)
	{
		$this->error($msg);
		exit($exitCode);
	}
	
	private function configureEventListeners()
	{
		Event::listen('*', function ($eventType, array $data) {
			if ($eventType != GenericEvent::class) {
				return;
			}
			
			$eventName = $data[0]->eventName;
			$args = $data[0]->args;
		});
	
	}
	
	public function failOnOption($optionName, $exception, $exitCode = 2)
	{
		$msg = $exception->getMessage();
		
		if ($this->option($optionName)) {
			$this->fail($msg, $exitCode);
		}
		
		$this->warn($msg);
	}
	
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$providedMappings = EnvironmentVariable::envVar("MAPPINGS");
		$directory = EnvironmentVariable::envVar("DIRECTORY", "environments");
		
		if (!is_dir($directory)) {
			$this->fail("Directory '$directory' does not exist");
		}
		
		$customRegexes = EnvironmentVariable::envVarAsArray("REGISTER_CUSTOM_REGEXES", ",", []);
		
		if ($this->option("github")) {
			$this->info("Enabling GitHub Actions output");
		}
		
		$this->configureEventListeners();

		$variables = new VariableCollection($this, function($variableName, $exception) {
			$this->warn("ignoring $variableName: " . $exception->getMessage());
		});
		
		$customVariables = EnvironmentVariable::envVarAsArray("CUSTOM_VARIABLES", ",", null);
		
		try {
			$variables = $variables->mergeWellKnownVariables();

			if (!empty($customVariables)) {
				$variables->locateAndMerge($customVariables);
			}
		}
		catch (MissingWellKnownVariableException $e) {
			$this->failOnOption("require-well-known-var", $e, 2);
		}
		catch (\Exception $e) {
			$this->fail($e->getMessage());
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
		
		$updateEnvironments = new UpdateEnvironments($mappings, $variables, $directory);
		
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