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
		$this->assertEquals('vfs://' . $expected, $this->jenkins_runner->getSsKey());
	}
	
	function testSetSshKeyFileDoesNotExists() {
		$this->mock_cmd_runner->expects($this->once())
							  ->method('warn')
							  ->with($this->equalTo('SSH key file not found (~/foo)'));
		$this->jenkins_runner->setSshKey('~/foo');
		$this->assertNull($this->jenkins_runner->getSsKey());
	}

	function testPatchFileExists() {
		$patch = 'testDir/patch.diff';
		vfsStream::newFile('patch.diff')
			->at(vfsStreamWrapper::getRoot());

		$this->jenkins_runner->setPatch(vfsStream::url($patch));

		$expected = array('-p patch.diff=vfs://' . $patch);
		$this->assertEquals($expected, $this->jenkins_runner->getOptions());
	}

	function testPatchFileDoesNotExists() {
		$this->mock_cmd_runner->expects($this->once())
							  ->method('terminate')
							  ->with($this->equalTo('Patch file not found (vfs://patch.diff)'));

		$this->jenkins_runner->setPatch(vfsStream::url('patch.diff'));
		$this->assertEquals(array(), $this->jenkins_runner->getOptions());
	}

	function testAddNullCallback() {
		$this->jenkins_runner->addCallback(null);
		$this->assertEquals(array(), $this->jenkins_runner->getCallbacks());
	}

	function testAddStringCallback() {
		$callback = 'echo "Hello Test"';
		$this->jenkins_runner->addCallback($callback);
		$this->assertEquals(array($callback), $this->jenkins_runner->getCallbacks());
	}

	function testAddObjallback() {
		$this->mock_cmd_runner->expects($this->once())
							  ->method('warn')
							  ->with($this->equalTo('Invalid callback - must be a string'));
		$this->jenkins_runner->addCallback((object) 'echo');
		$this->assertEquals(array(), $this->jenkins_runner->getCallbacks());
	}
	
	function provideCallbackData() {
		return array(
			array('echo "hello world"', 'SUCCESS', 'URL', 'echo "hello world"'),
			array('echo "${status}"', 'SUCCESS', 'URL', 'echo "SUCCESS"'),
			array('echo "${url}"', 'SUCCESS', 'URL', 'echo "URL"'),
			array('echo "${status} : ${url}"', 'SUCCESS', 'URL', 'echo "SUCCESS : URL"')
		);
	}
	
	/** @dataProvider provideCallbackData */
	function testExecuteCallback($callback, $status, $url, $expected) {
		$this->jenkins_runner->overall_result = $status;
		$this->jenkins_runner->try_base_url = $url;
		$this->mock_cmd_runner->expects($this->once())
							  ->method('run')
							  ->with($this->equalTo($expected));
		$this->jenkins_runner->executeCallback($callback);
	}
}