<?php

class TryLib_Util_OptionsParser {

    /**
      * Return the list of arguments in a command line (remove the options)
      */
    public static function pruneOptions($parsed_options) {
        global $argv;

        $pruneargv = array();
        foreach ($parsed_options as $option => $values) {
            if (!is_array($values)) {
                $values = array($values);
            }
            foreach ($values as $value) {
                foreach ($argv as $key => $chunk) {
                    $regex = '/^'. (isset($option[1]) ? '--' : '-') . $option . '/';
                    if ($chunk == $value && $argv[$key-1][0] == '-' || preg_match($regex, $chunk)) {
                        array_push($pruneargv, $key);
                    }
                }
            }
        }
        while ($key = array_pop($pruneargv)) {
            unset($argv[$key]);
        }

        return array_slice($argv, 1);
    }

    public static function getOptions() {
        global $argv;

        $options = array(
            'jobs' => array(),
            'exclude' => array(),
            'verbose' => false,
            'diffonly' => false,
            'patch' => null,
            'branch' => null,
            'staged' => false,
            'showprogress' => false,
            'callbacks' => array()
            );

        $parameters = array(
            'h' => 'help',
            'v' => 'verbose',
            'n' => 'diff-only',
            'e:' => 'exclude:',
            'b:' => 'branch:',
            'p:' => 'patch:',
            'c:' => 'callback:',
            'P' => 'show-progress',
            's' => 'staged'
        );

        $opt = getopt(implode('', array_keys($parameters)), $parameters);
        foreach ($opt as $k=>$v) {
            switch($k) {
                case 'h':
                case 'help':
                    self::showHelp();
                    break;

                case 'v':
                case 'verbose':
                    $options['verbose'] = true;
                    break;

                case 'n':
                case 'diff-only':
                    $options['diffonly'] = true;
                    break;

                case 'p':
                case 'patch':
                    $patch = realpath($v);
                    if (!file_exists($patch)) {
                        print "ERROR : Patch file `$v` not found" . PHP_EOL;
                        exit(1);
                    }
                    $options['patch'] = $patch;
                    break;

                case 'b':
                case 'branch':
                    $options['branch'] = $v;
                    break;

                case 'P':
                case 'show-progress':
                    $options['showprogress'] = true;
                    break;

                case 's':
                case 'staged':
                    $options['staged'] = true;
                    break;

                case 'c':
                case 'callback':
                    if (is_array($v)) {
                        $options['callbacks'] = $v;
                    } else {
                        $options['callbacks'][] = $v;
                    }
                    break;

                case 'e':
                case 'exclude':
                    if (is_array($v)) {
                        $options['exclude'] = $v;
                    } else {
                        $options['exclude'][] = $v;
                    }
                    break;
            }
        }

        $options['jobs'] = self::pruneOptions($opt);

        return $options;
    }

    /**
     * Display the help menu.
     */
    public static function showHelp() {
        print <<<eof
USAGE: try [options] [subjobs ...]

OPTIONS:
    -h --help                   Show help

    -n --diff-only              Create diff, but do not send to Hudson

    -v --verbose                Verbose (show shell commands as they're run)

    -p|--patch=</path/to/diff>  Don't generate diffs; use custom patch file instead

    -b|--branch=<remote branch> Name of the remote branch to diff and try against

    -e|--exclude=<job>          Subjob to exclude from the try run.
                                Use multiple -e|--exclude to exclude more than 1 job
                                Example: -e validate-css --exclude=unit-tests

    -P --show-progress          Print subtasks progressively as they complete (implies c)

    -s --staged                 Use staged changes only to generate the diff

    -c|--callback <string>      Callback string to execute at the end of the try run.
                                Use \${status} and \${url} as placeholders for the try build status and url
                                Example: -C 'echo "**Try status : [\${status}](\${url})**"'
eof
        ;
        print PHP_EOL . PHP_EOL;
        exit(0);
    }
}
