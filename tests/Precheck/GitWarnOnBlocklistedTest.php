<?php

namespace tests\phpunit\Precheck;

use TryLib\Precheck\GitWarnOnBlocklisted as GitWarnOnBlocklisted;

class GitWarnOnBlocklistedTest extends \PHPUnit\Framework\TestCase {
    protected function setUp() {
        parent::setUp();
        $this->mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                      ->getMock();
    }

    public function testNoChanges() {
        $script_runner = new GitWarnOnBlocklisted(
            [],
            null,
            false
        );

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git diff --name-only some/origin')
                              ->will($this->returnValue(0));


        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue(''));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('warn');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'some/origin');
    }

    public function testNoBlockListedFiles() {
        $script_runner = new GitWarnOnBlocklisted(
            ['my/foo.php'],
            false,
            false
        );

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git diff --name-only some/origin')
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue('foo.php'));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('warn');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'some/origin');

    }

    public function testWithBlockListedFiles() {
        $script_runner = new GitWarnOnBlocklisted(
            ['my/foo.php', 'my/bar.php'],
            null,
            false
        );

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git diff --name-only some/origin')
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue('foo.php' . PHP_EOL . 'my/bar.php'));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('warn');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'some/origin');

    }

    public function testWithSafeListedFiles() {
        $script_runner = new GitWarnOnBlocklisted(
            [],
            ['my/foo.php', 'my/bar.php'],
            false
        );

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git diff --name-only some/origin my/foo.php my/bar.php')
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue('my/foo.php' . PHP_EOL));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('warn');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'some/origin');

    }

    public function testWithSafeListedAndBlockListedFiles() {
        $script_runner = new GitWarnOnBlocklisted(
            ['my/bar.php'],
            ['my/foo.php', 'my/bar.php'],
            false
        );

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git diff --name-only some/origin my/foo.php my/bar.php')
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue('my/foo.php' . PHP_EOL . 'my/bar.php'));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('warn');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'some/origin');
    }

    public function testStagedOnly() {
        $script_runner = new GitWarnOnBlocklisted(
            ['my/foo.php', 'my/bar.php'],
            null,
            true
        );

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git diff --name-only some/origin --staged')
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue('foo.php'));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('warn');

        $script_runner->check($this->mock_cmd_runner, 'repoPath', 'some/origin');

    }
}
