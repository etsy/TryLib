<?php

/**
  * Warn a user if there are any blocklisted files in the patch
  */
class TryLib_Precheck_GitWarnOnBlocklisted implements TryLib_Precheck {
    protected $blocklist;
    protected $whitelist;
    protected $staged;

    function __construct(array $blocklist, $whitelist = null, $staged = false) {
        $this->whitelist = $whitelist ?: array();
        $this->blocklist = array_diff($blocklist, $this->whitelist);
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

        if (!empty($this->whitelist)) {
            $cmd .= ' ' . implode(' ', $this->whitelist);
        }

        $cmd_runner->run($cmd);

        $changed_files = $cmd_runner->getOutput();

        $blocklisted_changed_files = array();

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
