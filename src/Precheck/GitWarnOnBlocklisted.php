<?php

namespace TryLib\Precheck;

use TryLib\Precheck as Precheck;

/**
  * Warn a user if there are any blocklisted files in the patch
  */
class GitWarnOnBlocklisted implements Precheck {
    protected $blocklist;
    protected $safelist;
    protected $staged;

    function __construct(array $blocklist, $safelist = null, $staged = false) {
        $this->safelist = $safelist ?: [];
        $this->blocklist = array_diff($blocklist, $this->safelist);
        $this->staged = $staged;
    }

    /**
     * Warn the user if the changed files contain any blocklisted files
     *
     * @param CommandRunner $cmd_runner  cmd runner object
     * @param string        $repo_path          location of the git repo
     * @param string        $upstream           upstream branch name
     **/
    function check($cmd_runner, $repo_path, $upstream) {
        $cmd = 'git diff --name-only ' . $upstream;

        if ($this->staged) {
            $cmd .= ' --staged';
        }

        if (!empty($this->safelist)) {
            $cmd .= ' ' . implode(' ', $this->safelist);
        }

        $cmd_runner->run($cmd);

        $changed_files = $cmd_runner->getOutput();

        $blocklisted_changed_files = [];

        foreach (explode(PHP_EOL, $changed_files) as $file) {
            if (in_array($file, $this->blocklist)) {
                $blocklisted_changed_files[] = $file;
            }
        }

        if (!empty($blocklisted_changed_files)) {
            $msg = 'The diff you are trying to submit contains the following blocklisted file(s) : ';
            $msg .= implode(',', $blocklisted_changed_files);
            $cmd_runner->warn($msg);
        }
    }
}
