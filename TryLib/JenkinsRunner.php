<?php

abstract class TryLib_JenkinsRunner {
    protected $jenkins_url;
    protected $jenkins_cli;
    protected $try_job_name;
    protected $cmd_runner;
    
	public $try_status;
    public $try_base_url;

    private $branch;
    private $options;
    private $callbacks;
    private $ssh_key_path;

    public function __construct(
        $jenkins_url,
        $jenkins_cli,
        $try_job_name,
        $cmd_runner
    ) {
        $this->jenkins_url = $jenkins_url;
        $this->jenkins_cli = $jenkins_cli;
        $this->try_job_name = $try_job_name;
        $this->cmd_runner = $cmd_runner;

        $this->options = array();
        $this->callbacks = array();
        $this->ssh_key_path = null;
        $this->branch = null;
        $this->try_status = '';
        $this->try_base_url = '';
    }

    abstract protected function pollForCompletion($pretty);

    abstract protected function getBuildCommand();

    abstract protected function getBuildExtraArguments($poll_for_completion);

    public function runJenkinsCommand($command) {
        $cmd = sprintf(
            "java -jar %s -s http://%s/ %s",
            $this->jenkins_cli,
            $this->jenkins_url,
            $command
        );
        $this->cmd_runner->run($cmd, false, false);
    }

    /**
     * Logout, and Start the Jenkins job
     */
    public function startJenkinsJob($show_results=false, $show_progress = false) {
        // Explicitly log out user to force re-authentication over SSH
        $this->runJenkinsCommand("logout");

        // Build up the jenkins command incrementally
        $cli_command = $this->buildCLICommand($show_results);

        // Run the job
        $this->runJenkinsCommand($cli_command);

        if ($show_results || $show_progress || $this->getCallbacks()) {
            $this->pollForCompletion($show_progress);
            $this->executeCallbacks();
        }
    }

	public function getOptions() {
		return $this->options;
	}

    public function setParam($key, $value) {
        $param = sprintf('-p %s=%s', $key, $value);
        if (!in_array($param, $this->options)) {
            $this->options[] = $param;
        }
    }

    public function setSshKey($ssh_key_path) {
        if (file_exists($ssh_key_path)) {
            $this->ssh_key_path = $ssh_key_path;
        } else {
           $this->cmd_runner->warn("SSH key file not found (${ssh_key_path})");
        }
    }

	public function getSsKey() {
		return $this->ssh_key_path;
	}

    public function setPatch($patch) {
        if (file_exists($patch)) {
            $this->options[] = '-p patch.diff=' . $patch;
        } else {
            $this->cmd_runner->terminate("Patch file not found (${patch})");
        }
    }

    public function addCallback($callback) {
        if (is_null($callback)) {
            return;
        } else if (is_string($callback)) {
            $this->callbacks[] = $callback;
        } else {
           $this->cmd_runner->warn('Invalid callback - must be a string');
        }
    }

    public function getCallbacks() {
		return $this->callbacks;
	}

    /**
     * Build the Jenkins CLI command, based on all options
     */
    function buildCLICommand($show_results) {
        $command = array();

        if (!is_null($this->getSsKey())) {
            $command[] = '-i ' . $this->getSsKey();
        }

        $command[] = $this->getBuildCommand();

        $command[] = $this->try_job_name;

        $extra_args = $this->getBuildExtraArguments($show_results);

		$options = $this->getOptions();

        return implode(' ', array_merge($command, $extra_args, $options));
    }


    function executeCallbacks() {
        foreach ($this->getCallbacks() as $callback) {
            $this->executeCallback($callback);
        }
    }

    function executeCallback($callback) {
        $callback = str_replace(
            array('${status}', '${url}'),
            array($this->try_status, $this->try_base_url),
            $callback
        );
        $this->cmd_runner->run($callback, false, true);
    }
}
