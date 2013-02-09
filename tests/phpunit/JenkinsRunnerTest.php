<?php

require_once "TryLib/Autoload.php";
require_once 'vfsStream/vfsStream.php';


class TestRunner extends TryLib_JenkinsRunner{

    public function getBuildCommand() {
        return 'test';
    }

    public function getBuildExtraArguments($poll_for_completion) {
        return array('test1', 'test2');
    }

    public function pollForCompletion($pretty) {
		return;
    }
}

class JenkinsRunnerTest extends PHPUnit_Framework_TestCase {
	const JENKINS_URL = 'url.to.jenkins.com:8080';
	const JENKINS_CLI = '/path/to/cli.jar';
	const JENKINS_JOB = 'test-try';
	
    private $jenkins_runner;

    function setUp() {
        parent::setUp();
		
		$this->mock_cmd_runner = $this->getMock('TryLib_CommandRunner');
		
        $this->jenkins_runner = new TestRunner(
            self::JENKINS_URL,
            self::JENKINS_CLI,
            self::JENKINS_JOB,
            $this->mock_cmd_runner
        );

		vfsStream::setup('testDir');
    }

	function testRunJenkinsCommand() {
		$expected_cmd = 'java -jar ' . self::JENKINS_CLI . ' -s http://' . self::JENKINS_URL . '/ dummy-cmd';
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('run')
							  ->with($this->equalTo($expected_cmd));

		$this->jenkins_runner->runJenkinsCommand('dummy-cmd');
	}
	
	function testSetParam() {
		$this->jenkins_runner->setParam('foo', 'bar');
		$this->jenkins_runner->setParam('foo', 'baz');
		
		$actual = $this->jenkins_runner->getOptions();
		$expected = array('-p foo=bar', '-p foo=baz');
		$this->assertEquals($expected, $actual);
	}
	
	function testSetDuplicateParam() {
		$this->jenkins_runner->setParam('foo', 'bar');
		$this->jenkins_runner->setParam('foo', 'bar');
		$this->jenkins_runner->setParam('foo', 'baz');
		$this->jenkins_runner->setParam('foo', 'baz');
		
		$actual = $this->jenkins_runner->getOptions();
		$expected = array('-p foo=bar', '-p foo=baz');
		$this->assertEquals($expected, $actual);
	}
	
	function testSetSshKeyFileExists() {
		$expected = 'testDir/try_id_rsa';
		vfsStream::newFile('try_id_rsa')
			->at(vfsStreamWrapper::getRoot());

		$this->jenkins_runner->setSshKey(vfsStream::url($expected));
		$this->assertEquals('vfs://' . $expected, $this->jenkins_runner->ssh_key_path);
	}
	
	function testSetSshKeyFileDoesNotExists() {
		$this->mock_cmd_runner->expects($this->once())
							  ->method('warn')
							  ->with($this->equalTo('SSH key file not found (~/foo)'));
		$this->jenkins_runner->setSshKey('~/foo');
		$this->assertNull($this->jenkins_runner->ssh_key_path);
	}
}
