<?php

class TryLib_RepoManager_Git implements TryLib_RepoManager {
    protected $repo_path;
    protected $cmd_runner;
    protected $branch;
    protected $remote;
    protected $remote_branch;
    
    public function __construct($repo_path, $cmd_runner) {    
        $this->repo_path = $repo_path;
        $this->cmd_runner = $cmd_runner;
        $this->branch = null;
        $this->remote = null;
        $this->remote_branch = null;
    }

    function cleanRef($ref) {
        return rtrim(str_replace("refs/heads/", "", $ref));
    } 


    function getLocalBranch() {
        if (is_null($this->branch)) {
            $this->cmd_runner->chdir($this->repo_path);
            $ret = $this->cmd_runner->run("git symbolic-ref HEAD", true, true);
            if ($ret) {
                $this->branch = "";
            } else {
                $this->branch = $this->cleanRef($this->cmd_runner->getOutput());
            }
        }
        return $this->branch;
    }

    function getConfig($prop) {
        $this->cmd_runner->chdir($this->repo_path);
        $ret = $this->cmd_runner->run("git config '$prop'", true, true);
        if ($ret) {
            return "";
        } else {
            return $this->cleanRef($this->cmd_runner->getOutput());
        }
    }

    function getRemote($default=null) {
        if (is_null($this->remote)) {
            $branch = $this->getLocalBranch();
            $this->remote = $this->getConfig("branch.$branch.remote");
        }

        if ($this->remote === "" && !is_null($default)) {
            $this->remote = $default;
        }

        return $this->remote;
    }

    function getRemoteBranch($default=null) {
        $local_branch = $this->getLocalBranch();

        if (is_null($this->remote_branch)) {
            $this->remote_branch = $this->getConfig("branch.$local_branch.merge");
        }

        if ($this->remote_branch === "") {
            // try to see if a remote branch exists with the same name as the local branch
            $remote_url = $this->getConfig('remote.origin.url');
            $cmd = 'git ls-remote --exit-code ' . $remote_url . ' refs/heads/' . $local_branch;
            $ret = $this->cmd_runner->run($cmd, true, true);
            if ($ret === 0) {
                // we found a remote branch with same name as local branch - prompt user?
                echo "A remote branch with the same name than your local branch was found - using it for the diff" . PHP_EOL;
                $this->remote_branch = $local_branch;
            } elseif (!is_null($default)) {
                echo "It appears that your local branch $local_branch is not tracked remotely". PHP_EOL;
                echo "The default remote ($default) will be used to generate the diff." . PHP_EOL;
                $this->remote_branch = $default;
            }
        }
        return $this->remote_branch;
    }

    function getUpstream() {
        $remote = $this->getRemote("origin");
        $remote_branch = $this->getRemoteBranch("master");
        return $remote . "/" . $remote_branch;
    }

    function pullRefIfNeeded() {
        $local_ref = $this->getLocalBranch();
        $remote_ref = $this->getUpstream();
        $ret = $this->cmd_runner->run("git show-ref refs/remotes/$remote_ref", true, true);
        if ($ret) {
            $this->cmd_runner->run("git fetch origin $local_ref:refs/remotes/$remote_ref &> /dev/null", true, false);
        }
    }

    function generateDiff($staged_only=false) {    

        $this->pullRefIfNeeded();

        $patch = $this->repo_path . "/patch.diff";

        $args = array(
            "--src-prefix=''",
            "--dst-prefix=''",
            "--no-color",
            $this->getUpstream(),
        );

        if ($staged_only) {
            $args[] = "--staged";
        }
        
        $this->cmd_runner->chdir($this->repo_path);
        $this->cmd_runner->run('git diff ' . implode(' ', $args) . ' > ' . $patch, true, false);
        return $patch;
    }

    function runPreChecks(array $pre_checks) {
        foreach ($pre_checks as $c) {
            $this->cmd_runner->chdir($this->repo_path);
            $c->check($this->cmd_runner, $this->repo_path);
        }
    }
}
