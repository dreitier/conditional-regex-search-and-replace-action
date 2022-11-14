<?php
declare(strict_types=1);
namespace App\Git;

use App\Filesystem\Context as FilesystemContext;
use App\Variable\Collection as VariableCollection;
use Illuminate\Support\Facades\Blade;

class Options {
	public function __construct(
		public readonly FilesystemContext $filesystem,
		public readonly string $commitMessageTemplate,
		public readonly string $splitCommitsByFile,
	)
	{
	}
	
	public function renderCommitMessage(VariableCollection $variables): string
	{
		$parsedTemplate = Blade::render($this->commitMessageTemplate, $variables->raw());
		return $parsedTemplate;
	}
}
