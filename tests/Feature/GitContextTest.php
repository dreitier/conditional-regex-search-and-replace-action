<?php

use App\Filesystem\Context as FilesystemContext;
use Illuminate\Support\Facades\Event;

it('can open a Git repository', function () {
    $fs = FilesystemContext::of(__DIR__ . '/../..');
    $gitOptions = new \App\Git\Options($fs);
    $ctx = $gitOptions->createContext();

    try {
        $repo = $ctx->open();
        $sut = $repo->getLastCommit();

        expect($sut->getId()->toString())->toBeString();
    } catch (Exception $e) {
        expect($e->getMessage())->toBeEmpty();
    } finally {
        $ctx->close();
    }
});
