<?php

namespace TryLib\JenkinsRunner;

use TryLib\JenkinsRunner;

class FreeStyleProject extends JenkinsRunner{

    public function getBuildCommand() {
        return 'build';
    }

    public function getBuildExtraArguments($show_results, $show_progress) {
        $args = [];

        if ($show_results || $show_progress) {
            $args[] = '-s';
        }

        if ($show_progress) {
            $args[] = '-v';
        }

        return $args;
    }

    /**
     * Retrieve build number and status from the output
     */
    public function pollForCompletion($show_progress) {
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
