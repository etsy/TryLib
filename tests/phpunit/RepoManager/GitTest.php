<?php

namespace tests\phpunit\RepoManager;

use PHPUnit_Framework_TestCase as TestCase;
use TryLib_RepoManager_Git as Git;

require_once "TryLib/Autoload.php";

class GitTest extends TestCase {
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
        $expected_cmd = 'git -c diff.noprefix=false diff --binary '
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
        $expected_cmd = 'git -c diff.noprefix=false diff --binary '
                      . '--no-color origin/master > '
                      . $expected_patch;          

        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with($expected_cmd, false, true)
                              ->will($this->returnValue(1));
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('terminate');

        $repo_manager->generateDiff(false);
    }
    
    
    function testParseLocalBranchSuccess() {
        $repo_manager = new Git(
            self::REPO_PATH, $this->mock_cmd_runner
        );
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('chdir')
                              ->with(self::REPO_PATH);
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git rev-parse --abbrev-ref HEAD', true, true)
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue('refs/heads/master '));
        
        $this->assertEquals('master', $repo_manager->parseLocalBranch());
    }

    function testParseLocalBranchFailure() {
        $repo_manager = new Git(
            self::REPO_PATH, $this->mock_cmd_runner
        );
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('chdir')
                              ->with(self::REPO_PATH);
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git rev-parse --abbrev-ref HEAD', true, true)
                              ->will($this->returnValue(0));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue("HEAD"));
        
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
    
    
    function testGetRemoteBranchFromTrackingConfig() {
        $repo_manager = $this->getMock(
                'TryLib_RepoManager_Git',
                array('getLocalBranch', 'getConfig'),
                array(self::REPO_PATH, $this->mock_cmd_runner)
        );
        
        $repo_manager->expects($this->once())
                     ->method('getLocalBranch')
                     ->will($this->returnValue('local_branch'));

        $repo_manager->expects($this->once())
                     ->method('getConfig')
                     ->with('branch.local_branch.merge')
                     ->will($this->returnValue('remote_branch'));

        $this->assertEquals('remote_branch', $repo_manager->getRemoteBranch());
    }
    
    function testGetRemoteBranchNoTrackingRemoteWithSameName() {
        $repo_manager = $this->getMock(
                'TryLib_RepoManager_Git',
                array('getLocalBranch', 'getConfig'),
                array(self::REPO_PATH, $this->mock_cmd_runner)
        );
        
        $repo_manager->expects($this->at(0))
                     ->method('getLocalBranch')
                     ->will($this->returnValue('local_branch'));

        $repo_manager->expects($this->at(1))
                     ->method('getConfig')
                     ->with('branch.local_branch.merge')
                     ->will($this->returnValue(null));
        
        $repo_manager->expects($this->at(2))
                     ->method('getConfig')
                     ->with('remote.origin.url')
                     ->will($this->returnValue('git@github.com:Etsy/try.git'));

        $cmd = 'git ls-remote --exit-code git@github.com:Etsy/try.git refs/heads/local_branch';
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with($cmd, true, true)
                              ->will($this->returnValue(0));

        $this->assertEquals('local_branch', $repo_manager->getRemoteBranch());
    }
    
    function testGetRemoteBranchNoTrackingNoRemote() {
        $this->setExpectedException('RuntimeException', 'No remote branch was found');

        $repo_manager = $this->getMock(
                'TryLib_RepoManager_Git',
                array('getLocalBranch', 'getConfig'),
                array(self::REPO_PATH, $this->mock_cmd_runner)
        );
        
        $repo_manager->expects($this->at(0))
                     ->method('getLocalBranch')
                     ->will($this->returnValue('local_branch'));

        $repo_manager->expects($this->at(1))
                     ->method('getConfig')
                     ->with('branch.local_branch.merge')
                     ->will($this->returnValue(null));
        
        $repo_manager->expects($this->at(2))
                     ->method('getConfig')
                     ->with('remote.origin.url')
                     ->will($this->returnValue('git@github.com:Etsy/try.git'));

        $cmd = 'git ls-remote --exit-code git@github.com:Etsy/try.git refs/heads/local_branch';
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with($cmd, true, true)
                              ->will($this->returnValue(1));

        $repo_manager->getRemoteBranch();
    }
}
