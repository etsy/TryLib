<?php

/** 
  * Warn a user if there are any untracked files in the repository
  */
class TryLib_Precheck_GitReportUntracked implements TryLib_Precheck {
    /**
     * Warn the user if the working copy has any untracked changes
     *
     * @param CommandRunner $cmd_runner  cmd runner object
     * @param string        $repo_path          location of the git repo
     **/
    function check($cmd_runner, $repo_path) {
        $cmd_runner->run('git ls-files --exclude-standard --others');
        $output = $cmd_runner->getOutput();
        if (!empty($output)) {
            $msg = PHP_EOL;
            $msg = 'You have untracked files in your working copy' . PHP_EOL;
            $msg .= 'The below files will NOT be part of the diff: ' . PHP_EOL;
            $msg .= "   - " . implode(PHP_EOL . "   - ", explode(PHP_EOL, $output));
            $msg .= PHP_EOL . PHP_EOL;
            $cmd_runner->warn($msg);
        }
    }
}
