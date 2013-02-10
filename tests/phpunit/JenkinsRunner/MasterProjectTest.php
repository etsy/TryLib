<?php

require_once "TryLib/Autoload.php";

function greenFormat($text) {
	return "\e[green $text\[0m";
}

function redFormat($text) {
	return "\e[red $text\[0m";
}

function yellowFormat($text) {
	return "\e[yellow $text\[0m";
}


class MasterProjectTest extends PHPUnit_Framework_TestCase {
	const JENKINS_URL = 'url.to.jenkins.com:8080';
	const JENKINS_CLI = '/path/to/cli.jar';
	const JENKINS_JOB = 'test-try';

    private $jenkins_runner;

    function setUp() {
        parent::setUp();

		$this->mock_cmd_runner = $this->getMock('TryLib_CommandRunner');

        $this->jenkins_runner = new TryLib_JenkinsRunner_MasterProject(
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


	function providePrettyJobResultsData() {
		return array(
			array(
				'green',
				'           test-try-validate-css \e[green SUCCESS\[0m ',
				'[SUCCESS] test-try-validate-css (http://link/to/job/testReport)'
			),
			array(
				'yellow',
				'           test-try-validate-css \e[yellow UNSTABLE\[0m (http://link/to/job/testReport)',
				'[UNSTABLE] test-try-validate-css (http://link/to/job/testReport)'
			),
			array(
				'red',
				'           test-try-validate-css \e[red FAILURE\[0m (http://link/to/job/testReport)',
				'[FAILURE] test-try-validate-css (http://link/to/job/testReport)'
			)


		);
	}

	/** @dataProvider providePrettyJobResultsData */
	function testPrintJobResultSuccessAndPretty($color, $expected_output, $log_line) {
		$mock_colors = $this->getMock('TryLib_Util_AnsiColor', array('green', 'yellow', 'red'));

		$mock_colors->expects($this->once())
					->method($color)
					->will($this->returnCallback($color . 'Format'));

		$jenkins_runner = $this->getMock(
				'TryLib_JenkinsRunner_MasterProject',
				array('getColors'),
				array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
		);

		$jenkins_runner->expects($this->once())
					   ->method('getColors')
					   ->will($this->returnValue($mock_colors));

		$this->mock_cmd_runner->expects($this->at(0))
							  ->method('info')
							  ->with($this->equalTo(PHP_EOL));

		$this->mock_cmd_runner->expects($this->at(1))
							  ->method('info')
							  ->with($this->equalTo($expected_output));

		$this->assertTrue($jenkins_runner->printJobResults($log_line, true));
	}
	
	function provideJobResultsData() {
		return array(
			array(
				'           test-try-validate-css SUCCESS    ',
				'[SUCCESS] test-try-validate-css (http://link/to/job/testReport)'
			),
			array(
				'           test-try-validate-css UNSTABLE   (http://link/to/job/testReport)',
				'[UNSTABLE] test-try-validate-css (http://link/to/job/testReport)'
			),
			array(
			'           test-try-validate-css FAILURE    (http://link/to/job/testReport)',
				'[FAILURE] test-try-validate-css (http://link/to/job/testReport)'
			)


		);
	}

	/** @dataProvider provideJobResultsData */
	function testPrintJobResultSuccessNotPretty($expected_output, $log_line) {
		$jenkins_runner = $this->getMock(
				'TryLib_JenkinsRunner_MasterProject',
				array('getColors'),
				array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
		);

		$jenkins_runner->expects($this->once())
					   ->method('getColors')
					   ->will($this->returnValue(null));

		$this->mock_cmd_runner->expects($this->at(0))
							  ->method('info')
							  ->with($this->equalTo(PHP_EOL));

		$this->mock_cmd_runner->expects($this->at(1))
							  ->method('info')
							  ->with($this->equalTo($expected_output));

		$this->assertTrue($jenkins_runner->printJobResults($log_line, false));
	}
	
	function testPrintJobResultFailure() {
		$jenkins_runner = $this->getMock(
				'TryLib_JenkinsRunner_MasterProject',
				array('getColors'),
				array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
		);

		$jenkins_runner->expects($this->once())
					   ->method('getColors')
					   ->will($this->returnValue(null));

		$this->mock_cmd_runner->expects($this->never())
							  ->method('info');

		$this->assertFalse($jenkins_runner->printJobResults("random line", false));
	}
	
	function testProcessLogOutputNotFinishedPretty() {
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
		
		$this->mock_cmd_runner->expects($this->at(0))
							  ->method('info')
							  ->with(PHP_EOL . '......... waiting for job to finish ..');

		
		$this->mock_cmd_runner->expects($this->at(1))
							  ->method('info')
							  ->with('.', false);

		$actual = $jenkins_runner->processLogOuput($prev_text, true);
		$this->assertEquals($new_text, $actual);
	}

	function testProcessLogOutputSubjobNotFinishedPretty() {
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

		$jenkins_runner->expects($this->once())
					   ->method('printJobResults')
					   ->will($this->returnValue(false));		

		$this->mock_cmd_runner->expects($this->once())
							  ->method('info')
							  ->with('.', false);

		$actual = $jenkins_runner->processLogOuput($prev_text, true);
		$this->assertEquals($new_text, $actual);
	}
	
	function testProcessLogOutputNotFinishedNotPretty() {
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
	
	function testProcessLogOutputFinishedPretty() {
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
				array('getJobOutput', 'printJobResults'),
				array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
		);
		
		$jenkins_runner->expects($this->once())
					   ->method('getJobOutput')
					   ->will($this->returnValue($new_text));

		$jenkins_runner->expects($this->once())
					   ->method('printJobResults')
					   ->will($this->returnValue(false));

		$jenkins_runner->try_base_url = 'http://link/to/job/';

		$this->mock_cmd_runner->expects($this->at(0))
							  ->method('info')
							  ->with(PHP_EOL . 'http://link/to/job/');

		$this->mock_cmd_runner->expects($this->at(1))
							  ->method('info')
							  ->with(PHP_EOL . 'Finished: FAILURE');

		$this->assertNull( $jenkins_runner->processLogOuput($prev_text, true));
		$this->assertEquals('FAILURE', $jenkins_runner->try_status);
	}

	function testProcessLogOutputFinishedNotPretty() {
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
				array('getJobOutput', 'printJobResults'),
				array(self::JENKINS_URL, self::JENKINS_CLI, self::JENKINS_JOB, $this->mock_cmd_runner)
		);
		
		$jenkins_runner->expects($this->once())
					   ->method('getJobOutput')
					   ->will($this->returnValue($new_text));

		$jenkins_runner->expects($this->once())
					   ->method('printJobResults')
					   ->with($new_text, false);

		$jenkins_runner->try_base_url = 'http://link/to/job/';

		$this->mock_cmd_runner->expects($this->at(0))
							  ->method('info')
							  ->with(PHP_EOL . 'http://link/to/job/');

		$this->mock_cmd_runner->expects($this->at(1))
							  ->method('info')
							  ->with(PHP_EOL . 'Finished: SUCCESS');

		$this->assertNull( $jenkins_runner->processLogOuput($prev_text, false));
		$this->assertEquals('SUCCESS', $jenkins_runner->try_status);
	}
}
