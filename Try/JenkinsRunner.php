<?php

class Try_JenkinsRunner {

    private $jenkinsUrl;
    private $jenkinsCli;
    private $tryJobName;
    private $cmdRunner;

    private $branch;
    private $options;
    private $jobs;
    private $callbacks;
    private $overall_result;
    private $try_base_url;

    public function __construct(
        $jenkinsUrl,
        $jenkinsCli,
        $tryJobName,
        $cmdRunner
    ) {
        $this->jenkinsUrl = $jenkinsUrl;
        $this->jenkinsCli = $jenkinsCli;
        $this->tryJobName = $tryJobName;
        $this->cmdRunner = $cmdRunner;

        $this->options = array();
        $this->jobs = array();
        $this->callbacks = array();
        $this->overall_result = null;
        $this->try_base_url = null;
        $this->branch = null;
    }

    public function runJenkinsCommand($command) {
        $cmd = sprintf(
            "java -jar %s -s http://%s/ %s",
            $this->jenkinsCli,
            $this->jenkinsUrl,
            $command
        );
        $this->cmdRunner->run($cmd);
    }

    /**
     * Logout, and Start the Jenkins job
     */
    public function startJenkinsJob($patch, $pollForCompletion=false) {
        // Explicitly log out user to force re-authentication over SSH
        $this->runJenkinsCommand("logout");

        // Build up the jenkins command incrementally
        $cliCommand = $this->buildCLICommand($patch);

        // Run the job
        $this->runJenkinsCommand($cliCommand);

        if ($pollForCompletion || !empty($this->callbacks)) {
            $this->pollForCompletion($pollForCompletion);
            $this->executeCallbacks();
        }
    }

    public function setSshKey($sshKeyPath) {
        if (file_exists($sshKeyPath)) {
            $this->options[] = "-i $sshKeyPath";
        }
    }

    public function setUid($uid) {
        $this->options[] = "-p guid=$uid";
    }

    public function setBranch($branch) {
        $this->options[] = "-p branch=$branch";
    }

    public function setSubJobs($jobs) {
        $this->jobs = $jobs;
    }

    public function addCallback($callback) {
        if (is_string($callback)) {
            $this->callbacks[] = $callback;
        }
    }

    /**
     * Build the Jenkins CLI command, based on all options
     */
    function buildCLICommand($patch) {
        $command = array("build-master");
        $command[] = $this->tryJobName;

        foreach($this->jobs as $job) {
            $command[] = $this->tryJobName . "-" . $job;
        }

        $this->options[] = "-p patch.diff=" . $patch;

        return implode(' ' , array_merge($command, $this->options));
    }

    /**
     * Poll for completion of try job and print results
     */
    function pollForCompletion($pretty) {
        $try_output = $this->cmdRunner->getLastOutput();

        // Find job URL
        $matches = array();
        if (!preg_match('|http://[^/]+/job/' . $this->tryJobName . '/\d+|m', $try_output, $matches)) {
            $this->cmdRunner->terminate('Could not find ' . $this->tryJobName . 'URL' . PHP_EOL);
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
        $colors = new Try_Util_AnsiColor();
        if (preg_match_all('|^\[([^\]]+)\] (try[^ ]+) (\(http://[^)]+\))$|m', $log, $matches)) {
            echo PHP_EOL . PHP_EOL;
            foreach ($matches[0] as $k => $_) {
                $success = $matches[1][$k];
                if ($pretty) {
                    if ($success == 'SUCCESS') {
                        $success = $colors->green($success);
                    } else if ($success == 'UNSTABLE') {
                        $success = $colors->yellow($success);
                    } else {
                        $success = $colors->red($success);
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

        $callback = str_replace('${status}', $this->overall_result, $callback);
        $callback = str_replace('${url}', $this->try_base_url, $callback);
        $this->cmdRunner->run($callback);
    }
}
