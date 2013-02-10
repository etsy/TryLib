<?php

class TryLib_Precheck_CopyAge implements TryLib_Precheck {
    protected $max_age_warning;
    protected $max_age_blocking;
    protected $remote_branch;

    function __construct(
        $max_age_warning = 24,
        $max_age_blocking = 48,
        $remote_branch = null
    ) {
        $this->max_age_warning = $max_age_warning * 60 * 60;
        $this->max_age_blocking = $max_age_blocking * 60 * 60;
        $this->remote_branch = $remote_branch;
    }

    /**
     * Return a human representation of a time difference
     *
     * @param int $secs time delta in seconds
     * @return string human representation of time difference
     **/
    public function formatTimeDiff($secs) {
        $d = new DateTime();
        $d->add(new DateInterval('PT' . $secs . 'S'));
        $dv=$d->diff(new DateTime());
        return $dv->format("%m month, %d days, %h hours, %m minutes and %s seconds");
    }

	public function getTimeDelta($since) {
		return time() - strtotime($since);
	}

	public function getLastFetchDate($cmd_runner) {
		$cmd = 'git log -1 --format=\'%cd\' --date=local';
        if (!is_null($this->remote_branch)) {
            $cmd .= ' origin/' . $this->remote_branch;
        }

        $ret = $cmd_runner->run($cmd);
        if ($ret) {
			return null;
		} else {
			return $cmd_runner->getOutput();
		}
	}

    /**
     * Check the age of the working copy and warn user if
     * it's greater than $max_age_warning in hrs ( defaults to 24)
     *
     * @param CommandRunner $cmd_runner  cmd runner object
     * @param string $repo_path          location of the git repo
     **/
    function check($cmd_runner, $repo_path) {
		$last_fetch = $this->getLastFetchDate($cmd_runner);
        if ( !is_null($last_fetch)) {
            $wc_age = $this->getTimeDelta($last_fetch);
            if ($wc_age >= $this->max_age_blocking) {
                $msg = 'ERROR - you working copy is ' . $this->formatTimeDiff($wc_age) . ' old.' . PHP_EOL;
                $msg .= 'The code you want to `try` does not reflect the state of the repository' . PHP_EOL;
                $msg .= 'Please run `git pull` and try again';
                $cmd_runner->terminate($msg);
            } elseif ($wc_age >= $this->max_age_warning) {
				$msg ='Your working copy is ' . $this->formatTimeDiff($wc_age) . ' old.' . PHP_EOL;
				$msg .= 'You may want to run `git fetch` to avoid merging conflicts in the try job.';
                $cmd_runner->warn($msg);
            }
        }
    }
}
