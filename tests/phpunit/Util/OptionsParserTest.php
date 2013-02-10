<?php

require_once "TryLib/Autoload.php";

class OptionsParserTest extends PHPUnit_Framework_TestCase {
	function provideArgvData() {
		return array(
			array(
				array('./try'),
				array(),
				array()
			),
			array(
				array('./try', '-n', '--verbose', '-p', 'patch.diff', '--branch=master', 'arg'),
				array('n'=>false, 'verbose'=>false, 'p'=>'patch.diff', 'branch'=>'master'),
				array('arg')
			),
			array(
				array('./try', '-c', 'foo', '-c', 'bar', '--callback=baz', 'arg'),
				array('c'=>array('foo', 'bar'), 'callback'=>'baz'),
				array('arg')
			),
			array(
				array('./try', 'foo', 'bar', 'baz'),
				array(),
				array('foo', 'bar', 'baz')
			),
		);
	}
	
	/** @dataProvider provideArgvData */
	function testPruneOptions($cmd_argv, $parsed_options, $expected) {
        global $argv;
		$argv=$cmd_argv;
		$actual = TryLib_Util_OptionsParser::pruneOptions($parsed_options);
		$this->assertEquals($expected, $actual);
	}
	
}