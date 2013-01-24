<?php
/**
 * Helpful tool for generating colored text on the linux prompt
 *
 * Usage:
 *  To use, just call methods in any order to build up the style you want
 *  (see below for available options.) After you output the string, the
 *  terminal is automatically reset.
 *
 *  Examples:
 *
 *     # bold, red text
 *     echo $clr->bold()->red("Some text");
 *     #  The prompt is automatically reset after the text is output.
 *
 *     # To do background colors, just prepend "on" to a color:
 *     echo $clr->onred('Text')
 *
 *     # blinking red text on intense blue background:
 *     echo $clr->blink()->bold()->oniblue()->red('Text');
 *
 * Supported colors:
 *     black, red, green, yellow, blue, purple, cyan, white, iblack, ired,
 *      igreen, iyellow, iblue, ipurple, icyan, iwhite
 *
 * Supported text styles:
 *     bold, underline, blink, reverse
 *
 */
class Util_AnsiColor {
    private $color_opts = array(
        'black'   => '30',
        'red'     => '31',
        'green'   => '32',
        'yellow'  => '33',
        'blue'    => '34',
        'purple'  => '35',
        'cyan'    => '36',
        'white'   => '37',
        'iblack'  => '90',
        'ired'    => '91',
        'igreen'  => '92',
        'iyellow' => '93',
        'iblue'   => '94',
        'ipurple' => '95',
        'icyan'   => '96',
        'iwhite'  => '97',
    );

    private $text_opts = array(
        'bold'      => '1',
        'underline' => '4',
        'blink'     => '5',
        'reverse'   => '7',
    );

    private $seq = '';

    public function __call($name, $args) {
        if (count($args) > 1) {
            throw new InvalidArgumentException("$name expects 0 or 1 arguments");
        }

        $bg = false;
        if (strpos($name, 'on') === 0) {
            $bg = true;
            $name = substr($name, 2);
        }

        if (isset($this->color_opts[$name])) {
            if ($bg) {
                $color = intval($this->color_opts[$name]) + 10;
                $this->seq .= sprintf("\033[%sm", $color);
            } else {
                $this->seq .= sprintf("\033[%sm", $this->color_opts[$name]);
            }
        } else if (isset($this->text_opts[$name])) {
            $this->seq .= sprintf("\033[%sm", $this->text_opts[$name]);
        } else {
            throw new BadMethodCallException('Function name must be in $color_opts or $text_opts');
        }

        if (count($args) == 1) {
            $this->seq .= $args[0];
        }

        return $this;
    }

    public function __toString() {
        $seq = $this->seq."\033[0m";
        $this->seq = '';
        return $seq;
    }
}
