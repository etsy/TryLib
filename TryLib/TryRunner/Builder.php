<?php

/**
 * A builder for configuring and creating a TryLib_TryRunner_Runner.
 */
final class TryLib_TryRunner_Builder {

    const PROJECT_TYPE_FREESTYLE = 1;
    const PROJECT_TYPE_MASTER = 2;

    public static function freeStyleProject() {
        return new TryLib_TryRunner_Builder(self::PROJECT_TYPE_FREESTYLE);
    }

    public static function masterProject() {
        return new TryLib_TryRunner_Builder(self::PROJECT_TYPE_MASTER);
    }

    private $project_type = null;
    private $jenkins_cli_jar_path = null;
    private $safelisted_files = null;
    private $override_user = null;
    private $prechecks = null;
    private $options_tuple = null;
    private $ssh_key_path = null;

    private function __construct($project_type) {
        $this->project_type = $project_type;
    }

    public function build() {
        list($options, $flags, $extra) = $this->options_tuple;

        $subjobs = array_slice($extra, 1);
        if (count($subjobs) > 0 && $this->project_type !== self::PROJECT_TYPE_MASTER) {
            throw new InvalidArgumentException(
                "You supplied a list of subjobs in your command, "
                . "but this Try job doesn't accept subjobs since it's not a master project.");
        }

        $cmd_runner = new TryLib_CommandRunner($options->verbose);
        $repo_manager = new TryLib_RepoManager_Git($options->wcpath, $cmd_runner);

        if ($this->project_type === self::PROJECT_TYPE_MASTER) {
            $jenkins_runner = new TryLib_JenkinsRunner_MasterProject(
                $options->jenkinsserver,
                $this->jenkins_cli_jar_path,
                $options->jenkinsjob,
                $cmd_runner,
                $options->jenkinsjobprefix
            );
            $jenkins_runner->setSubJobs($subjobs);

        } else if ($this->project_type === self::PROJECT_TYPE_FREESTYLE) {
            $jenkins_runner = new TryLib_JenkinsRunner_FreeStyleProject(
                $options->jenkinsserver,
                $this->jenkins_cli_jar_path,
                $options->jenkinsjob,
                $cmd_runner
            );

        } else {
            throw new RuntimeException("Unknown project type");
        }

        return new TryLib_TryRunner_Runner(
            $repo_manager,
            $jenkins_runner,
            $this->jenkins_cli_jar_path,
            $this->safelisted_files,
            $this->override_user,
            $this->prechecks,
            $this->options_tuple,
            $this->ssh_key_path);
    }

    public function jenkinsCliJarPath($jenkins_cli_jar_path) {
        $this->jenkins_cli_jar_path = $jenkins_cli_jar_path;
        return $this;
    }

    /**
     * An array of the only paths in your local working copy to include in a diff. Overriden by
     * the command line option --safelist.
     *
     * Defaults to an empty array.
     */
    public function safelistedFiles(array $safelisted_files) {
        $this->safelisted_files = $safelisted_files;
        return $this;
    }

    /**
     * The Jenkins username to use. Defaults to $USER.
     */
    public function overrideUser($override_user) {
        $this->override_user = $override_user;
        return $this;
    }

    /**
     * An array of TryLib_Precheck instances to run on your local working copy before sending a job
     * to Jenkins.
     *
     * Defaults to an empty array.
     */
    public function prechecks(array $prechecks) {
        $this->prechecks = $prechecks;
        return $this;
    }

    /**
     * Command-line options as parsed by TryLib_TryRunner_Options::parse().
     */
    public function optionsTuple(array $options_tuple) {
        $this->options_tuple = $options_tuple;
        return $this;
    }

    /**
     * Set the path to read for a Jenkins authentication key.
     */
    public function sshKeyPath($ssh_key_path) {
        $this->ssh_key_path = $ssh_key_path;
        return $this;
    }
}
