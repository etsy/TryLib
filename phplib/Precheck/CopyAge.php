<?php

class Precheck_CopyAge implements Precheck {
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
        $bit = array(
            ' year'        => $secs / 31556926 % 12,
            ' week'        => $secs / 604800 % 52,
            ' day'        => $secs / 86400 % 7,
            ' hour'        => $secs / 3600 % 24,
            ' minute'    => $secs / 60 % 60,
            ' second'    => $secs % 60
            );
    
        foreach ($bit as $k => $v) {
            if ($v > 1) {
                $ret[] = $v . $k . 's';
            }
            if ($v == 1) {
                $ret[] = $v . $k;
            }
        }
    
        array_splice($ret, count($ret)-1, 0, 'and');
    
        return join(' ', $ret);
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
