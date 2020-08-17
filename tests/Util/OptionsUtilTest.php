<?php

namespace tests\phpunit\Util;

use TryLib\Util\OptionsUtil as OptionsUtil;

class OptionsUtilTest extends \PHPUnit\Framework\TestCase {

    public function provideExtraParameters() {
        return [
            [
                null,
                []
            ],
            [
                'k=v',
                [
                    ['k','v']
                ]
            ],
            [
                'k',
                [
                    ['k','']
                ]
            ],
            [
                'k=v=w',
                [
                    ['k','v=w']
                ]
            ],
            [
                ['k=v','x=y=z','w'],
                [
                    ['k','v'],
                    ['x','y=z'],
                    ['w',''],
                ]
            ],
        ];
    }

    /**
      * @dataProvider provideExtraParameters
      */
    public function testParseExtraParameters($extra_param_option, $expected_params) {
        $actual_params = OptionsUtil::parseExtraParameters($extra_param_option);
        $this->assertEquals($expected_params, $actual_params);
    }
}
