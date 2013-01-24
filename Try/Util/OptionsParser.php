<?php

class Try_Util_OptionsParser {
	public static function getOptions() {
		global $argv;
	
	    $options = array(
	        'jobs' => array(),
	        'verbose' => false,
	        'dry-run' => false,
	        'patch' => null,
	        'staged-only' => false,
	        'poll_for_completion' => false,
	        'callback' => null
	        );

	    // Using the evil @ operator here because Console_Getopt
	    // is still PHP4 and spews a bunch of deprecation warnings:
	    $ret = @Console_Getopt::getopt($argv, 'h?vnp:C:cPs');

	    if ($ret instanceOf PEAR_Error) {
	        error_log($ret->getMessage());
	        self::showHelp();
	    }

	    list($opt, $args) = $ret;

	    foreach ($opt as $tuple) {
	        list($k, $v) = $tuple;

	        switch($k) {
	            case 'h':
	            case '?':
	                self::showHelp();
	                break;

	            case 'P':
	                $options['poll_for_completion'] = true;
	                break;

	            case 'v':
	                $options['verbose'] = true;
	                break;

	            case 'n':
	                $options['dry-run'] = true;
	                break;

	            case 'p':
	                $options['patch'] = $v;
	                break;

	            case 'c':
	                $options['poll_for_completion'] = true;
	                break;

	            case 's':
	                $options['staged-only'] = true;
	                break;

	            case 'C':
	                $options['callback'] = $v;
	                break;
	        }
	    }

	    if (count($args)) {
	        $options['jobs'] = $args;
	    }

	    return $options;
	}

	/**
	 * Display the help menu.
	 */
	public static function showHelp() {
	    print <<<eof
USAGE: try [options] suite [tests ...]

OPTIONS:
    -h          show help
    -n          create diff, but do not send to Hudson
    -v          verbose (show shell commands as they're run)
    -p path     don't generate diffs; use custom patch file instead
    -c          poll for job completion and print results
    -P          print subtasks progressively as they complete (implies c)
    -s          use staged changes only to generate the diff
    -C          Callback string to execute at the end of the try run.
                Use ${status} and ${url} as placeholders for the try build status and url
                Example: -C 'echo "**Try status : [${status}](${url})**"'
eof
		;
	    print PHP_EOL . PHP_EOL;
	    exit(0);
	}
}
