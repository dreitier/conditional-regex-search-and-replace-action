<?php
beforeEach(function() {
	// variables
	// --- well-known variables
	putenv("DOCKER_IMAGE_TAG=main-1.0.0");
	putenv("GIT_BRANCH=main");
	putenv("GIT_TAG=v1.0.0");

	// --- custom variables
	putenv("CUSTOM_VAR=2.6.6");
	// --- register any number of previously provided variables
	putenv("CUSTOM_VARIABLES=custom_var");

	// replacers
	// --- well-known replacers
	putenv("DOCKER_IMAGE_TAG_REGEX=imageTag: \\\"(?<docker_image_tag>.*)\\\" (?<custom_var>\w+)");
	// --- custom replacer for CUSTOM_VAR_REGEX
	putenv("CUSTOM_VAR_REGEX=blub.*blub2");
	// --- custom replacer
	putenv("MY_CUSTOM_REGEX=bla.*bla2");
});

function has_tty() {
	return is_writable("/dev/tty");
}

function is_github() {
	return !empty(getenv("CI")) && !empty(getenv("GITHUB_ACTION"));
}

if (has_tty() && !is_github()) {
	it('can update environments via artisan', function () {
		$this->artisan('update-environments')->assertExitCode(0);
	});

	it('can dump the active application configuration via artisan', function () {
		$cmd = [
			'update-environments',
			'--dump',
			'--custom-regexes=my_custom_regex,custom_var_regex',
			'--custom-variables=custom_var',
			'--directory=' . __DIR__ . '/scenarios/environments',
			'--mappings="docker_image_tag==main.* {OR} git_branch==main.* {OR} git_tag==dev {THEN_UPDATE_FILES} **dev/*.yaml=docker_image_tag_regex&my_custom_regex&custom_var_regex"',
		];

		$this->withoutMockingConsoleOutput()->artisan(implode(" ", $cmd));//->assertExitCode(0);
		$output = Artisan::output();

		// echo $output;
		expect($output)
			->toMatch('/docker_image_tag.*\|.*main-1.0.0/')
			->toMatch('/git_tag.*\|.*v1.0.0/')
			->toMatch('/git_branch.*\|.*main/')
			->toMatch('/custom_var.*\|.*2.6.6/')
			->toMatch('/docker_image_tag_regex.*\|.*imageTag.*/' /* regex with a regex */)
			->toMatch('/\*\*dev\/\*\.yaml.*environments\/dev\/values\.yaml/')
			->toMatch('/docker_image_tag\s+\|\s+main\-1.0.0\s+|\s+main\.\*\|\s+Yes\s+\|.*/')
		;
	});

    it('can replace an .argocd file via artisan', function () {
        putenv('ARGOCD_SOURCE_IMAGE_TAG_REGEX=value: (?<docker_image_tag>.*)\\s+#\\s+IMAGE_TAG_MARKER_DONT_REMOVE_THIS_COMMENT');
        putenv('DOCKER_IMAGE_TAG=1.0.16');
        putenv('DOCKER_IMAGE_TAG_REGEX=\\s+tag:\\s+\\\"(?<docker_image_tag>.*)\\\"');
        putenv('GIT_BRANCH_REGEX=\\\"(?<git_branch>.*)\\\"');

        $cmd = [
            'update-environments',
            '--dump',
            '--directory=' . __DIR__ . '/scenarios/argocd',
            '--mappings="__true__ {THEN_UPDATE_FILES} environments/prod/values.yaml=docker_image_tag_regex&git_branch_regex {AND} .argocd-source-self-service-prod.yaml=argocd_source_image_tag_regex"',
        ];

        $this->withoutMockingConsoleOutput()->artisan(implode(" ", $cmd));//->assertExitCode(0);
        $output = Artisan::output();
        // echo $output;
        expect($output)
            ->toMatch('/\.argocd-source-self-service-prod.yaml.*|argocd_source_image_tag_regex/')
        ;
    });
}
