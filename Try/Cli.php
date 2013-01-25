<?php

class Try_CLI {
    private $jenkins_server;
    private $jenkins_cli_jar;
    private $jenkins_master_job;
    private $user;
    private $repo_path;
    private $options;
    private $patch;
    private $cmd_runner;
    private $repo_manager;
    PRIVATE $pre_checks;

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

        $this->patch = $this->options['patch'];
        if (is_null($this->patch)) {
            $this->patch = $this->repo_manager->generateDiff($this->options['staged-only']);
        }

        if ($this->options['dry-run']) {
            print "Not sending job to Jenkins (-n) diff is here: $patch" . PHP_EOL;
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
        $jenkins_runner->startJenkinsJob($this->patch, $this->options['poll_for_completion']);
    }
}
