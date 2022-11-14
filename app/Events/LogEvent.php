<?php
declare(strict_types=1);
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;

class LogEvent
{
	use Dispatchable;

	public function __construct(
		public readonly string $type, 
		public readonly string $message,
		public readonly ?array $variables = null,
		public readonly ?int $errorCode = 0,
	)
	{
	}
	
	public static function info($message) 
	{
		self::dispatch('info', $message);
	}
	
	public static function debug($message)
	{
		self::dispatch('debug', $message);
	}
	
	public static function warn($message)
	{
		self::dispatch('warn', $message);
	}

	public static function fatal($message, $errorCode = 1)
	{
		self::dispatch('fatal', $message, [], $errorCode);
	}
}