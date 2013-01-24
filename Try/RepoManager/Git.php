<?php

class RepoManager_Git implements RepoManager {
    private $repoPath;
    private $cmdRunner;
    private $branch;
    private $remote;
    private $remoteBranch;
    
    public function __construct(
        $repoPath,
        $cmdRunner
    ) {    
        $this->repoPath = $repoPath;
        $this->cmdRunner = $cmdRunner;
        $this->branch = null;
        $this->remote = null;
        $this->remoteBranch = null;
    }

    function cleanRef($ref) {
        $ref = str_replace("refs/heads/", "", $ref);
        return rtrim($ref);
    } 


    function getLocalBranch() {
        if (is_null($this->branch)) {
            $this->cmdRunner->chdir($this->repoPath);
            $this->cmdRunner->run("git symbolic-ref HEAD");
            $this->branch = $this->cleanRef($this->cmdRunner->getLastOutput());
        }
        return $this->branch;
    }

    function getConfig($prop) {
        $this->cmdRunner->chdir($this->repoPath);
        $this->cmdRunner->run("git config '$prop'");
        return $this->cleanRef($this->cmdRunner->getLastOutput());
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
        if (is_null($this->remoteBranch)) {
            $branch = $this->getLocalBranch();
            $this->remoteBranch = $this->getConfig("branch.$branch.merge");
        }

        if ($this->remoteBranch === "" && !is_null($default)) {
            return $default;
        }
        return $this->remoteBranch;
    }

    function getUpstream() {
        $remote = $this->getRemote("origin");
        $remoteBranch = $this->getRemoteBranch("master");
        return $remote . "/" . $remoteBranch;
    }

    function generateDiff($staged_only=false) {    
        $patch = $this->repoPath . "/patch.diff";

        $args = array(
            "--src-prefix=''",
            "--dst-prefix=''",
            "--no-color",
            $this->getUpstream(),
        );

        if ($staged_only) {
            $args[] = "--staged";
        }
        
        $this->cmdRunner->chdir($this->repoPath);
        $this->cmdRunner->run('git diff ' . implode(' ', $args) . ' > ' . $patch);
        return $patch;
    }

    function runPreChecks(array $preChecks) {
        foreach ($preChecks as $c) {
            $this->cmdRunner->chdir($this->repoPath);
            $c->check($this->cmdRunner, $this->repoPath);
        }
    }
}
