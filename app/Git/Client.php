<?php

namespace App\Git;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\IRunner;

/**
 * Make the CzProject\GitPhp work with from https://github.com/czproject/git-php/pull/58. This class can be deleted as soon as the PR
 * is merged.
 */
class Client extends Git
{
    /**
     * @param string $directory
     * @return Repository
     */
    public function open($directory)
    {
        return new Repository($directory, $this->runner);
    }

}
