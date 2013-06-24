<?php

class TryLib_JenkinsRunner_MasterProject extends TryLib_JenkinsRunner{
    protected $colors;
    private $jobs;
    private $excluded_jobs;
    private $polling_time;

    public function __construct(
        $jenkins_url,
        $jenkins_cli,
        $try_job_name,
        $cmd_runner,
        $polling_time = 20
    ) {
        parent::__construct(
            $jenkins_url,
            $jenkins_cli,
            $try_job_name,
            $cmd_runner
        );

        $this->jobs = array();
        $this->excluded_jobs = array();

        try {
            $this->colors = new TryLib_Util_AnsiColor();
        } catch (TryLib_Util_DisplayException $e) {
            $this->colors = false;
        }

        $this->polling_time = $polling_time;
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
    public function getBuildExtraArguments($show_results, $show_progress) {
        return $this->getJobsList();
    }

    public function getJobOutput() {
        if (is_string($this->try_base_url)) {
            return file_get_contents($this->try_base_url . '/consoleText');
        }
        return null;
    }

    /**
     * Poll for completion of try job and print results
     */
    public function pollForCompletion($show_progress) {
        $try_output = $this->cmd_runner->getOutput();

        // Find job URL
        if (!preg_match('|http://[^/]+/job/' . $this->try_job_name . '/\d+|m', $try_output, $matches)) {
            $this->cmd_runner->terminate('Could not find ' . $this->try_job_name . 'URL' . PHP_EOL);
        } else {
            $this->try_base_url = $matches[0];

            $prev_text = '';
            // Poll job URL for completion
            while (true) {
                $prev_text = $this->processLogOuput($prev_text, $show_progress);
                if (is_null($prev_text)) {
                    break;
                }
                sleep($this->polling_time);
            }
        }
    }

    public function processLogOuput($prev_text, $show_progress) {
        $try_log = $this->getJobOutput();

        $new_text = str_replace($prev_text, '', $try_log);
        $prev_text = $try_log;

        if ($show_progress) {
            $this->printJobResults($new_text);
        }

        if (preg_match('|^Finished: .*$|m', $try_log, $matches)) {
            $this->try_status = $matches[0];
            $this->try_status = str_replace("Finished: ", "", $this->try_status);

            $this->cmd_runner->info(
                PHP_EOL .
                sprintf('Try Status : %s (%s)',
                        $this->colorStatus($this->try_status),
                        $this->try_base_url
                        ) .
                PHP_EOL
            );
            return null;
        }

        if (!$show_progress) {
            $this->cmd_runner->info('......... waiting for job to finish');
        }
        return $prev_text;
    }

    public function colorStatus($status) {
        if ($this->colors) {
            if ($status == 'SUCCESS') {
                $status = $this->colors->green($status);
            } else if ($status == 'UNSTABLE') {
                $status = $this->colors->yellow($status);
            } else {
                $status = $this->colors->red($status);
            }
        }
        return $status;
    }

    /**
     * Given a string of the try logs, print the results from any individual
     * job found in the text.
     *
     * @param string $log
     * @access public
     * @return boolean Returns true if any job results were printed, false otherwise
     */
    public function printJobResults($log) {

        if (preg_match_all('|^\[([^\]]+)\] (' . $this->try_job_name . '[^ ]+) (\(http://[^)]+\))$|m', $log, $matches)) {
            $this->cmd_runner->info(PHP_EOL);
            foreach ($matches[0] as $k => $_) {
                $job_status = $matches[1][$k];

                $status = sprintf(
                    "% 32s % -10s %s",
                    $matches[2][$k],
                    $this->colorStatus($job_status),
                    $matches[1][$k] !== 'SUCCESS' ? $matches[3][$k] : ''
                );
                $this->cmd_runner->info($status);
            }
            return true;
        }
        return false;
    }
}
