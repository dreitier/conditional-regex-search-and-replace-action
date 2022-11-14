<?php
namespace App;

class Util 
{
	public static function trimmedExplode(string $separator, ?string $string): array 
	{
		if (null === $string || empty(trim($string))) {
			return [];
		}
		
		return array_map('trim', explode($separator, $string));
	}
}