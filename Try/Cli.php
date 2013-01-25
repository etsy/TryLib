<?php

class Try_CLI {
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
            new Try_Precheck_ScriptRunner($this->repo_path . '/bin/check_file_size'),
            new Try_Precheck_CopyAge(),
        );

        $this->cmd_runner = new Try_CommandRunner($this->options['verbose']);

        $this->repo_manager = new Try_RepoManager_Git($this->repo_path, $this->cmd_runner);
        $this->repo_manager->runPrechecks($this->pre_checks);

        $patch = $this->options['patch'];
        if (is_null($patch)) {
            $patch = $this->repo_manager->generateDiff($this->options['staged-only']);
        }

        if ($this->options['dry-run']) {
            print 'Not sending job to Jenkins (-n) diff is here:' . $patch . PHP_EOL;
            exit(0);
        }

        $jenkins_runner = new Try_JenkinsRunner(
            $this->jenkins_server,
            $this->jenkins_cli_jar,
            $this->jenkins_master_job,
            $this->cmd_runner
        );

        $jenkins_runner->setBranch($this->repo_manager->getRemotebranch("master"));
        $jenkins_runner->setSshKey('/home/' . $this->user . '/.ssh/try_id_rsa');
        $jenkins_runner->setUid($this->user . time());
        $jenkins_runner->setSubJobs($this->options['jobs']);
        $jenkins_runner->addCallback($this->options['callback']);
        $jenkins_runner->startJenkinsJob($patch, $this->options['poll_for_completion']);
    }
}
