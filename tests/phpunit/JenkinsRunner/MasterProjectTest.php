<?php

namespace tests\phpunit\JenkinsRunner;

use PHPUnit_Framework_TestCase as TestCase;
use TryLib_JenkinsRunner_MasterProject as MasterProject;

require_once "TryLib/Autoload.php";

class MasterProjectTest extends TestCase {
    const JENKINS_URL = 'http://url.to.jenkins.com:8080/';
    const JENKINS_CLI = '/path/to/cli.jar';
    const JENKINS_JOB = 'test-try';

    private $jenkins_runner;
    private $mock_cmd_runner;

    function setUp() {
        parent::setUp();

        $this->mock_cmd_runner = $this->getMock('TryLib_CommandRunner');

        $this->jenkins_runner = new MasterProject(
            self::JENKINS_URL,
            self::JENKINS_CLI,
            self::JENKINS_JOB,
            $this->mock_cmd_runner
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


    function provideShowProgressJobResultsData() {
        return array(
            array(
                '[SUCCESS] test-try-validate-css (http://link/to/job/testReport)',
                'SUCCESS',
                '           test-try-validate-css \e[color SUCCESS\[0m ',
            ),
            array(
                '[UNSTABLE] test-try-validate-css (http://link/to/job/testReport)',
                'UNSTABLE',
                '           test-try-validate-css \e[color UNSTABLE\[0m (http://link/to/job/testReport)',
            ),
            array(
                '[FAILURE] test-try-validate-css (http://link/to/job/testReport)',
                'FAILURE',
                '           test-try-validate-css \e[color FAILURE\[0m (http://link/to/job/testReport)',
            )


        );
    }

    /** @dataProvider provideShowProgressJobResultsData */
    function testPrintJobResultSuccessAndShowProgress($log_line, $status, $expected_output) {
        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('colorStatus'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->expects($this->once())
                       ->method('colorStatus')
                       ->with($status)
                       ->will($this->returnValue('\e[color '. $status . '\[0m'));

        $this->mock_cmd_runner->expects($this->at(0))
                              ->method('info')
                              ->with($this->equalTo(PHP_EOL));

        $this->mock_cmd_runner->expects($this->at(1))
                              ->method('info')
                              ->with($this->equalTo($expected_output));

        $this->assertTrue($jenkins_runner->printJobResults($log_line));
    }

    function testPrintJobResultNoMatch() {
        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('colorStatus'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->expects($this->never())
                       ->method('colorStatus')
                       ->will($this->returnValue(null));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('info');

        $this->assertFalse($jenkins_runner->printJobResults("random line"));
    }

    function testProcessLogOutputNotFinishedShowProgress() {
        $prev_text = '
            ......... try-replication-tests (pending)
            ......... try-hphp (pending)
            ......... try-integration-tests (pending)
            ......... try-js-phantom-tests (pending)
            ......... try-validate-css (pending)
            ......... try-php-code-sniffer (pending)';

        $new_text = $prev_text . PHP_EOL . '......... try-file-tests (pending)';

        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('getJobOutput', 'printJobResults'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->expects($this->at(0))
                       ->method('getJobOutput')
                       ->will($this->returnValue($new_text));

        $jenkins_runner->expects($this->at(1))
                       ->method('printJobResults')
                       ->will($this->returnValue(true));

        $this->mock_cmd_runner->expects($this->never())
                              ->method('info');

        $actual = $jenkins_runner->processLogOuput($prev_text, true);
        $this->assertEquals($new_text, $actual);
    }

    function testProcessLogOutputNotFinishedDoNotShowProgress() {
        $prev_text = '
            ......... try-replication-tests (pending)
            ......... try-hphp (pending)
            ......... try-integration-tests (pending)
            ......... try-js-phantom-tests (pending)
            ......... try-validate-css (pending)
            ......... try-php-code-sniffer (pending)';

        $new_text = $prev_text . PHP_EOL . '......... try-file-tests (pending)';

        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('getJobOutput', 'printJobResults'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->expects($this->once())
                       ->method('getJobOutput')
                       ->will($this->returnValue($new_text));

        $jenkins_runner->expects($this->never())
                       ->method('printJobResults');

        $this->mock_cmd_runner->expects($this->once())
                              ->method('info')
                              ->with('......... waiting for job to finish');

        $actual = $jenkins_runner->processLogOuput($prev_text, false);
        $this->assertEquals($new_text, $actual);
    }

    function testProcessLogOutputFinishedShowProgress() {
        $prev_text = '
            ......... try-replication-tests (pending)
            ......... try-hphp (pending)
            ......... try-integration-tests (pending)
            ......... try-js-phantom-tests (pending)
            ......... try-validate-css (pending)
            ......... try-php-code-sniffer (pending)';

        $new_text = $prev_text . PHP_EOL . 'Finished: FAILURE';

        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('getJobOutput', 'printJobResults', 'colorStatus'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->expects($this->once())
                       ->method('getJobOutput')
                       ->will($this->returnValue($new_text));

        $jenkins_runner->expects($this->once())
                       ->method('colorStatus')
                       ->with('FAILURE')
                       ->will($this->returnValue('\e[red FAILURE \]0m'));

        $jenkins_runner->expects($this->once())
                       ->method('printJobResults')
                       ->will($this->returnValue(false));

        $jenkins_runner->try_base_url = 'http://link/to/job/';

        $this->mock_cmd_runner->expects($this->once())
                              ->method('info')
                              ->with(PHP_EOL . 'Try Status : \e[red FAILURE \]0m (http://link/to/job/)' . PHP_EOL);

        $this->assertNull( $jenkins_runner->processLogOuput($prev_text, true));
        $this->assertEquals('FAILURE', $jenkins_runner->try_status);
    }

    function testProcessLogOutputFinishedDoNotShowProgress() {
        $prev_text = '
            ......... try-replication-tests (pending)
            ......... try-hphp (pending)
            ......... try-integration-tests (pending)
            ......... try-js-phantom-tests (pending)
            ......... try-validate-css (pending)
            ......... try-php-code-sniffer (pending)';

        $new_text = $prev_text . PHP_EOL . 'Finished: SUCCESS';

        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('getJobOutput', 'printJobResults', 'colorStatus'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->expects($this->once())
                       ->method('getJobOutput')
                       ->will($this->returnValue($new_text));

        $jenkins_runner->expects($this->never())
                       ->method('printJobResults');

        $jenkins_runner->expects($this->once())
                       ->method('colorStatus')
                       ->with('SUCCESS')
                       ->will($this->returnValue('\e[green SUCCESS \]0m'));

        $jenkins_runner->try_base_url = 'http://link/to/job/';

        $this->mock_cmd_runner->expects($this->once())
                              ->method('info')
                              ->with(PHP_EOL . 'Try Status : \e[green SUCCESS \]0m (http://link/to/job/)' . PHP_EOL);

        $this->assertNull( $jenkins_runner->processLogOuput($prev_text, false));
        $this->assertEquals('SUCCESS', $jenkins_runner->try_status);
    }

    function testProcessLogOutputDifferentPrefixFinishedDoNotShowProgress() {
        $prev_text = '
            ......... bar-replication-tests (pending)
            ......... bar-hphp (pending)
            ......... bar-integration-tests (pending)
            ......... bar-js-phantom-tests (pending)
            ......... bar-validate-css (pending)
            ......... bar-php-code-sniffer (pending)';

        $new_text = $prev_text . PHP_EOL . 'Finished: SUCCESS';

        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('getJobOutput', 'printJobResults', 'colorStatus'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB . '-foo', $this->mock_cmd_runner, 'bar')
        );

        $jenkins_runner->expects($this->once())
                       ->method('getJobOutput')
                       ->will($this->returnValue($new_text));

        $jenkins_runner->expects($this->never())
                       ->method('printJobResults');

        $jenkins_runner->expects($this->once())
                       ->method('colorStatus')
                       ->with('SUCCESS')
                       ->will($this->returnValue('\e[green SUCCESS \]0m'));

        $jenkins_runner->try_base_url = 'http://link/to/job/';

        $this->mock_cmd_runner->expects($this->once())
                              ->method('info')
                              ->with(PHP_EOL . 'Try Status : \e[green SUCCESS \]0m (http://link/to/job/)' . PHP_EOL);

        $this->assertNull( $jenkins_runner->processLogOuput($prev_text, false));
        $this->assertEquals('SUCCESS', $jenkins_runner->try_status);
    }

    function testPollForCompletionJobUrlNotFound() {
        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue('no url in here'));

        $this->mock_cmd_runner->expects($this->once())
                              ->method('terminate')
                              ->with('Could not find ' .self::JENKINS_JOB . ' URL' . PHP_EOL);

        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('processLogOuput'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->pollForCompletion(true);

        $this->assertEquals('', $jenkins_runner->try_base_url);
    }

    function testPollForCompletionJobFinished() {
        $expected_job_url = 'http://some.other.domain:8080/job/' . self::JENKINS_JOB . '/1234';
        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue($expected_job_url));


        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('processLogOuput'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
        );

        $jenkins_runner->expects($this->once())
                       ->method('processLogOuput')
                       ->with('', true)
                       ->will($this->returnValue(null));

        $jenkins_runner->pollForCompletion(true);

        $this->assertEquals($expected_job_url, $jenkins_runner->try_base_url);
    }

    function testPollForCompletionJobPollsAndFinishes() {
        $expected_job_url = 'http://some.other.domain:8080/job/' . self::JENKINS_JOB . '/1234';
        $this->mock_cmd_runner->expects($this->once())
                              ->method('getOutput')
                              ->will($this->returnValue($expected_job_url));


        $jenkins_runner = $this->getMock(
                'TryLib_JenkinsRunner_MasterProject',
                array('processLogOuput'),
                array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner, null, 0)
        );

        $jenkins_runner->expects($this->at(0))
                       ->method('processLogOuput')
                       ->with('', false)
                       ->will($this->returnValue(false));

        $jenkins_runner->expects($this->at(1))
                       ->method('processLogOuput')
                       ->with('', false)
                       ->will($this->returnValue(null));

        $jenkins_runner->pollForCompletion(false);

        $this->assertEquals($expected_job_url, $jenkins_runner->try_base_url);
    }
}
