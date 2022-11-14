<?php
use App\Mapping\Collection as MappingCollection;
use App\Replacer\Collection as ReplacerCollection;
use App\Variable\Collection as VariableCollection;
use App\Replacer\Replacer;
use App\Variable\Variable;
use App\Mapping\Mapping;

test('single matcher can be configured', function () {
	$variables = (new VariableCollection())->add(new Variable('docker_image_tag', '1.0.0'));
	$replacers = (new ReplacerCollection())->add(new Replacer('my_replacer', 'some regex'));
	
	$sut = new MappingCollection($variables, $replacers);
	$sut->upsert("docker_image_tag==main.* {THEN_UPDATE_FILES} environments/dev/values.yaml=my_replacer");
	
	$items = $sut->items();
	
	$this->assertEquals(1, sizeof($items));
	$first = $items[0];
	$this->assertEquals('docker_image_tag', $first->variable->name);
	$this->assertEquals('main.*', $first->regexToMatchValue);
	$this->assertEquals('my_replacer', $first->replacers[0]->name);
	$this->assertEquals('some regex', $first->replacers[0]->regex);
});

test('multiple matchers can be configured', function () {
	$variables = (new VariableCollection())
		->add(new Variable('docker_image_tag', '1.0.0'))
		->add(new Variable('git_branch', 'prod.bla'))
		;
	$replacers = (new ReplacerCollection())->add(new Replacer('my_replacer', 'some regex'));
	
	$sut = new MappingCollection($variables, $replacers);
	$sut->upsert("docker_image_tag==main.* {OR} git_branch==prod.* {THEN_UPDATE_FILES} environments/dev/values.yaml=my_replacer");
	
	$items = $sut->items();
	
	$this->assertEquals(2, sizeof($items));
	$first = $items[0];
	$this->assertEquals('docker_image_tag', $first->variable->name);
	$this->assertEquals('main.*', $first->regexToMatchValue);
	
	$second = $items[1];
	$this->assertEquals('git_branch', $second->variable->name);
	$this->assertEquals('prod.*', $second->regexToMatchValue);
});

test('multiple replacers can be configured', function () {
	$variables = (new VariableCollection())
		->add(new Variable('docker_image_tag', '1.0.0'))
		->add(new Variable('git_branch', 'prod.bla'))
		;
	$replacers = (new ReplacerCollection())
		->add(new Replacer('my_replacer', 'some regex'))
		->add(new Replacer('my_replacer_2', 'some other regex'));
	
	$sut = new MappingCollection($variables, $replacers);
	$sut->upsert("docker_image_tag==main.* {THEN_UPDATE_FILES} environments/dev/values.yaml=my_replacer, environments/qa/kustomize-overlay.yaml=my_replacer_2");
	
	$items = $sut->items();
	
	$this->assertEquals(2, sizeof($items));
	$first = $items[0];
	$this->assertEquals('docker_image_tag', $first->variable->name);
	$this->assertEquals('main.*', $first->regexToMatchValue);
	$this->assertEquals('environments/dev/values.yaml', $first->glob);
	
	$second = $items[1];
	$this->assertEquals('docker_image_tag', $second->variable->name);
	$this->assertEquals('main.*', $second->regexToMatchValue);
	$this->assertEquals('environments/qa/kustomize-overlay.yaml', $second->glob);
});