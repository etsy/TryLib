<?php

require_once "TryLib/Autoload.php";

class FreeStyleProjectTest extends PHPUnit_Framework_TestCase {
	const JENKINS_URL = 'url.to.jenkins.com:8080';
	const JENKINS_CLI = '/path/to/cli.jar';
	const JENKINS_JOB = 'test-try';

    private $jenkins_runner;
	private $mock_cmd_runner;

    function setUp() {
        parent::setUp();

		$this->mock_cmd_runner = $this->getMock('TryLib_CommandRunner');

        $this->jenkins_runner = new TryLib_JenkinsRunner_FreeStyleProject(
	        self::JENKINS_URL,
	        self::JENKINS_CLI,
	        self::JENKINS_JOB,
            $this->mock_cmd_runner
        );
    }

	function testGetBuildCommand() {
		$this->assertEquals('build', $this->jenkins_runner->getBuildCommand());
	}

    function testGetBuildExtraArgumentsNoPolling() {
		$this->assertEquals(array(), $this->jenkins_runner->getBuildExtraArguments(false));
	}

	function testGetBuildExtraArgumentsWithPolling() {
		$this->assertEquals(array('-s'), $this->jenkins_runner->getBuildExtraArguments(true));
	}

	function providePollForCompletionData() {
		return array(
			array('Completed ' . self::JENKINS_JOB . ' #1234 : SUCCESS',
				  'SUCCESS',
				  'http://' . self::JENKINS_URL . '/job/' . self::JENKINS_JOB .'/1234'),

			array('Completed ' . self::JENKINS_JOB . ' #1 : failure',
				  'failure',
				  'http://' . self::JENKINS_URL . '/job/' . self::JENKINS_JOB .'/1'),

			array('Random string', '', '')
		);
	}

	/** @dataProvider providePollForCompletionData */
	function testPollForCompletion($output, $status, $url){
		$this->mock_cmd_runner->expects($this->once())
							  ->method('getOutput')
							  ->will($this->returnValue($output));

		$this->jenkins_runner->pollForCompletion(true);

		$this->assertEquals($status, $this->jenkins_runner->try_status);
		$this->assertEquals($url, $this->jenkins_runner->try_base_url);
	}
}
