<?php

class Precheck_BinaryFileSize implements Precheck {
    /**
     * Checks to see if you are committing large binary files to the repo (which will fail on CI).
     **/
    function check($cmdRunner, $repoPath) {
        $script = $repoPath . '/bin/check_file_size';
        if (file_exists($repoPath . '/bin/check_file_size')) {
            $return = $cmdRunner->run("cd $repoPath && bin/check_file_size");
            if ($return) {
                $cmdRunner->terminate("Binary file size check failed with output: " . $cmdRunner->getOutput());
            }
        }
    }
}
