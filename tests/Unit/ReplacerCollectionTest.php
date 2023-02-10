<?php
use App\Mapping\Collection as MappingCollection;
use App\Replacer\Collection as ReplacerCollection;
use App\Variable\Collection as VariableCollection;
use App\Replacer\Replacer;
use App\Variable\Variable;
use App\Mapping\Mapping;

test('regex from environment is autodetected', function () {
    putenv('MY_CUSTOM_REGEX=some_regex');

    $sut = ReplacerCollection::create([], true);

    $first = $sut->get('my_custom_regex');
    $this->assertNotNull($first);
    $this->assertEquals("some_regex", $first->regex);
});

test('regex with custom variable name is merged', function () {
    putenv('MY_CUSTOM_REGEX_WITH_ANOTHER_SUFFIX=some_regex');

    $sut = ReplacerCollection::create(['MY_CUSTOM_REGEX_WITH_ANOTHER_SUFFIX'], false);

    $first = $sut->get('my_custom_regex_with_another_suffix');
    $this->assertNotNull($first);
    $this->assertEquals("some_regex", $first->regex);
});

test('regexes can be autodetected and customized', function () {
    putenv('MY_CUSTOM_REGEX=some_regex');
    putenv('MY_CUSTOM_REGEX_WITH_ANOTHER_SUFFIX=some_regex2');

    $sut = ReplacerCollection::create(['MY_CUSTOM_REGEX_WITH_ANOTHER_SUFFIX'], true);

    $first = $sut->get('my_custom_regex');
    $second = $sut->get('my_custom_regex_with_another_suffix');

    $this->assertEquals("my_custom_regex", $first->name);
    $this->assertEquals("some_regex", $first->regex);
    $this->assertEquals("my_custom_regex_with_another_suffix", $second->name);
    $this->assertEquals("some_regex2", $second->regex);

});
