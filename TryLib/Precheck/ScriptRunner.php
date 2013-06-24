<?php

class TryLib_Precheck_ScriptRunner implements TryLib_Precheck {
    protected $script_path;

    function __construct($script_path) {
        $this->script_path = $script_path;

    }

    /**
     * Checks to see if you are committing large binary files to the repo (which will fail on CI).
     **/
    function check($cmd_runner, $repo_path, $upstream) {
        if (file_exists($this->script_path)) {
            $return = $cmd_runner->run($this->script_path, false, true);
            if ($return) {
                $cmd_runner->terminate("Failed running pre-check script $this->script_path");
            }
        }
    }
}
