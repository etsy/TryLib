<?php

require_once "TryLib/Autoload.php";

class GitTest extends PHPUnit_Framework_TestCase {
	const REPO_PATH = '/path/to/repo';

	private $mock_cmd_runner;
	
    function setUp() {
        parent::setUp();

		$this->mock_cmd_runner = $this->getMock('TryLib_CommandRunner');
	}	
	
	function testGenerateDiffStagedSuccessfull() {
		$repo_manager = $this->getMock(
				'TryLib_RepoManager_Git',
				array('getUpstream'),
				array(self::REPO_PATH, $this->mock_cmd_runner)
		);
		
		$repo_manager->expects($this->once())
					 ->method('getUpstream')
					 ->will($this->returnValue('origin/master'));
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('chdir')
							  ->with(self::REPO_PATH);

		$expected_patch = self::REPO_PATH . '/patch.diff';
		$expected_cmd = 'git diff --src-prefix=\'\' --dst-prefix=\'\' '
					  . '--no-color origin/master '
					  . '--staged > '
					  . $expected_patch;		  

		$this->mock_cmd_runner->expects($this->once())
							  ->method('run')
							  ->with($expected_cmd, false, true)
							  ->will($this->returnValue(0));

		$this->mock_cmd_runner->expects($this->never())
							  ->method('terminate');

		$actual_patch = $repo_manager->generateDiff(true);
		$this->assertEquals($actual_patch, $expected_patch);
	}
	
	function testGenerateDiffFailure() {
		$repo_manager = $this->getMock(
				'TryLib_RepoManager_Git',
				array('getUpstream'),
				array(self::REPO_PATH, $this->mock_cmd_runner)
		);
		
		$repo_manager->expects($this->once())
					 ->method('getUpstream')
					 ->will($this->returnValue('origin/master'));
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('chdir')
							  ->with(self::REPO_PATH);

		$expected_patch = self::REPO_PATH . '/patch.diff';
		$expected_cmd = 'git diff --src-prefix=\'\' --dst-prefix=\'\' '
					  . '--no-color origin/master > '
					  . $expected_patch;		  

		$this->mock_cmd_runner->expects($this->once())
							  ->method('run')
							  ->with($expected_cmd, false, true)
							  ->will($this->returnValue(1));

		$expected_error = 'An error was encountered generating the diff '
						. '- run \'git fetch\' and try again';
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('terminate')
							  ->with($expected_error);

		$repo_manager->generateDiff(false);
	}
	
	
	function testParseLocalBranchSuccess() {
		$repo_manager = new TryLib_RepoManager_Git(
            self::REPO_PATH, $this->mock_cmd_runner
        );
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('chdir')
							  ->with(self::REPO_PATH);
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('run')
							  ->with('git symbolic-ref HEAD', true, true)
							  ->will($this->returnValue(0));

		$this->mock_cmd_runner->expects($this->once())
							  ->method('getOutput')
							  ->will($this->returnValue('refs/heads/master '));
		
		$this->assertEquals('master', $repo_manager->parseLocalBranch());
	}

	function testParseLocalBranchFailure() {
		$repo_manager = new TryLib_RepoManager_Git(
            self::REPO_PATH, $this->mock_cmd_runner
        );
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('chdir')
							  ->with(self::REPO_PATH);
		
		$this->mock_cmd_runner->expects($this->once())
							  ->method('run')
							  ->with('git symbolic-ref HEAD', true, true)
							  ->will($this->returnValue(1));

		$this->mock_cmd_runner->expects($this->never())
							  ->method('getOutput');
		
		$this->assertEquals('', $repo_manager->parseLocalBranch());
	}
	
	function testGetRemoteSuccess() {
		$repo_manager = $this->getMock(
				'TryLib_RepoManager_Git',
				array('getLocalBranch', 'getConfig'),
				array(self::REPO_PATH, $this->mock_cmd_runner)
		);
		
		$repo_manager->expects($this->once())
					 ->method('getLocalBranch')
					 ->will($this->returnValue('master'));

		$repo_manager->expects($this->once())
					 ->method('getConfig')
					 ->with('branch.master.remote')
					 ->will($this->returnValue('origin'));

		$this->assertEquals('origin', $repo_manager->getRemote('default'));
	}
	
	function testGetRemoteFailWithDefault() {
		$repo_manager = $this->getMock(
				'TryLib_RepoManager_Git',
				array('getLocalBranch', 'getConfig'),
				array(self::REPO_PATH, $this->mock_cmd_runner)
		);
		
		$repo_manager->expects($this->once())
					 ->method('getLocalBranch')
					 ->will($this->returnValue('master'));

		$repo_manager->expects($this->once())
					 ->method('getConfig')
					 ->with('branch.master.remote')
					 ->will($this->returnValue(null));

		$this->assertEquals('default', $repo_manager->getRemote('default'));
	}
	
	function testGetRemoteFailNoDefault() {
		$repo_manager = $this->getMock(
				'TryLib_RepoManager_Git',
				array('getLocalBranch', 'getConfig'),
				array(self::REPO_PATH, $this->mock_cmd_runner)
		);
		
		$repo_manager->expects($this->once())
					 ->method('getLocalBranch')
					 ->will($this->returnValue('master'));

		$repo_manager->expects($this->once())
					 ->method('getConfig')
					 ->with('branch.master.remote')
					 ->will($this->returnValue(null));

		$this->assertNull($repo_manager->getRemote());
	}
}