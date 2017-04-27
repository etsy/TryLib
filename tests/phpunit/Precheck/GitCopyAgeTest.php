<?php

namespace tests\phpunit\Precheck;

use PHPUnit_Framework_TestCase as TestCase;
use TryLib_Precheck_GitCopyAge as GitCopyAge;

require_once "TryLib/Autoload.php";

class GitCopyAgeTest extends TestCase {

    private $mock_cmd_runner;

    function setUp() {
        parent::setUp();
        $this->mock_cmd_runner = $this->getMock('TryLib_CommandRunner');
    }

    function testGetLastFetchDateWithoutRemoteBranchSuccess() {
        $last_fetch = 'Sun Feb 10 10:00:00 2013';

        $script_runner = new GitCopyAge();
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git log -1 --format=\'%cd\' --date=local')
                              ->will($this->returnValue(0));
        

        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue($last_fetch));
        
        $actual = $script_runner->getLastFetchDate($this->mock_cmd_runner); 
        
        $this->assertEquals($last_fetch, $actual);          
    }
    
    function testGetLastFetchDateWithRemoteBranchFailure() {
        $last_fetch = 'Sun Feb 10 10:00:00 2013';

        $script_runner = new GitCopyAge(24, 48, 'branch');
        
        $this->mock_cmd_runner->expects($this->once())
                              ->method('run')
                              ->with('git log -1 --format=\'%cd\' --date=local origin/branch')
                              ->will($this->returnValue(1));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('getOutput');
                
        $actual = $script_runner->getLastFetchDate($this->mock_cmd_runner);
        $this->assertNull($actual);         
    }
    
    function testWorkingCopyPastMaxBlockingAge() {
        $last_fetch = 'Sun Feb 10 10:00:00 2013';
        
        $script_runner = $this->getMock(
                'TryLib_Precheck_GitCopyAge',
                array('getLastFetchDate', 'getTimeDelta', 'formatTimeDiff'),
                array(12, 72, 'branch')
        );
        
        $script_runner->expects($this->once())
                      ->method('getLastFetchDate')
                      ->with($this->mock_cmd_runner)
                      ->will($this->returnValue($last_fetch));

        $script_runner->expects($this->once())
                      ->method('getTimeDelta')
                      ->with($last_fetch)
                      ->will($this->returnValue(100 * 60 * 60));
        
        $script_runner->expects($this->once())
                      ->method('formatTimeDiff')
                      ->with(100 * 60 * 60)
                      ->will($this->returnValue('100 hours'));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('terminate')
                              ->with($this->stringContains('you working copy is 100 hours old.'));
        
        $script_runner->check($this->mock_cmd_runner, 'path', 'origin/master');
    }

    function testWorkingCopyPastMaxWarningAge() {
        $last_fetch = 'Sun Feb 10 10:00:00 2013';
        
        $script_runner = $this->getMock(
                'TryLib_Precheck_GitCopyAge',
                array('getLastFetchDate', 'getTimeDelta', 'formatTimeDiff'),
                array(12, 72, 'branch')
        );
        
        $script_runner->expects($this->once())
                      ->method('getLastFetchDate')
                      ->with($this->mock_cmd_runner)
                      ->will($this->returnValue($last_fetch));

        $script_runner->expects($this->once())
                      ->method('getTimeDelta')
                      ->with($last_fetch)
                      ->will($this->returnValue(24 * 60 * 60));
        
        $script_runner->expects($this->once())
                      ->method('formatTimeDiff')
                      ->with(24 * 60 * 60)
                      ->will($this->returnValue('24 hours'));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('terminate');

        $this->mock_cmd_runner->expects($this->once())
                              ->method('warn');
        
        $script_runner->check($this->mock_cmd_runner, 'path', 'origin/master');
    }


    function testWorkingCopySuccess() {
        $last_fetch = 'Sun Feb 10 10:00:00 2013';
        
        $script_runner = $this->getMock(
                'TryLib_Precheck_GitCopyAge',
                array('getLastFetchDate', 'getTimeDelta', 'formatTimeDiff'),
                array(12, 72, 'branch')
        );
        
        $script_runner->expects($this->once())
                      ->method('getLastFetchDate')
                      ->with($this->mock_cmd_runner)
                      ->will($this->returnValue($last_fetch));

        $script_runner->expects($this->once())
                      ->method('getTimeDelta')
                      ->with($last_fetch)
                      ->will($this->returnValue(6 * 60 * 60));
        
        $script_runner->expects($this->never())
                      ->method('formatTimeDiff');

        $this->mock_cmd_runner->expects($this->never())
                              ->method('terminate');

        $this->mock_cmd_runner->expects($this->never())
                              ->method('warn');
        
        $script_runner->check($this->mock_cmd_runner, 'path', 'origin/master');
    }
}
