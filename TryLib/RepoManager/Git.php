<?php

class TryLib_RepoManager_Git implements TryLib_RepoManager {
    protected $repo_path;
    protected $cmd_runner;
    protected $branch;
    protected $remote;
    private $remote_branch;

    public function __construct($repo_path, $cmd_runner) {
        $this->repo_path = $repo_path;
        $this->cmd_runner = $cmd_runner;
        $this->branch = null;
        $this->remote = null;
        $this->remote_branch = null;
    }

    function cleanRef($ref) {
        return rtrim(str_replace('refs/heads/', '', $ref));
    }

    function parseLocalBranch() {
        $this->cmd_runner->chdir($this->repo_path);
        $ret = $this->cmd_runner->run('git rev-parse --abbrev-ref HEAD', true, true);
        $output = $this->cmd_runner->getOutput();
        if ($ret || $output === 'HEAD') {
            return '';
        } else {
            return $this->cleanRef($output);
        }
    }

    function getLocalBranch() {
        if (is_null($this->branch)) {
            $this->branch = $this->parseLocalBranch();
        }
        return $this->branch;
    }

    function getConfig($prop) {
        $this->cmd_runner->chdir($this->repo_path);
        $ret = $this->cmd_runner->run("git config '$prop'", true, true);
        if ($ret) {
            return null;
        } else {
            return $this->cleanRef($this->cmd_runner->getOutput());
        }
    }

    function getRemote($default=null) {
        if (is_null($this->remote)) {
            $branch = $this->getLocalBranch();
            $this->remote = $this->getConfig("branch.$branch.remote");
        }

        if (is_null($this->remote) && !is_null($default)) {
            $this->remote = $default;
        }

        return $this->remote;
    }


    function setRemoteBranch($remote_branch) {
        $this->remote_branch = $remote_branch;
    }

    function getRemoteBranch($default=null) {
        if (!is_null($this->remote_branch)) {
            return $this->remote_branch;
        }

        $local_branch = $this->getLocalBranch();
        $this->remote_branch = $this->getConfig("branch.$local_branch.merge");

        if (is_null($this->remote_branch)) {
            // try to see if a remote branch exists with the same name as the local branch
            $remote_url = $this->getConfig('remote.origin.url');
            $cmd = 'git ls-remote --exit-code ' . $remote_url . ' refs/heads/' . $local_branch;
            $ret = $this->cmd_runner->run($cmd, true, true);
            if ($ret === 0) {
                $this->cmd_runner->info(
                    'A remote branch with the same name than your local branch was found - using it for the diff'
                );
                $this->remote_branch = $local_branch;
            } elseif (!is_null($default)) {
                $this->cmd_runner->info("It appears that your local branch '$local_branch' is not tracked remotely");
                $this->cmd_runner->info("The default remote ('$default') will be used to generate the diff.");
                return $default;
            }
        }
        return $this->remote_branch;
    }

    function getUpstream() {
        $remote = $this->getRemote("origin");
        $remote_branch = $this->getRemoteBranch("master");
        return $remote . "/" . $remote_branch;
    }

    function generateDiff($staged_only = false, $whitelist = null) {
        $patch = $this->repo_path . "/patch.diff";

        $args = array(
            "--binary",
            "--src-prefix=''",
            "--dst-prefix=''",
            "--no-color",
            $this->getUpstream(),
        );

        if ($staged_only) {
            $args[] = "--staged";
        }

        if (is_array($whitelist)) {
            $args[] = implode(' ', $whitelist);
        }

        $this->cmd_runner->chdir($this->repo_path);

        $ret = $this->cmd_runner->run('git diff ' . implode(' ', $args) . ' > ' . $patch, false, true);
        if ($ret) {
            $this->cmd_runner->terminate(
                'An error was encountered generating the diff - run \'git fetch\' and try again'
            );
        }

        return $patch;
    }

    function runPreChecks(array $pre_checks) {
        $upstream = $this->getUpstream();
        foreach ($pre_checks as $c) {
            $this->cmd_runner->chdir($this->repo_path);
            $c->check($this->cmd_runner, $this->repo_path, $upstream);
        }
    }
}
