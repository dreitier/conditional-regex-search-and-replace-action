<?php
namespace App;

class Util 
{
	public static function trimmedExplode(string $separator, string $string): array 
	{
		return array_map('trim', explode($separator, $string));
	}
}