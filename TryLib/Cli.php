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
        $this->cmd_runner = new TryLib_CommandRunner($this->options['verbose']);
        $this->repo_manager = new TryLib_RepoManager_Git($this->repo_path, $this->cmd_runner);

        $remote_branch = 'master';
        if (in_array('search', $this->options['jobs'], true)) {
            $remote_branch = $this->repo_manager->getRemotebranch('master');
        }

        $this->pre_checks = array(
            new TryLib_Precheck_ScriptRunner($this->repo_path . '/bin/check_file_size'),
            new TryLib_Precheck_GitCopyBehind(array('master')),
            new TryLib_Precheck_GitCopyAge(48, 96, $remote_branch)
        );

        $this->repo_manager->runPrechecks($this->pre_checks);
        $this->repo_manager->setRemoteBranch($remote_branch);

        $patch = $this->options['patch'];
        if (is_null($patch)) {
            $patch = $this->repo_manager->generateDiff($this->options['staged']);
        }

        if ($this->options['diffonly']) {
            print 'Not sending job to Jenkins (-n) diff is here:' . $patch . PHP_EOL;
            exit(0);
        }

        $jenkins_runner = new TryLib_JenkinsRunner_MasterProject(
            $this->jenkins_server,
            $this->jenkins_cli_jar,
            $this->jenkins_master_job,
            $this->cmd_runner
        );

        $jenkins_runner->setPatch(realpath($patch));
        $jenkins_runner->setSshKey( '/home/' . $this->user . '/.ssh/try_id_rsa');
        $jenkins_runner->setParam('branch', $remote_branch);
        $jenkins_runner->setParam('guid', $this->user . time());
        $jenkins_runner->setSubJobs($this->options['jobs']);
        $jenkins_runner->setExcludedSubJobs($this->options['exclude']);
        foreach ($this->options['callbacks'] as $cb) {
            $jenkins_runner->addCallback($cb);
        }
        $jenkins_runner->startJenkinsJob($this->options['showresults'], $this->options['showprogress']);
    }
}
