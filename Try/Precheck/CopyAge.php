<?php

class Try_Precheck_CopyAge implements Try_Precheck {
    private $max_age_warning;

    function __construct($max_age_warning = 24) {
        $this->max_age_warning = $max_age_warning;
    }

    /**
     * Return a human representation of a time difference
     *
     * @param int $secs time delta in secods
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
        $cmd_runner->run("git log -1 --format='%cd' --date=iso");
        $output = $cmd_runner->getOutput();
        if ( is_string($output)) {
            $wc_date = strtotime($output);
    
            $wc_age = time() - $wc_date;
    
            if ($wc_age >= $this->max_age_warning * 60 * 60) {
                echo 'WARNING - you working copy is ' . self::formatTimeDiff($wc_age) . ' old.' . PHP_EOL;
                echo 'You may want to run `git rpull` to avoid merging conflicts in the try job.' . PHP_EOL . PHP_EOL;
            }
        }
    }
}
