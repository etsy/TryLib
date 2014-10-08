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
            $whitelisted_files,
            $override_user,
            $prechecks,
            $options_tuple,
            $ssh_key_path) {
        $this->repo_manager = self::requireArg($repo_manager);
        $this->jenkins_runner = self::requireArg($jenkins_runner);
        $this->jenkins_cli_jar_path = self::requireArg($jenkins_cli_jar_path);
        $this->whitelisted_files = $whitelisted_files ?: array();
        $this->override_user = $override_user ?: getenv("USER");
        $this->prechecks = $prechecks ?: array();
        $this->options_tuple = self::requireArg($options_tuple);
        $this->ssh_key_path = $ssh_key_path;
    }

    private static function requireArg($arg) {
        if (!$arg) {
            throw new InvalidArgumentException();
        }
        return $arg;
    }

    public function run() {
        list($options, $flags, $extra) = $this->options_tuple;

        $this->repo_manager->setRemoteBranch($options->branch);
        // Resolve the given remote branch value to a real ref.
        $remote_branch = $this->repo_manager->getRemoteBranch();

        if ($options->whitelist) {
            $whitelist = $options->whitelist;
            if (is_string($whitelist)) {
                $whitelist = array($whitelist);
            }
        } else {
            $whitelist = $this->whitelisted_files;
        }

        $this->repo_manager->runPrechecks($this->prechecks);

        $patch = $options->patch;
        if ($options->patch_stdin) {
            $patch = $this->readPatchFromStdin($options->wcpath);
        }
        if (is_null($patch)) {
            $patch = $this->repo_manager->generateDiff($options->staged, $whitelist);
        }

        if ($options->diff_only) {
            print 'Not sending job to Jenkins (-n) diff is here:' . $patch . PHP_EOL;
            exit(0);
        }

        $this->jenkins_runner->setPatch(realpath($patch));
        if ($this->ssh_key_path) {
            $this->jenkins_runner->setSshKey($this->ssh_key_path);
        }
        $this->jenkins_runner->setParam('branch', $remote_branch);
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
}
