<?php

class Try_Precheck_CopyAge implements Try_Precheck {
    private $maxAgeWarning;

    function __construct($maxAgeWarning = 24) {
        $this->maxAgeWarning = $maxAgeWarning;
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
     * it's greater than $maxAgeWarning in hrs ( defaults to 24)
     *
     * @param CommandRunner $cmdRunner  cmd runner object
     * @param string $repoPath          location of the git repo
     **/
    function check($cmdRunner, $repoPath) {
        $cmdRunner->run("git log -1 --format='%cd' --date=iso");
        $output = $cmdRunner->getLastOutput();
        if ( is_string($output)) {
            $wc_date = strtotime($output);
    
            $wc_age = time() - $wc_date;
    
            if ($wc_age >= $this->maxAgeWarning * 60 * 60) {
                echo "WARNING - you working copy is " . self::formatTimeDiff($wc_age) . " old.\n";
                echo "You may want to run `git rpull` to avoid merging conflicts in the try job.\n\n";
            }
        }
    }
}
