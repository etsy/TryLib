<?php

/**
 * Return a human representation of a time difference
 *
 * @param int $secs time delta in secods
 * @return string human representation of time difference
 **/
function formatTimeDiff($secs) {
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
 * it's greater than $max_age_warning in hrs ( defaults to 24)
 *
 * @param string $location        location of the git repo
 * @param int    $max_age_warning maximum age in hrs to trigger the warning
 **/
function checkCopyAge($cmdRunner, $location, $max_age_warning=24) {

    $cmdRunner->run("cd $location && git log -1 --format='%cd' --date=iso");
    $output = $cmdRunner->getLastOutput();
    if ( is_string($output)) {
        $wc_date = strtotime($output);

        $wc_age = time() - $wc_date;

        if ($wc_age >= $max_age_warning * 60 * 60) {
            echo "WARNING - you working copy is " . formatTimeDiff($wc_age) . " old.\n";
            echo "You may want to run `git rpull` to avoid merging conflicts in the try job.\n\n";
        }
    }
}

/**
 * This checks to see if you are committing large binary files to the repo (which will fail on CI).
 **/
function checkBinaryFileSizes($cmdRunner, $location) {
    if (file_exists($location . '/bin/check_file_size')) {
        $return = $cmdRunner->run("cd $location && bin/check_file_size");
        if ($return) {
            $cmdRunner->terminate("Binary file size check failed with output: " . $cmdRunner->getOutput());
        }
    }
}
