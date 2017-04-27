<?php

namespace tests\phpunit\Util;

use PHPUnit_Framework_TestCase as TestCase;
use TryLib_Util_OptionsUtil as OptionsUtil;

require_once "TryLib/Autoload.php";

class OptionsUtilTest extends TestCase {

    function provideExtraParameters() {
        return array(
            array(
                null,
                array()
            ),
            array(
                'k=v',
                array(
                    array('k','v')
                )
            ),
            array(
                'k',
                array(
                    array('k','')
                )
            ),
            array(
                'k=v=w',
                array(
                    array('k','v=w')
                )
            ),
            array(
                array('k=v','x=y=z','w'),
                array(
                    array('k','v'),
                    array('x','y=z'),
                    array('w',''),
                )
            ),
        );
    }

    /**
      * @dataProvider provideExtraParameters
      */
    function testParseExtraParameters($extra_param_option, $expected_params) {
        $actual_params = OptionsUtil::parseExtraParameters($extra_param_option);
        $this->assertEquals($expected_params, $actual_params);
    }
}
