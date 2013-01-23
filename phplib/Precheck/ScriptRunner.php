<?php

class Precheck_ScriptRunner implements Precheck {
    private $scriptPath;

    function __construct($scriptPath) {
        $this->scriptPath = $scriptPath;
    }

    /**
     * Checks to see if you are committing large binary files to the repo (which will fail on CI).
     **/
    function check($cmdRunner, $repoPath) {
        if (file_exists($this->scriptPath)) {
            $return = $cmdRunner->run($this->scriptPath);
            if ($return) {
                $cmdRunner->terminate(
                    "Pre-check script failed with output: " .
                    PHP_EOL .
                    $cmdRunner->getOutput()
                );
            }
        }
    }
}
