<?php
use App\ContentUpdater;
use App\Variable;
use App\Replacer;
use App\VariableCollection;
use App\ReplacerCollection;

test('can replace a single variable', function () {
	$variable = new Variable('docker_image_tag', '1.0.0');
	
	$variables = new VariableCollection();
	$variables->add($variable);
	$regexCollection = (new ReplacerCollection())
		->add(new Replacer('some_name', 'imageTag: \"(?<docker_image_tag>.*)\"'));		
	
	$sut = new ContentUpdater($variable, $regexCollection->items(), $variables);
	
	$content = <<<CONTENT
first: value
second: value2
  imageTag: "0.0.666"
fourth: value4
CONTENT;
	
	$r = $sut->update($content);
	
	expect($r)->toBe(<<<CONTENT
first: value
second: value2
  imageTag: "1.0.0"
fourth: value4
CONTENT
);
});

test('can replace the same variable multiple times', function () {
	$variable = new Variable('docker_image_tag', '1.0.0');
	
	$variables = new VariableCollection();
	$variables->add($variable);
	$regexCollection = (new ReplacerCollection())
		->add(new Replacer('some_name', 'imageTag: \"(?<docker_image_tag>.*)\"'));		
		
		
	$sut = new ContentUpdater($variable, $regexCollection->items(), $variables);
	
	$content = <<<CONTENT
first: value
second: value2
  imageTag: "0.0.666"
fourth: value4
  imageTag: "0.0.666"
CONTENT;
	
	$r = $sut->update($content);
	
	expect($r)->toBe(<<<CONTENT
first: value
second: value2
  imageTag: "1.0.0"
fourth: value4
  imageTag: "1.0.0"
CONTENT
);
});

test('can reference another variable', function () {
	$variable = new Variable('docker_image_tag', '1.0.0');
	$otherVar = new Variable('custom_var', '4.0.0');
	
	$variables = new VariableCollection();
	$variables
		->add($variable)
		->add($otherVar);
	$regexCollection = (new ReplacerCollection())
		->add(new Replacer('some_name', 'imageTag: \"(?<docker_image_tag>.*)\" (?<custom_var>\w+)'));		
		
	$sut = new ContentUpdater($variable, $regexCollection->items(), $variables);
	
	$content = <<<CONTENT
first: value
second: value2
  imageTag: "0.0.666" old_custom_var
fourth: value4
  # the next line gets not replaced as the regex does not match the line
  imageTag: "0.0.666"
CONTENT;
	
	$r = $sut->update($content);
	
	expect($r)->toBe(<<<CONTENT
first: value
second: value2
  imageTag: "1.0.0" 4.0.0
fourth: value4
  # the next line gets not replaced as the regex does not match the line
  imageTag: "0.0.666"
CONTENT
);

});