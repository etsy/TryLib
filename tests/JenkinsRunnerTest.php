<?php

namespace tests\phpunit;

use TryLib\JenkinsRunner as JenkinsRunner;
use org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamWrapper;

class TestRunner extends JenkinsRunner{

    public function getBuildCommand() {
        return 'test';
    }

    public function getBuildExtraArguments($show_results, $show_progress) {
        return [];
    }

    public function pollForCompletion($pretty) {
        return;
    }
}

class JenkinsRunnerTest extends \PHPUnit\Framework\TestCase {
    const JENKINS_URL = 'https://url.to.jenkins.com:8080/';
    const JENKINS_CLI = '/path/to/cli.jar';
    const JENKINS_JOB = 'test-try';

    private $jenkins_runner;
    private $mock_cmd_runner;

    protected function setUp() {
        parent::setUp();

        $this->mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                      ->getMock();

        $this->jenkins_runner = new TestRunner(
            self::JENKINS_URL,
            self::JENKINS_CLI,
            self::JENKINS_JOB,
            $this->mock_cmd_runner
        );

        vfsStream::setup('testDir');
    }

    /**
     * @expectedException Exception
     */
    public function testInvalidUrl() {
            $this->jenkins_runner = new TestRunner(
                'totallyvalid.com/',
                self::JENKINS_CLI,
                self::JENKINS_JOB,
                $this->mock_cmd_runner
            );
    }

    public function testRunJenkinsCommand() {
        $expected_cmd = 'java -jar ' . self::JENKINS_CLI . ' -s ' . self::JENKINS_URL . ' dummy-cmd';

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with($this->equalTo($expected_cmd));

        $this->jenkins_runner->runJenkinsCommand('dummy-cmd');
    }

    public function testSetParam() {
        $this->jenkins_runner->setParam('foo', 'bar');
        $this->jenkins_runner->setParam('foo', 'baz');

        $actual = $this->jenkins_runner->getOptions();
        $expected = ['-p foo=bar', '-p foo=baz'];
        $this->assertEquals($expected, $actual);
    }

    public function testSetDuplicateParam() {
        $this->jenkins_runner->setParam('foo', 'bar');
        $this->jenkins_runner->setParam('foo', 'bar');
        $this->jenkins_runner->setParam('foo', 'baz');
        $this->jenkins_runner->setParam('foo', 'baz');

        $actual = $this->jenkins_runner->getOptions();
        $expected = ['-p foo=bar', '-p foo=baz'];
        $this->assertEquals($expected, $actual);
    }

    public function testSetSshKeyFileExists() {
        $expected = 'testDir/try_id_rsa';
        vfsStream::newFile('try_id_rsa')
            ->at(vfsStreamWrapper::getRoot());

        $this->jenkins_runner->setSshKey(vfsStream::url($expected));
        $this->assertEquals('vfs://' . $expected, $this->jenkins_runner->getSsKey());
    }

    public function testSetSshKeyFileDoesNotExists() {
        $this->jenkins_runner->setSshKey('~/foo');
        $this->assertNull($this->jenkins_runner->getSsKey());
    }

    public function testPatchFileExists() {
        $patch = 'testDir/patch.diff';
        vfsStream::newFile('patch.diff')
            ->at(vfsStreamWrapper::getRoot());

        $this->jenkins_runner->setPatch(vfsStream::url($patch));

        $expected = ['-p patch.diff=vfs://' . $patch];
        $this->assertEquals($expected, $this->jenkins_runner->getOptions());
    }

    public function testPatchFileDoesNotExists() {
        $this->mock_cmd_runner->expects($this->once())
                              ->method('terminate')
                              ->with($this->equalTo('Patch file not found (vfs://patch.diff)'));

        $this->jenkins_runner->setPatch(vfsStream::url('patch.diff'));
        $this->assertEquals([], $this->jenkins_runner->getOptions());
    }

    public function testAddNullCallback() {
        $this->jenkins_runner->addCallback(null);
        $this->assertEquals([], $this->jenkins_runner->getCallbacks());
    }

    public function testAddStringCallback() {
        $callback = 'echo "Hello Test"';
        $this->jenkins_runner->addCallback($callback);
        $this->assertEquals([$callback], $this->jenkins_runner->getCallbacks());
    }

    public function testAddObjallback() {
        $this->mock_cmd_runner->expects($this->once())
                              ->method('warn')
                              ->with($this->equalTo('Invalid callback - must be a string'));
        $this->jenkins_runner->addCallback((object) 'echo');
        $this->assertEquals([], $this->jenkins_runner->getCallbacks());
    }

    public function provideCallbackData() {
        return [
            ['echo "hello world"', 'SUCCESS', 'URL', 'echo "hello world"'],
            ['echo "${status}"', 'SUCCESS', 'URL', 'echo "SUCCESS"'],
            ['echo "${url}"', 'SUCCESS', 'URL', 'echo "URL"'],
            ['echo "${status} : ${url}"', 'SUCCESS', 'URL', 'echo "SUCCESS : URL"']
        ];
    }

    /** @dataProvider provideCallbackData */
    public function testExecuteCallback($callback, $status, $url, $expected) {
        $this->jenkins_runner->try_status = $status;
        $this->jenkins_runner->try_base_url = $url;
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with($this->equalTo($expected));
        $this->jenkins_runner->executeCallback($callback);
    }


    public function testBuildCLICommandNoSshKey() {
        $this->jenkins_runner->setParam('foo', 'bar');
        $this->jenkins_runner->setParam('foo', 'baz');

        $expected = 'test test-try -p foo=bar -p foo=baz';
        $actual = $this->jenkins_runner->buildCLICommand(true, true);
        $this->assertEquals($expected, $actual);
    }

    public function testBuildCLICommandWithSshKey() {
        $this->jenkins_runner->setParam('foo', 'bar');

        $ssh_key = 'testDir/try_id_rsa';
        vfsStream::newFile('try_id_rsa')
            ->at(vfsStreamWrapper::getRoot());
        $this->jenkins_runner->setSshKey(vfsStream::url($ssh_key));

        $expected = '-i vfs://testDir/try_id_rsa test test-try -p foo=bar';
        $actual = $this->jenkins_runner->buildCLICommand(false, false);
        $this->assertEquals($expected, $actual);
    }

    public function provideStartJenkinsJobParam() {
        return [
            [false, false, false, $this->never()],
            [true, false, false, $this->any()],
            [false, true, false, $this->any()],
            [false, false, true, $this->any()],
            [true, true, true, $this->any()],
            [false, true, true, $this->any()],
            [true, true, false, $this->any()],
            [true, false, true, $this->any()]
        ];
    }

    /** @dataProvider provideStartJenkinsJobParam */
    public function testStartJenkinsJob($show_results, $show_progress, $has_callbacks, $expected_call_count) {
        $jenkins_runner = $this->getMockBuilder('tests\phpunit\TestRunner')
                               ->setMethods(['runJenkinsCommand', 'buildCLICommand', 'pollForCompletion', 'getCallbacks', 'executeCallbacks'])
                               ->setConstructorArgs([self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, 'mock_runner'])
                               ->getMock();

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
