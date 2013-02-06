<?php

require_once "TryLib/Autoload.php";

class MasterProjectTest extends PHPUnit_Framework_TestCase {

    private $jenkins_runner;

    function setUp() {
        parent::setUp();
        $this->jenkins_runner = new TryLib_JenkinsRunner_MasterProject(
            'url.to.jenkins.com:8080',
            '/path/to/cli.jar',
            'test-try',
             $this->getMock('TryLib_CommandRunner')
        );        
    }

    function getSubjobs() {
        return array(
            array(
                array(),
                array(),
                array(),
            ),
            array(
                array('a','b','c'),
                array(),
                array('test-try-a','test-try-b','test-try-c')
            ),
            array(
                array(),
                array('a', 'b', 'c'),
                array('-test-try-a','-test-try-b','-test-try-c')
            ),
            array(
                array('a','b'),
                array('c'),
                array('test-try-a','test-try-b','-test-try-c')
            ),
            array(
                array('a','b','c'),
                array('a','c'),
                array('test-try-b','-test-try-a','-test-try-c')
            ),
            array(
                array('a','a','c'),
                array('c','c'),
                array('test-try-a', '-test-try-c')
            ),
        );
    }

    /** @dataProvider getSubJobs */
    function testGetJobList($included, $excluded, $expected_joblist) {
        $this->jenkins_runner->setSubJobs($included);
        $this->jenkins_runner->setExcludedSubJobs($excluded);
        $joblist = $this->jenkins_runner->getJobsList();
        $this->assertEquals($expected_joblist, $joblist);
    }
}
