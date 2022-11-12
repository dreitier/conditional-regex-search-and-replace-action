<?php
use App\VariableCollection;

test('can detect default variables', function () {
	putenv('DOCKER_IMAGE_TAG=latest');
	putenv('GIT_TAG=1.0.0');
	putenv('GIT_BRANCH=develop');
	
	$sut = new VariableCollection();
	$sut->mergeWellKnownVariables();

	$r = $sut->items();
	
	expect($r)->toHaveCount(3);
	expect($r)->sequence(
		fn($first) => 
			expect($first->value->name)
				->toBe('docker_image_tag')
				->and($first->value->value)
				->toBe('latest'),
		fn($second) => 
			expect($second->value->name)
				->toBe('git_tag')
				->and($second->value->value)
				->toBe('1.0.0'),
		fn($third) => 
			expect($third->value->name)
				->toBe('git_branch')
				->and($third->value->value)
				->toBe('develop'),
	);
	
	putenv('DOCKER_IMAGE_TAG');
	putenv('GIT_TAG');
	putenv('GIT_BRANCH');
});

test('can resolve custom variables', function() {
	putenv('CUSTOM_VARIABLE=custom');

	$sut = new VariableCollection();
	$sut->locateAndMerge(['custom_variable']);
	$r = $sut->items();
	
	expect($r)->toHaveCount(1);
	$first= $r[0];
	expect($first->name)
		->toBe('custom_variable')
		->and($first->value)
		->toBe('custom');

	putenv('CUSTOM_VARIABLE');
});