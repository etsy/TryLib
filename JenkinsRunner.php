<?php
require_once 'AnsiColor.php';

const TRY_VERSION = 1;

class JenkinsRunner {
    
    //const HUDSON = "try.etsycorp.com";
    const HUDSON = "cimaster-dev2.vm.ny4dev.etsy.com:8080";
    const JENKINS_CLI = '/usr/etsy/jenkins-cli.jar';
    
    private $cmdRunner;
    private $branch;
    private $patch;
    private $user;

    public function __construct(
        $cmdRunner,
        $branch,
        $patch,
        $user
    ) {
        $this->cmdRunner = $cmdRunner;
        $this->branch = $branch;
        $this->patch = $patch;
        $this->user = $user;
    }

    public function runJenkinsCommand($command) {
        $cmd = sprintf("java -jar %s -s http://%s/ %s", self::JENKINS_CLI, self::HUDSON, $command);
        $this->cmdRunner->run($cmd);    
    }

    /**
     * Logout, and Start the Jenkins job
     */
    public function startJenkinsJob($jobs) {
        // Explicitly log out user to force re-authentication over SSH
        $this->runJenkinsCommand("logout");
        
        // Build up the jenkins command incrementally
        $cliCommand = $this->buildCLICommand($jobs);
        
        // Run the job
        $this->runJenkinsCommand($cliCommand);
    }

    /**
     * Build the Jenkins CLI command, based on all options
     */
    function buildCLICommand($jobs) {
        $command = "";

        $optional_ssh_key = "/home/" . $this->user . "/.ssh/try_id_rsa";
        if (file_exists($optional_ssh_key)) {
            $command .= " -i $optional_ssh_key";
        }

        $command .= " build-master try ";

        if (count($jobs)) {
            $command .= "try-" . implode(" try-", $jobs);
        }

        $time = time();
        $guid = "$this->user"."$time";

        $command .= " -p guid=$guid ";
        $command .= " -p ssh_login=true";
        $command .= " -p branch=" . $this->branch;
        $command .= " -p patch.diff=" . $this->patch;
        $command .= " -p try_version=" . TRY_VERSION;

        return $command;
    }

    /**
     * Poll for completion of try job and print results
     */
    function pollForCompletion($pretty) {
        $try_output = $this->cmdRunner->getLastOutput();
        
        // Find job URL
        $matches = array();
        if (!preg_match('|http://[^/]+/job/try/\d+|m', $try_output, $matches)) {
            echo "Could not find try URL\n";
            exit(1);
        }
        $try_base_url = $matches[0];
        $try_poll_url = $try_base_url . '/consoleText';

        $prev_text = '';

        // Poll job URL for completion
        while (true) {
            $try_log = file_get_contents($try_poll_url);

            $new_text = str_replace($prev_text, '', $try_log);
            $prev_text = $try_log;

            if ($pretty) {
                if ($this->printJobResults($new_text, $pretty)) {
                    echo "\n......... waiting for job to finish ..";
                }
            }

            if (preg_match('|^Finished: .*$|m', $try_log, $matches)) {
                echo "\n\n{$try_base_url}\n";
                $overall_result = $matches[0];
                if (!$pretty) {
                    $this->printJobResults($try_log, $pretty);
                }
                echo "\n{$overall_result}\n";
                break;
            }
            if ($pretty) {
                echo ".";
            } else {
                echo "......... waiting for job to finish\n";
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
        $colors = new AnsiColor();
        if (preg_match_all('|^\[([^\]]+)\] (try[^ ]+) (\(http://[^)]+\))$|m', $log, $matches)) {
            echo "\n\n";
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
                    "% 32s % -10s %s\n",
                    $matches[2][$k],
                    $success,
                    $matches[1][$k] !== 'SUCCESS' ? $matches[3][$k] : ''
                );
            }
            return true;
        }
        return false;
    }
}
