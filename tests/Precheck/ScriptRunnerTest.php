<?php

namespace tests\phpunit\Precheck;

use TryLib\Precheck\ScriptRunner as ScriptRunner;
use org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamWrapper;

class ScriptRunnerTest extends \PHPUnit\Framework\TestCase {

    private $mock_cmd_runner;

    protected function setUp() {
        parent::setUp();
        $this->mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                      ->getMock();

        vfsStream::setup('testDir');
    }

    public function testScriptDoesNotExists() {

        $script_runner = new ScriptRunner(vfsStream::url('testDir/script'));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('run');

        $this->mock_cmd_runner->expects($this->never())
                              ->method('terminate');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'origin/master');
    }

    public function testScriptExistsAndSucceeds() {
        vfsStream::newFile('script')
            ->at(vfsStreamWrapper::getRoot());

        $script_runner = new ScriptRunner(vfsStream::url('testDir/script'));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('vfs://testDir/script', false, true)
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('terminate');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'origin/master');
    }

    public function testScriptExistsAndFails() {
        vfsStream::newFile('script')
            ->at(vfsStreamWrapper::getRoot());

        $script_runner = new ScriptRunner(vfsStream::url('testDir/script'));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('vfs://testDir/script', false, true)
                              ->will($this->returnValue(255));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('terminate')
                              ->with('Failed running pre-check script vfs://testDir/script');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'origin/master');
    }
}
