<?php

namespace TryLib\Precheck;

use TryLib\Precheck as Precheck;

/** 
  * Warn a user if there are any untracked files in the repository
  */
class GitReportUntracked implements Precheck {
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
        $untracked_files = array_filter(explode(PHP_EOL, $output));
        if (!empty($untracked_files)) {
            $msg = 'You have ';
            $msg .= count($untracked_files);
            $msg .= ' untracked files in your working copy' . PHP_EOL;
            $cmd_runner->warn($msg);
        }
    }
}
