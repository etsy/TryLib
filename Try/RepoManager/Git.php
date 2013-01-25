<?php

class Try_RepoManager_Git implements Try_RepoManager {
    private $repo_path;
    private $cmd_runner;
    private $branch;
    private $remote;
    private $remote_branch;
    
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
            $this->cmd_runner->run("git symbolic-ref HEAD");
            $this->branch = $this->cleanRef($this->cmd_runner->getOutput());
        }
        return $this->branch;
    }

    function getConfig($prop) {
        $this->cmd_runner->chdir($this->repo_path);
        $this->cmd_runner->run("git config '$prop'");
        return $this->cleanRef($this->cmd_runner->getOutput());
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
        $this->cmd_runner->run('git diff ' . implode(' ', $args) . ' > ' . $patch);
        return $patch;
    }

    function runPreChecks(array $pre_checks) {
        foreach ($pre_checks as $c) {
            $this->cmd_runner->chdir($this->repo_path);
            $c->check($this->cmd_runner, $this->repo_path);
        }
    }
}
