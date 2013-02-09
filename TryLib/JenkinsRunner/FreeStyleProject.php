<?php

class TryLib_JenkinsRunner_FreeStyleProject extends TryLib_JenkinsRunner{

    public function getBuildCommand() {
        return 'build';
    }

    public function getBuildExtraArguments($poll_for_completion) {
        $args = array();

        if ($poll_for_completion) {
            $args[] = '-s';
        }

        return $args;
    }

    /**
     * Retrieve build number and status from the output
     */
    public function pollForCompletion($pretty) {
        $out = $this->cmd_runner->getOutput();

        if (preg_match('|Completed ' . $this->try_job_name . ' #(\d+) : (.*)|m', $out, $matches)) {
			$this->try_status = $matches[2];
            $this->try_base_url = sprintf(
                'http://%s/job/%s/%s',
                $this->jenkins_url,
                $this->try_job_name,
                $matches[1]
            );
        }
    }
}
