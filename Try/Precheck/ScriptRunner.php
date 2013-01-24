<?php

class Try_Precheck_ScriptRunner implements Try_Precheck {
    private $scriptPath;

    function __construct($scriptPath) {
        $this->scriptPath = $scriptPath;
    }

    /**
     * Checks to see if you are committing large binary files to the repo (which will fail on CI).
     **/
    function check($cmdRunner, $repoPath) {
        if (file_exists($this->scriptPath)) {
            $return = $cmdRunner->run($this->scriptPath, $silent=false, $ignore_errors=true);
            if ($return) {
                $cmdRunner->terminate("Failed running pre-check script $this->scriptPath");
            }
        }
    }
}
