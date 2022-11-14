<?php
declare(strict_types=1);
namespace App\Variable;

class MissingWellKnownVariableException extends \Exception 
{
	public function __construct($message) {
		parent::__construct($message);
	}
}