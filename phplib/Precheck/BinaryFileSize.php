<?php

class Precheck_BinaryFileSize implements Precheck {
    private $cmdRunner;
    private $repoPath;

    function __construct($cmdRunner, $repoPath) {
        $this->cmdRunner = $cmdRunner;
        $this->repoPath = $repoPath;
    }

    /**
     * Checks to see if you are committing large binary files to the repo (which will fail on CI).
     **/
    function check() {
        $script = $this->repoPath . '/bin/check_file_size';
        if (file_exists($this->repoPath . '/bin/check_file_size')) {
            $return = $this->cmdRunner->run("cd $this->repoPath && bin/check_file_size");
            if ($return) {
                $this->cmdRunner->terminate("Binary file size check failed with output: " . $this->cmdRunner->getOutput());
            }
        }
    }
}
