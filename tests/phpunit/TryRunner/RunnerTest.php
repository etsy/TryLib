<?php

require_once 'TryLib/TryRunner/Options.php';
require_once 'TryLib/TryRunner/Runner.php';

/**
 * Simple integration tests for TryLib_TryRunner_Runner to make sure expected method calls are made
 * given certain options.
 */
class TryRunner_RunnerTest extends PHPUnit_Framework_TestCase {

    public function testSimple() {
        $options_tuple = TryLib_TryRunner_Options::parse(
            array("--branch", "testbranch", "-U", 10),
            "jenkins_job",
            "jenkins_job_prefix",
            "jenkins_server",
            "/path/to/working/copy");

        $repo_manager = new TryRunner_RunnerTest__TestRepoManager();
        $jenkins_runner = new TryRunner_RunnerTest__TestJenkinsRunner();
        list($options, $flags, $extra) = $options_tuple;

        $try_runner = new TryLib_TryRunner_Runner(
            $repo_manager,
            $jenkins_runner,
            "test_cli_jar_path",
            array(),
            "test_user",
            array(),
            $options_tuple,
            "/test/ssh/key/path"
        );
        $result = $try_runner->run();

        $this->assertEquals(0, $result);
        $this->assertEquals("testbranch", $repo_manager->getRemoteBranch());
        $this->assertTrue($repo_manager->ran_prechecks);
        $this->assertContains("some CLI command", $jenkins_runner->commands_run);
        $this->assertEquals("/test/ssh/key/path", $jenkins_runner->ssh_key_path);
        $this->assertEquals(10, $options->lines_of_context);
    }

    /**
     * Tests that the JenkinsRunner gets passed the remote branch auto-detected by the RepoManager.
     *
     * In other words, if the RepoManager is given a null default remote and magically decides
     * to use the remote 'autodetected_branch', then make sure the JenkinsRunner gets passed
     * 'autodetected_branch' as the branch to use.
     */
    public function testHonorRemoteBranch() {
        $options_tuple = TryLib_TryRunner_Options::parse(
            array(),
            "jenkins_job",
            "jenkins_job_prefix",
            "jenkins_server",
            "/path/to/working/copy",
            null  /* Set no default remote, to enable branch auto-detection. */);

        $repo_manager = new TryRunner_RunnerTest__TestRepoManagerWithDetectedBranch();
        $jenkins_runner = new TryRunner_RunnerTest__TestJenkinsRunner();

        $try_runner = new TryLib_TryRunner_Runner(
            $repo_manager,
            $jenkins_runner,
            "test_cli_jar_path",
            array(),
            "test_user",
            array(),
            $options_tuple,
            "/test/ssh/key/path"
        );
        $result = $try_runner->run();

        $this->assertEquals(0, $result);
        $this->assertEquals("autodetected_branch", $repo_manager->getRemoteBranch());
        $this->assertTrue($repo_manager->ran_prechecks);
        $this->assertContains("some CLI command", $jenkins_runner->commands_run);
        $this->assertEquals("/test/ssh/key/path", $jenkins_runner->ssh_key_path);

    }
}


class TryRunner_RunnerTest__TestRepoManager implements TryLib_RepoManager {

    public $remote_branch = null;
    public $ran_prechecks = false;

    public function setRemoteBranch($remote_branch) {
        $this->remote_branch = $remote_branch;
    }

    public function getRemoteBranch() {
        return $this->remote_branch;
    }

    public function generateDiff() {
        // Return a path string with some valid interpretation. The empty string is interpreted as
        // the current dir by realpath(), so use that.
        return '';
    }

    public function runPreChecks(array $pre_checks) {
        $this->ran_prechecks = true;
    }
}

class TryRunner_RunnerTest__TestRepoManagerWithDetectedBranch
    extends TryRunner_RunnerTest__TestRepoManager {

    public function setRemoteBranch($remote_branch) {
        if (!is_null($remote_branch)) {
            throw new RuntimeException('Null default remote branch expected');
        }
        $this->remote_branch = $remote_branch;
    }

    public function getRemoteBranch() {
        return "autodetected_branch";
    }
}

class TryRunner_RunnerTest__TestJenkinsRunner extends TryLib_JenkinsRunner {

    public $commands_run = array();
    public $ssh_key_path = null;

    public function __construct() {}

    protected function pollForCompletion($pretty) {}

    protected function getBuildCommand() {}

    protected function getBuildExtraArguments($show_results, $show_progress) {}

    public function runJenkinsCommand($command) {
        $this->commands_run[] = $command;
    }

    public function buildCLICommand($show_results, $show_progress) {
        return "some CLI command";
    }

    public function setSshKey($ssh_key_path) {
        $this->ssh_key_path = $ssh_key_path;
    }
}
