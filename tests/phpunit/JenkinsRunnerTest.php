<?php

require_once "TryLib/Autoload.php";
require_once 'vfsStream/vfsStream.php';


class TestRunner extends TryLib_JenkinsRunner{

    public function getBuildCommand() {
        return 'test';
    }

    public function getBuildExtraArguments($show_results, $show_progress) {
        return array();
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
    private $mock_cmd_runner;

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
        $this->jenkins_runner->try_status = $status;
        $this->jenkins_runner->try_base_url = $url;
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with($this->equalTo($expected));
        $this->jenkins_runner->executeCallback($callback);
    }


    function testBuildCLICommandNoSshKey() {
        $this->jenkins_runner->setParam('foo', 'bar');
        $this->jenkins_runner->setParam('foo', 'baz');

        $expected = 'test test-try -p foo=bar -p foo=baz';
        $actual = $this->jenkins_runner->buildCLICommand(true, true);
        $this->assertEquals($expected, $actual);
    }

    function testBuildCLICommandWithSshKey() {
        $this->jenkins_runner->setParam('foo', 'bar');

        $ssh_key = 'testDir/try_id_rsa';
        vfsStream::newFile('try_id_rsa')
            ->at(vfsStreamWrapper::getRoot());
        $this->jenkins_runner->setSshKey(vfsStream::url($ssh_key));

        $expected = '-i vfs://testDir/try_id_rsa test test-try -p foo=bar';
        $actual = $this->jenkins_runner->buildCLICommand(false, false);
        $this->assertEquals($expected, $actual);
    }

    function provideStartJenkinsJobParam() {
        return array(
            array(false, false, false, $this->never()),
            array(true, false, false, $this->any()),
            array(false, true, false, $this->any()),
            array(false, false, true, $this->any()),
            array(true, true, true, $this->any()),
            array(false, true, true, $this->any()),
            array(true, true, false, $this->any()),
            array(true, false, true, $this->any()) 
        );
    }

    /** @dataProvider provideStartJenkinsJobParam */
    function testStartJenkinsJob($show_results, $show_progress, $has_callbacks, $expected_call_count) {
        $jenkins_runner = $this->getMock(
                'TestRunner',
                array('runJenkinsCommand', 'buildCLICommand', 'pollForCompletion', 'getCallbacks', 'executeCallbacks'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, 'mock_runner')
        );

        $jenkins_runner->expects($this->at(0))
             ->method('buildCLICommand')
             ->with($this->equalTo($show_results))
             ->will($this->returnValue('cmd'));

        $jenkins_runner->expects($this->at(1))
             ->method('runJenkinsCommand')
             ->with($this->equalTo('cmd'));

        $jenkins_runner->expects($this->any())
             ->method('getCallbacks')
             ->will($this->returnValue($has_callbacks));

        $jenkins_runner->expects($expected_call_count)
                       ->method('pollForCompletion');

        $jenkins_runner->expects($expected_call_count)
                       ->method('executeCallbacks');

        $jenkins_runner->startJenkinsJob($show_results, $show_progress);
    }
}
