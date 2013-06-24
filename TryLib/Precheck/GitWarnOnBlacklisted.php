<?php

/**
  * Warn a user if there are any blacklisted files in the patch
  */
class TryLib_Precheck_GitWarnOnBlacklisted implements TryLib_Precheck {
    protected $blacklist;
    protected $whitelist;
    protected $staged;

    function __construct(array $blacklist, $whitelist = null, $staged = false) {
        $this->blacklist = $blacklist;
        $this->whitelist = $whitelist;
        $this->staged = $staged;
    }

    /**
     * Warn the user if the changed files contain any blacklisted files
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

        if (is_array($this->whitelist)) {
            $cmd .= ' ' . implode(' ', $this->whitelist);
        }

        $cmd_runner->run($cmd);

        $changed_files = $cmd_runner->getOutput();

        $blacklisted_changed_files = array();

        foreach (explode(PHP_EOL, $changed_files) as $file) {
            if (in_array($file, $this->blacklist)) {
                $blacklisted_changed_files[] = $file;
            }
        }

        if (!empty($blacklisted_changed_files)) {
            $msg = 'The diff you are trying to submit contains the following blacklisted file(s) : ';
            $msg .= implode(',', $blacklisted_changed_files);
            $cmd_runner->warn($msg);
        }
    }
}
