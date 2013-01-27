<?php

class Try_RepoManager_Git implements Try_RepoManager {
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
            return $default;
        }

        return $this->remote;
    }
    function getRemoteBranch($default=null) {
        if (is_null($this->remote_branch)) {
            $branch = $this->getLocalBranch();
            $this->remote_branch = $this->getConfig("branch.$branch.merge");
        }

        if ($this->remote_branch === "" && !is_null($default)) {
            echo "Remote branch not found - using default remote: $default" . PHP_EOL;
            return $default;
        }
        return $this->remote_branch;
    }

    function getUpstream() {
        $remote = $this->getRemote("origin");
        $remote_branch = $this->getRemoteBranch("master");
        return $remote . "/" . $remote_branch;
    }

    function generateDiff($staged_only=false) {    
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
