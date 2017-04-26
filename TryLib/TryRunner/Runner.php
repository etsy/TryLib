<?php

/**
 * Runs a Try job with given options.
 *
 * It's strongly recommended you use TryLib_TryRunner_Builder to create and configure an instance
 * of this class.
 *
 * Note that use of this class is optional. If you'd like, you can still put all the parts together
 * in your own try script.
 */
final class TryLib_TryRunner_Runner {

    public function __construct(
            $repo_manager,
            $jenkins_runner,
            $jenkins_cli_jar_path,
            $safelisted_files,
            $override_user,
            $prechecks,
            $options_tuple,
            $ssh_key_path) {
        $this->repo_manager = self::requireArg($repo_manager);
        $this->jenkins_runner = self::requireArg($jenkins_runner);
        $this->jenkins_cli_jar_path = self::requireArg($jenkins_cli_jar_path);
        $this->safelisted_files = $safelisted_files ?: array();
        $this->override_user = $override_user ?: getenv("USER");
        $this->prechecks = $prechecks ?: array();
        $this->options_tuple = self::requireArg($options_tuple);
        $this->ssh_key_path = $ssh_key_path;
        $this->patch = null;
    }

    private static function requireArg($arg) {
        if (!$arg) {
            throw new InvalidArgumentException();
        }
        return $arg;
    }

    public function getPatchLocation() {
        if (is_null($this->patch)) {
            list($options, $flags, $extra) = $this->options_tuple;

            $this->repo_manager->setRemoteBranch($options->branch);
            // Resolve the given remote branch value to a real ref.
            $remote_branch = $this->repo_manager->getRemoteBranch();
            // Set the remote branch parameter
            $this->jenkins_runner->setParam('branch', $remote_branch);

            if ($options->safelist) {
                $safelist = $options->safelist;
                if (is_string($safelist)) {
                    $safelist = array($safelist);
                }
            } else {
                $safelist = $this->safelisted_files;
            }

            $this->patch = $options->patch;
            if ($options->patch_stdin) {
                $this->patch = $this->readPatchFromStdin($options->wcpath);
            }
            $lines_of_context = false;
            if ($options->lines_of_context) {
                $lines_of_context = $options->lines_of_context;
            }
            if (is_null($this->patch)) {
                $this->patch = $this->repo_manager->generateDiff($options->staged, $safelist, $lines_of_context);
            }

            if (0 == filesize(realpath($this->patch))) {
                $this->printWarningSign();
                print "\nThe patch file is empty! There are no local changes.\n\nContinuing Try...\n\n";
            }
        }
        return $this->patch;
    }

    public function run() {
        list($options, $flags, $extra) = $this->options_tuple;

        $patch = $this->getPatchLocation();

        $this->repo_manager->runPrechecks($this->prechecks);

        if ($options->diff_only) {
            print 'Not sending job to Jenkins (-n) diff is here:' . $patch . PHP_EOL;
            exit(0);
        }

        $this->jenkins_runner->setPatch(realpath($patch));
        if ($this->ssh_key_path) {
            $this->jenkins_runner->setSshKey($this->ssh_key_path);
        }
        $this->jenkins_runner->setParam('guid', $this->override_user . time());

        $extra_params = TryLib_Util_OptionsUtil::parseExtraParameters($options->extra_param);
        foreach($extra_params as $param) {
            $this->jenkins_runner->setParam($param[0], $param[1]);
        }
        $this->jenkins_runner->addCallback($options->callback);

        $this->jenkins_runner->startJenkinsJob($options->show_results, $options->show_progress);
        return ($this->jenkins_runner->try_status === 'FAILURE') ? 1 : 0;
    }

    private function readPatchFromStdin($patch_dir) {
        $patch_file = "$patch_dir/patch.diff";
        $input = file_get_contents('php://stdin');
        file_put_contents($patch_file, $input);
        return $patch_file;
    }

