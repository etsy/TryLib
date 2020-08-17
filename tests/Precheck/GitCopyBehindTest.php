<?php

namespace tests\phpunit\Precheck;

use TryLib\Precheck\GitCopyBehind as GitCopyBehind;

class GitCopyBehindTest extends \PHPUnit\Framework\TestCase {

    public function testShouldRunCheckShouldRun() {
        $mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                ->getMock();

        $mock_cmd_runner->expects($this->once())
                        ->method('run')
                        ->with('git rev-parse --abbrev-ref HEAD');

        $mock_cmd_runner->expects($this->once())
                        ->method('getOutput')
                        ->will($this->returnValue('master'));

        $git_copy_behind_check = new GitCopyBehind(['master']);

        $this->assertTrue($git_copy_behind_check->shouldRunCheck($mock_cmd_runner));
    }

    public function testShouldRunCheckShouldNotRun() {
        $mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                ->getMock();

        $mock_cmd_runner->expects($this->once())
                        ->method('run')
                        ->with('git rev-parse --abbrev-ref HEAD');

        $mock_cmd_runner->expects($this->once())
                        ->method('getOutput')
                        ->will($this->returnValue('myfeature'));

        $git_copy_behind_check = new GitCopyBehind(['master']);

        $this->assertFalse($git_copy_behind_check->shouldRunCheck($mock_cmd_runner));
    }

    public function testShouldRunCheckNoBranches() {
        $mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                ->getMock();

        $mock_cmd_runner->expects($this->once())
                        ->method('run')
                        ->with('git rev-parse --abbrev-ref HEAD');

        $mock_cmd_runner->expects($this->once())
                        ->method('getOutput')
                        ->will($this->returnValue('master'));

        $git_copy_behind_check = new GitCopyBehind([]);

        $this->assertFalse($git_copy_behind_check->shouldRunCheck($mock_cmd_runner));
    }

    public function testCheckWorkingCopyBehind() {
        $mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                ->getMock();

        $mock_cmd_runner->expects($this->once())
                        ->method('run')
                        ->with('git rev-list HEAD..origin');

        $mock_cmd_runner->expects($this->once())
                        ->method('getOutput')
                        ->will($this->returnValue('60f7a69d993db7fb12c2ccdbf285df8ed217a09d'));

        $mock_cmd_runner->expects($this->once())
                        ->method('warn');

        $script_runner = $this->getMockBuilder('TryLib\Precheck\GitCopyBehind')
                              ->setMethods(['shouldRunCheck'])
                              ->setConstructorArgs([['master']])
                              ->getMock();

        $script_runner->expects($this->once())
                      ->method('shouldRunCheck')
                      ->with($mock_cmd_runner)
                      ->will($this->returnValue(true));

        $script_runner->check($mock_cmd_runner, 'path', 'origin/master');
    }

    public function testCheckWorkingCopyNotBehind() {
        $mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                ->getMock();

        $mock_cmd_runner->expects($this->once())
                        ->method('run')
                        ->with('git rev-list HEAD..origin');

        $mock_cmd_runner->expects($this->once())
                        ->method('getOutput')
                        ->will($this->returnValue(''));

        $mock_cmd_runner->expects($this->never())
                        ->method('warn');

        $script_runner = $this->getMockBuilder('TryLib\Precheck\GitCopyBehind')
                              ->setMethods(['shouldRunCheck'])
                              ->setConstructorArgs([['master']])
                              ->getMock();

        $script_runner->expects($this->once())
                      ->method('shouldRunCheck')
                      ->with($mock_cmd_runner)
                      ->will($this->returnValue(true));

        $script_runner->check($mock_cmd_runner, 'path', 'origin/master');
    }

    public function testCheckShouldNotRun() {
        $mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                ->getMock();

        $mock_cmd_runner->expects($this->never())
                        ->method('run');

        $mock_cmd_runner->expects($this->never())
                        ->method('getOutput');

        $mock_cmd_runner->expects($this->never())
                        ->method('warn');

        $script_runner = $this->getMockBuilder('TryLib\Precheck\GitCopyBehind')
                              ->setMethods(['shouldRunCheck'])
                              ->setConstructorArgs([['master']])
                              ->getMock();

        $script_runner->expects($this->once())
                      ->method('shouldRunCheck')
                      ->with($mock_cmd_runner)
                      ->will($this->returnValue(false));

        $script_runner->check($mock_cmd_runner, 'path', 'origin/master');
    }
}
