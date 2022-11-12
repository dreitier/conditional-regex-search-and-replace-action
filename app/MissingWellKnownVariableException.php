<?php
declare(strict_types=1);
namespace App;

class MissingWellKnownVariableException extends \Exception 
{
	public function __construct($message) {
		parent::__construct($message);
	}
}