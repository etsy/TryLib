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
    public static function formatTimeDiff($secs) {
        $d = new DateTime();
        $d->add(new DateInterval('PT' . $secs . 'S'));
        $dv=$d->diff(new DateTime());
        return $dv->format("%m month, %d days, %h hours, %m minutes and %s seconds");
    }

    /**
     * Check the age of the working copy and warn user if
     * it's greater than $max_age_warning in hrs ( defaults to 24)
     *
     * @param CommandRunner $cmd_runner  cmd runner object
     * @param string $repo_path          location of the git repo
     **/
    function check($cmd_runner, $repo_path) {
        $cmd = "git log -1 --format='%cd' --date=iso";
        if (!is_null($this->remote_branch)) {
            $cmd .= ' origin/' . $this->remote_branch;
        }

        $cmd_runner->run($cmd);
        $output = $cmd_runner->getOutput();
        if ( is_string($output)) {
            $wc_date = strtotime($output);

            $wc_age = time() - $wc_date;
            if ($wc_age >= $this->max_age_blocking) {
                $msg = 'ERROR - you working copy is ' . self::formatTimeDiff($wc_age) . ' old.' . PHP_EOL;
                $msg .= 'The code you want to `try` does not reflect the state of the repository' . PHP_EOL;
                $msg .= 'Please run `git pull` and try again' . PHP_EOL . PHP_EOL;
                $cmd_runner->terminate($msg);
            } elseif ($wc_age >= $this->max_age_warning) {
                $cmd_runner->warn('Your working copy is ' . self::formatTimeDiff($wc_age) . ' old.');
                $cmd_runner->warn('You may want to run `git fetch` to avoid merging conflicts in the try job.' . PHP_EOL);
            }
        }
    }
}
