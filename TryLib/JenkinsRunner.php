<?php

class TryLib_JenkinsRunner {

    protected $jenkins_url;
    protected $jenkins_cli;
    protected $try_job_name;
    protected $cmd_runner;
    protected $overall_result;
    protected $try_base_url;
    protected $colors;

    private $branch;
    private $options;
    private $jobs;
    private $excluded_jobs;
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
        $this->jobs = array();
        $this->callbacks = array();
        $this->ssh_key_path = null;
        $this->overall_result = null;
        $this->try_base_url = null;
        $this->branch = null;

        $this->colors = null;
        if(defined("STDERR") && posix_isatty(STDERR)){
            $this->colors = new TryLib_Util_AnsiColor();
        }
    }

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
    public function startJenkinsJob($patch, $pollForCompletion = false) {
        // Explicitly log out user to force re-authentication over SSH
        $this->runJenkinsCommand("logout");

        // Build up the jenkins command incrementally
        $cli_command = $this->buildCLICommand($patch);

        // Run the job
        $this->runJenkinsCommand($cli_command);

        if ($pollForCompletion || !empty($this->callbacks)) {
            $this->pollForCompletion($pollForCompletion);
            $this->executeCallbacks();
        }
    }

    public function setSshKey($ssh_key_path) {
        if (file_exists($ssh_key_path)) {
            $this->ssh_key_path = $ssh_key_path;
        } else {
           echo PHP_EOL . "WARNING : SSH key file not found (${ssh_key_path})" . PHP_EOL;
        }
    }

    public function setUid($uid) {
        $this->options[] = "-p guid=$uid";
    }

    public function setBranch($branch) {
        $this->options[] = "-p branch=$branch";
    }

    public function setSubJobs($jobs) {
        $this->jobs = array_unique($jobs);
    }

    public function setExcludedSubJobs($jobs) {
        $this->excluded_jobs = array_unique($jobs);
    }

    public function getJobsList() {
        $tryjobs = array();
        foreach($this->jobs as $job) {
            if ( !in_array($job, $this->excluded_jobs)) {
                $tryjobs[] = $this->try_job_name . '-' . $job;
            }
        }

        foreach($this->excluded_jobs as $job) {
            $tryjobs[] = '-' . $this->try_job_name . '-' . $job;
        }
        return $tryjobs;
    }

    public function addCallback($callback) {
        if (is_null($callback)) {
            return;
        } else if (is_string($callback)) {
            $this->callbacks[] = $callback;
        } else {
           echo PHP_EOL . "WARNING : Invalid callback - must be a string" . PHP_EOL;
        }
    }

    /**
     * Build the Jenkins CLI command, based on all options
     */
    function buildCLICommand($patch) {
        $command = array();

        if (!is_null($this->ssh_key_path)) {
            $command[] = '-i ' . $this->ssh_key_path;
        }

        $command[] = "build-master";
        $command[] = $this->try_job_name;

        $command = array_merge($command, $this->getJobsList());

        $this->options[] = "-p patch.diff=" . $patch;

        return implode(' ' , array_merge($command, $this->options));
    }

    /**
     * Poll for completion of try job and print results
     */
    function pollForCompletion($pretty) {
        $try_output = $this->cmd_runner->getOutput();

        // Find job URL
        $matches = array();
        if (!preg_match('|http://[^/]+/job/' . $this->try_job_name . '/\d+|m', $try_output, $matches)) {
            $this->cmd_runner->terminate('Could not find ' . $this->try_job_name . 'URL' . PHP_EOL);
        }

        $this->try_base_url = $matches[0];
        $try_poll_url = $this->try_base_url . '/consoleText';

        $prev_text = '';

        // Poll job URL for completion
        while (true) {
            $try_log = file_get_contents($try_poll_url);

            $new_text = str_replace($prev_text, '', $try_log);
            $prev_text = $try_log;

            if ($pretty) {
                if ($this->printJobResults($new_text, $pretty)) {
                    echo PHP_EOL . '......... waiting for job to finish ..';
                }
            }

            if (preg_match('|^Finished: .*$|m', $try_log, $matches)) {
                echo PHP_EOL . $this->try_base_url . PHP_EOL;
                $this->overall_result = $matches[0];
                if (!$pretty) {
                    $this->printJobResults($try_log, $pretty);
                }
                echo PHP_EOL . $this->overall_result . PHP_EOL;
                $this->overall_result = str_replace("Finished: ", "", $this->overall_result);
                break;
            }
            if ($pretty) {
                echo '.';
            } else {
                echo '......... waiting for job to finish' . PHP_EOL;
            }
            sleep(30);
        }
    }

    /**
     * Given a string of the try logs, print the results from any individual
     * job found in the text.
     *
     * @param string $log
     * @access public
     * @return boolean Returns true if any job results were printed, false otherwise
     */
    function printJobResults($log, $pretty) {
        if (preg_match_all('|^\[([^\]]+)\] (try[^ ]+) (\(http://[^)]+\))$|m', $log, $matches)) {
            echo PHP_EOL . PHP_EOL;
            foreach ($matches[0] as $k => $_) {
                $success = $matches[1][$k];
                if ($pretty && !is_null($this->colors)) {
                    if ($success == 'SUCCESS') {
                        $success = $this->colors->green($success);
                    } else if ($success == 'UNSTABLE') {
                        $success = $this->colors->yellow($success);
                    } else {
                        $success = $this->colors->red($success);
                    }
                }

                printf(
                    "% 32s % -10s %s" . PHP_EOL,
                    $matches[2][$k],
                    $success,
                    $matches[1][$k] !== 'SUCCESS' ? $matches[3][$k] : ''
                );
            }
            return true;
        }
        return false;
    }

    function executeCallbacks() {
        foreach($this->callbacks as $callback) {
            $this->executeCallback($callback);
        }
    }

    function executeCallback($callback) {
        if (is_null($this->try_base_url) || is_null($this->overall_result)) {
            return;
        }

        $callback = str_replace(
            array('${status}', '${url}'),
            array($this->overall_result, $this->try_base_url),
            $callback
        );
        $this->cmd_runner->run($callback, false, true);
    }
}
