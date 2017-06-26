<?php

namespace tests\phpunit\Util;

use TryLib\Util\PHPOptions\Options as Options;

class OptionsTest extends \PHPUnit\Framework\TestCase {

    function testParseExtraParameters() {
        $optspec = "
try
--
safelist= Generate the patch for only the safelisted files
        ";

        $actual_params = (new Options($optspec))->parse(array('--safelist', 'v1', '--safelist', 'v2', '--safelist', 'v3'));
        list($opt,$flags,$extra) = $actual_params;
        $this->assertEquals(['v1','v2','v3'], $opt->safelist);
    }
}
