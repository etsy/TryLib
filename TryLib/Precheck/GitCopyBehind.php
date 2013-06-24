<?php

/** Check to prevent generating a diff when the local work copy
  * has been fetched but not merged. When it's the case, the diff
  * generated via "git diff origin/branch" will "undo" the un-merged commits.
  * As a result, the code 'tried' on the jenkins server will no longer be
  * the latest copy of the code with the local changes, but instead the exact same code
  * than the local working copy.
  */
class TryLib_Precheck_GitCopyBehind implements TryLib_Precheck {
    protected $branches_to_check;

    function __construct(array $branches_to_check) {
        $this->branches_to_check = $branches_to_check;
    }

    /**
     * Check if we should run the GitCopyBehind pre-check for the local-branch
     *
     * @param CommandRunner $cmd_runner  cmd runner object
     * @return boolean true if we should run the GitCopyBehind check
     **/
    public function shouldRunCheck($cmd_runner) {
        $cmd_runner->run('git rev-parse --abbrev-ref HEAD');
        $local_branch = $cmd_runner->getOutput();
        return in_array($local_branch, $this->branches_to_check);
    }

    /**
     * Check if the local branch is behind by X commits
     * If it's the case, then the diff that will get generated will "undo"
     * the latest changes from the server and as a result, the code that will
     * get tried will be the exact same code than where the diff is generated
     * and NOT the latest copy of the repository with only the local changes applied
     *
     * @param CommandRunner $cmd_runner  cmd runner object
     * @param string        $repo_path          location of the git repo
     * @param string        $upstream           upstream branch name
     **/
    function check($cmd_runner, $repo_path, $upstream) {
        if ($this->shouldRunCheck($cmd_runner)) {
            $cmd_runner->run('git rev-list HEAD..origin');
            $output = $cmd_runner->getOutput();
            if (!empty($output)) {
                $msg = 'ERROR - you ran git fetch in your repository without merging the new commits' . PHP_EOL;
                $msg .= 'If you submit a `try` job as is, you will not be testing your diff against the ';
                $msg .= 'latest version of the repository.' . PHP_EOL . PHP_EOL;
                $msg .= 'Please merge your changes or run `git rpull` first.' . PHP_EOL;
                $cmd_runner->warn($msg);
            }
        }
    }
}