    private function printWarningSign() {
        echo "[49m[K[0m[48;5;231m                                                  [49m
[48;5;231m                       [48;5;16m    [48;5;231m                       [49m
[48;5;231m                      [48;5;16m      [48;5;231m                      [49m
[48;5;231m                     [48;5;16m   [48;5;227m  [48;5;16m   [48;5;188m [48;5;231m                    [49m
[48;5;231m                    [48;5;16m   [48;5;227m    [48;5;16m    [48;5;231m                   [49m
[48;5;231m                  [48;5;188m [48;5;16m   [48;5;227m      [48;5;58m [48;5;16m   [48;5;231m                  [49m
[48;5;231m                 [48;5;102m [48;5;16m   [48;5;227m  [48;5;16m    [48;5;227m  [48;5;101m [48;5;16m   [48;5;231m                 [49m
[48;5;231m                [48;5;16m    [48;5;227m  [48;5;16m      [48;5;227m   [48;5;16m   [48;5;231m                [49m
[48;5;231m               [48;5;16m   [48;5;59m [48;5;227m   [48;5;16m      [48;5;227m    [48;5;16m   [48;5;231m               [49m
[48;5;231m              [48;5;16m   [48;5;185m [48;5;227m    [48;5;16m      [48;5;227m     [48;5;16m   [48;5;231m              [49m
[48;5;231m             [48;5;16m   [48;5;227m      [48;5;185m [48;5;16m     [48;5;227m      [48;5;16m    [48;5;231m            [49m
[48;5;231m            [48;5;16m   [48;5;227m        [48;5;16m    [48;5;59m [48;5;227m       [48;5;16m    [48;5;231m           [49m
[48;5;231m          [48;5;95m [48;5;16m   [48;5;227m         [48;5;16m    [48;5;227m         [48;5;101m [48;5;16m   [48;5;231m          [49m
[48;5;231m         [48;5;16m    [48;5;227m          [48;5;16m    [48;5;227m           [48;5;16m   [48;5;231m         [49m
[48;5;231m        [48;5;16m   [48;5;143m [48;5;227m           [48;5;16m    [48;5;227m            [48;5;16m   [48;5;231m        [49m
[48;5;231m       [48;5;16m   [48;5;227m                              [48;5;16m   [48;5;231m       [49m
[48;5;231m      [48;5;16m   [48;5;227m              [48;5;16m     [48;5;227m             [48;5;16m   [48;5;59m [48;5;231m     [49m
[48;5;231m     [48;5;16m   [48;5;227m              [48;5;16m      [48;5;227m              [48;5;16m    [48;5;231m    [49m
[48;5;231m   [48;5;188m [48;5;16m   [48;5;227m                [48;5;16m     [48;5;227m               [48;5;16m    [48;5;231m   [49m
[48;5;231m  [48;5;16m    [48;5;227m                                      [48;5;143m [48;5;16m   [48;5;231m  [49m
[48;5;231m  [48;5;16m                                               [48;5;231m [49m
[48;5;231m                                                  [0m";
        print "
     __          __              _                         
     \ \        / /             (_)              _
      \ \  /\  / /_ _ _ __ _ __  _ _ __   __ _  (_)
       \ \/  \/ / _` | '__| '_ \| | '_ \ / _` |
        \  /\  / (_| | |  | | | | | | | | (_| |  _
         \/  \/ \__,_|_|  |_| |_|_|_| |_|\__, | (_)
                                          __/ |
  ____  _             _       _____      |___/   _       _
 |  _ \| |           | |     |  __ \    | |     | |     | |
 | |_) | | __ _ _ __ | | __  | |__) |_ _| |_ ___| |__   | |
 |  _ <| |/ _` | '_ \| |/ /  |  ___/ _` | __/ __| '_ \  | |
 | |_) | | (_| | | | |   <   | |  | (_| | || (__| | | | |_|
 |____/|_|\__,_|_| |_|_|\_\  |_|   \__,_|\__\___|_| |_| (_)

                                                           ";
    }
}
