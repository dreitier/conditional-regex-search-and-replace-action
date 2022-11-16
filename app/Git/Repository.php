<?php

namespace App\Git;

use CzProject\GitPhp\GitRepository;

/**
 * Extended GitRepository to apply committer's name and email address.
 * This is taken from https://github.com/czproject/git-php/pull/58. This class can be deleted as soon as the PR is merged.
 *
 * Please note that for our implementation, we have removed begin/commit as the chdir functionality.
 * That is already present in our WorkingContext.
 */
class Repository extends GitRepository
{
    /**
     * Set the user name
     * `git config user.name %user_name`
     * @param string $user_name
     * @return self
     */
    public function setUserName($user_name)
    {
        exec('git config user.name "' . $user_name . '"');
        return $this;
    }

    /**
     * Set the user email
     * `git config user.email %user_email`
     * @param string $user_email
     * @return self
     */
    public function setUserEmail($user_email)
    {
        exec('git config user.email "' . $user_email . '"');
        return $this;
    }
}
