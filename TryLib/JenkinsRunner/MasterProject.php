<?php

class TryLib_JenkinsRunner_MasterProject extends TryLib_JenkinsRunner{
    protected $colors;
    private $jobs;
    private $excluded_jobs;

    public function __construct(
        $jenkins_url,
        $jenkins_cli,
        $try_job_name,
        $cmd_runner
    ) {
        parent::__construct(
            $jenkins_url,
            $jenkins_cli,
            $try_job_name,
            $cmd_runner
        );

        $this->jobs = array();
        $this->excluded_jobs = array();
        $this->colors = null;
    }

    public function getColors() {
        if (is_null($this->colors)) {
            if (defined("STDERR") && posix_isatty(STDERR)) {
                $this->colors = new TryLib_Util_AnsiColor();
            } else {
                $this->colors = false;
            }
        }
        return $this->colors;
    }

    public function getBuildCommand() {
        return 'build-master';
    }

    public function setSubJobs($jobs) {
        $this->jobs = array_unique($jobs);
    }

    public function setExcludedSubJobs($jobs) {
        $this->excluded_jobs = array_unique($jobs);
    }

    public function getJobsList() {
        $tryjobs = array();
        foreach ($this->jobs as $job) {
            if ( !in_array($job, $this->excluded_jobs)) {
                $tryjobs[] = $this->try_job_name . '-' . $job;
            }
        }

        foreach ($this->excluded_jobs as $job) {
            $tryjobs[] = '-' . $this->try_job_name . '-' . $job;
        }
        return $tryjobs;
    }


    /** For a master project, the extra arguments are a list of subjobs */
    public function getBuildExtraArguments($poll_for_completion) {
        return $this->getJobsList();
    }

    /**
     * Poll for completion of try job and print results
     */
    public function pollForCompletion($pretty) {
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
                $this->try_status = $matches[0];
                if (!$pretty) {
                    $this->printJobResults($try_log, $pretty);
                }
                echo PHP_EOL . $this->try_status . PHP_EOL;
                $this->try_status = str_replace("Finished: ", "", $this->try_status);
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
    public function printJobResults($log, $pretty) {
        $colors = $this->getColors();

        if (preg_match_all('|^\[([^\]]+)\] (try[^ ]+) (\(http://[^)]+\))$|m', $log, $matches)) {
            $this->cmd_runner->info(PHP_EOL);
            foreach ($matches[0] as $k => $_) {
                $success = $matches[1][$k];
                if ($pretty && $colors) {
                    if ($success == 'SUCCESS') {
                        $success = $colors->green($success);
                    } else if ($success == 'UNSTABLE') {
                        $success = $colors->yellow($success);
                    } else {
                        $success = $colors->red($success);
                    }
                }

	            $status = sprintf(
                    "% 32s % -10s %s",
                    $matches[2][$k],
                    $success,
                    $matches[1][$k] !== 'SUCCESS' ? $matches[3][$k] : ''
                );
				$this->cmd_runner->info($status);
            }
            return true;
        }
        return false;
    }
}
