<?php

class Try_CLI {
    private $jenkinsServer;
    private $jenkinsCliJar;
    private $jenkinsMasterJob;
    private $user;
    private $repoPath;
    private $options;
    private $patch;
    private $cmdRunner;
    private $repoManager;
    PRIVATE $preChecks;

    public function __construct($jenkinsServer, $jenkinsCliJar, $jenkinsMasterJob) {
        $this->jenkinsServer = $jenkinsServer;
        $this->jenkinsCliJar = $jenkinsCliJar;
        $this->jenkinsMasterJob = $jenkinsMasterJob;
        $this->user = null;
        $this->repoPath = null;
        $this->options = null;
    }

    public function setUserAndRepoPath($user, $repoPath) {
        $this->user = $user;
        $this->repoPath = $repoPath;
    }

    public function setOptions($options) {
        $this->options = $options;
    }

    public function run() {
        $this->preChecks = array(
            new Try_Precheck_ScriptRunner($this->repoPath . '/bin/check_file_size'),
            new Try_Precheck_CopyAge(),
        );

        $this->cmdRunner = new Try_CommandRunner($this->options['verbose']);

        $this->repoManager = new Try_RepoManager_Git($this->repoPath, $this->cmdRunner);
        $this->repoManager->runPrechecks($this->preChecks);

        $this->patch = $this->options['patch'];
        if (is_null($this->patch)) {
            $this->patch = $this->repoManager->generateDiff($this->options['staged-only']);
        }

        if ($this->options['dry-run']) {
            print "Not sending job to Jenkins (-n) diff is here: $patch" . PHP_EOL;
            exit(0);
        }

        $jenkinsRunner = new Try_JenkinsRunner(
            $this->jenkinsServer,
            $this->jenkinsCliJar,
            $this->jenkinsMasterJob,
            $this->cmdRunner
        );

        $jenkinsRunner->setBranch($this->repoManager->getRemotebranch("master"));
        $jenkinsRunner->setSshKey('/home/' . $this->user . '/.ssh/try_id_rsa');
        $jenkinsRunner->setUid($this->user . time());
        $jenkinsRunner->setSubJobs($this->options['jobs']);
        $jenkinsRunner->addCallback($this->options['callback']);
        $jenkinsRunner->startJenkinsJob($this->patch, $this->options['poll_for_completion']);
    }
}
