<?php

namespace tests\phpunit\JenkinsRunner;

use TryLib\JenkinsRunner\FreeStyleProject as FreeStyleProject;

class FreeStyleProjectTest extends \PHPUnit\Framework\TestCase {
    const JENKINS_URL = 'http://url.to.jenkins.com:8080/';
    const JENKINS_CLI = '/path/to/cli.jar';
    const JENKINS_JOB = 'test-try';

    private $jenkins_runner;
    private $mock_cmd_runner;

    function setUp(): void {
        parent::setUp();

        $this->mock_cmd_runner = $this->getMockBuilder('TryLib\CommandRunner')
                                      ->getMock();

        $this->jenkins_runner = new FreeStyleProject(
            self::JENKINS_URL,
            self::JENKINS_CLI,
            self::JENKINS_JOB,
            $this->mock_cmd_runner
        );
    }

    function testGetBuildCommand() {
        $this->assertEquals('build', $this->jenkins_runner->getBuildCommand());
    }

    function provideDataForBuildExtraArgs() {
        return array(
            array(false, false, array()),
            array(false, true, array('-s', '-v')),
            array(true, false, array('-s')),
            array(true, true, array('-s', '-v')),
        );
    }

    /**
      * @dataProvider provideDataForBuildExtraArgs
      */
    function testGetBuildExtraArguments($show_results, $show_progress, $expected_args) {
        $actual_args = $this->jenkins_runner->getBuildExtraArguments($show_results, $show_progress);
        $this->assertEquals($expected_args, $actual_args);
    }

    function providePollForCompletionData() {
        return array(
            array('Completed ' . self::JENKINS_JOB . ' #1234 : SUCCESS',
                  'SUCCESS',
                  'http://' . self::JENKINS_URL . '/job/' . self::JENKINS_JOB .'/1234'),

            array('Completed ' . self::JENKINS_JOB . ' #1 : failure',
                  'failure',
                  'http://' . self::JENKINS_URL . '/job/' . self::JENKINS_JOB .'/1'),

            array('Random string', '', '')
        );
    }

    /** @dataProvider providePollForCompletionData */
    function testPollForCompletion($output, $status, $url){
        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue($output));

        $this->jenkins_runner->pollForCompletion(true);

        $this->assertEquals($status, $this->jenkins_runner->try_status);
        $this->assertEquals($url, $this->jenkins_runner->try_base_url);
    }
}
