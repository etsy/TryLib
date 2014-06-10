<?php

require_once "TryLib/Autoload.php";

class GitWarnOnBlacklistedTest extends PHPUnit_Framework_TestCase {
    function setUp() {
        parent::setUp();
        $this->mock_cmd_runner = $this->getMock('TryLib_CommandRunner');
    }

    function testNoChanges() {
        $script_runner = new TryLib_Precheck_GitWarnOnBlacklisted(
            array(),
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

    function testNoBlackListedFiles() {
        $script_runner = new TryLib_Precheck_GitWarnOnBlacklisted(
            array('my/foo.php'),
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

    function testWithBlackListedFiles() {
        $script_runner = new TryLib_Precheck_GitWarnOnBlacklisted(
            array('my/foo.php', 'my/bar.php'),
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

    function testWithWhiteListedFiles() {
        $script_runner = new TryLib_Precheck_GitWarnOnBlacklisted(
            array(),
            array('my/foo.php', 'my/bar.php'),
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

    function testWithWhiteListedAndBlackListedFiles() {
        $script_runner = new TryLib_Precheck_GitWarnOnBlacklisted(
            array('my/bar.php'),
            array('my/foo.php', 'my/bar.php'),
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

    function testStagedOnly() {
        $script_runner = new TryLib_Precheck_GitWarnOnBlacklisted(
            array('my/foo.php', 'my/bar.php'),
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
