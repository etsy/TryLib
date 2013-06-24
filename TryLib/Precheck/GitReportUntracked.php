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
     * @param string        $upstream           upstream branch name
     **/
    function check($cmd_runner, $repo_path, $upstream) {
        $cmd_runner->run('git ls-files --exclude-standard --others');
        $output = $cmd_runner->getOutput();
        $untracked_files = explode(PHP_EOL, $output);
        if (!empty($untracked_files)) {
            $msg = 'You have ';
            $msg .= count($untracked_files);
            $msg .= ' untracked files in your working copy' . PHP_EOL;
            $cmd_runner->warn($msg);
        }
    }
}
