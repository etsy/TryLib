<?php

class TryLib_CLI {
    protected $jenkins_server;
    protected $jenkins_cli_jar;
    protected $jenkins_master_job;
    protected $cmd_runner;
    protected $repo_manager;
    protected $pre_checks;

    private $user;
    private $repo_path;
    private $options;

    public function __construct($jenkins_server, $jenkins_cli_jar, $jenkins_master_job) {
        $this->jenkins_server = $jenkins_server;
        $this->jenkins_cli_jar = $jenkins_cli_jar;
        $this->jenkins_master_job = $jenkins_master_job;
        $this->user = null;
        $this->repo_path = null;
        $this->options = null;
    }

    public function setUserAndRepoPath($user, $repo_path) {
        $this->user = $user;
        $this->repo_path = $repo_path;
    }

    public function setOptions($options) {
        $this->options = $options;
    }

    public function run() {
        $this->pre_checks = array(
            new TryLib_Precheck_ScriptRunner($this->repo_path . '/bin/check_file_size'),
            new TryLib_Precheck_CopyAge(),
        );

        $this->cmd_runner = new TryLib_CommandRunner($this->options['verbose']);

        $this->repo_manager = new TryLib_RepoManager_Git($this->repo_path, $this->cmd_runner);
        $this->repo_manager->runPrechecks($this->pre_checks);

        $remote_branch = $this->options['branch'];
        if (is_null($remote_branch)) {
            $remote_branch = $this->repo_manager->getRemotebranch('master');
        }

        $this->repo_manager->setRemoteBranch($remote_branch);

        $patch = $this->options['patch'];
        if (is_null($patch)) {
            $patch = $this->repo_manager->generateDiff($this->options['staged']);
        }

        if ($this->options['diffonly']) {
            print 'Not sending job to Jenkins (-n) diff is here:' . $patch . PHP_EOL;
            exit(0);
        }

        $jenkins_runner = new TryLib_JenkinsRunner(
            $this->jenkins_server,
            $this->jenkins_cli_jar,
            $this->jenkins_master_job,
            $this->cmd_runner
        );

        $jenkins_runner->setBranch($remote_branch);
        $jenkins_runner->setSshKey('/home/' . $this->user . '/.ssh/try_id_rsa');
        $jenkins_runner->setUid($this->user . time());
        $jenkins_runner->setSubJobs($this->options['jobs']);
        $jenkins_runner->addCallback($this->options['callback']);
        $jenkins_runner->startJenkinsJob($patch, $this->options['showprogress']);
    }
}
