<?php
use App\ContentUpdater;
use App\Mapping;
use App\Variable;
use App\Replacer;
use App\VariableCollection;
use App\ReplacerCollection;
use App\MappingCollection;
use App\UpdateEnvironments;

it('can update a single match in a file', function () {
	$variables = (new VariableCollection())
		->add(new Variable('docker_image_tag', '1.0.0'))
		->add(new Variable('custom_var', '4.0.0'));

	$replacers = (new ReplacerCollection())
		->add(new Replacer('docker_image_tag', 'imageTag: \"(?<docker_image_tag>.*)\" (?<custom_var>.*)'));

	$mappingDefinition = "docker_image_tag==1.* {THEN_UPDATE_FILES} dev/*.yaml=docker_image_tag";
	
	$mappings = new MappingCollection($variables, $replacers);
	$mappings->upsert($mappingDefinition);

	$sut = new UpdateEnvironments($mappings, $variables, __DIR__ . "/environments");
	$called = 0;
	
	$sut->process(function(ContentUpdater $contentUpdater, $pathToFile) use (&$called){
		$replaced = $contentUpdater->update(file_get_contents($pathToFile));
		
		expect($replaced)
			->toBe(file_get_contents($pathToFile . ".expected"));
		
		$called++;
	});
	
	
	expect($called)->toBe(1);
});
