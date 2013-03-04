<?php
/**
# Copyright 2010-2012 Avery Pennarun and options.py contributors.
# All rights reserved.
#
# The code was ported to PHP by Gabriel Filion
#
# (This license applies to this file but not necessarily the other files in
# this package.)
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are
# met:
#
#    1. Redistributions of source code must retain the above copyright
#       notice, this list of conditions and the following disclaimer.
#
#    2. Redistributions in binary form must reproduce the above copyright
#       notice, this list of conditions and the following disclaimer in
#       the documentation and/or other materials provided with the
#       distribution.
#
# THIS SOFTWARE IS PROVIDED BY AVERY PENNARUN ``AS IS'' AND ANY
# EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
# PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> OR
# CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
# EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
# PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
# PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
# LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
# NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
# SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
#


 All code in this file is under the above license, except for the code relating
 to gnu_getopt, which was ported from Python's getopt.py module to PHP and is
 under the Python license.

 Both licenses can be found in their own separate files in the source archive
 at, either accompanying this file, or at the following URL:

 https://github.com/lelutin/php-options/

 but the Python license was not embedded here for brevity.
**/

/**Command-line options parser.
With the help of an options spec string, easily parse command-line options.

An options spec is made up of two parts, separated by a line with two dashes.
The first part is the synopsis of the command and the second one specifies
options, one per line.

Each non-empty line in the synopsis gives a set of options that can be used
together.

Option flags must be at the begining of the line and multiple flags are
separated by commas. Usually, options have a short, one character flag, and a
longer one, but the short one can be omitted.

Long option flags are used as the option's key for the OptDict produced when
parsing options.

When the flag definition is ended with an equal sign, the option takes
one string as an argument, and that string will be converted to an
integer when possible. Otherwise, the option does not take an argument
and corresponds to a boolean flag that is true when the option is
given on the command line.

The option's description is found at the right of its flags definition, after
one or more spaces. The description ends at the end of the line. If the
description contains text enclosed in square brackets, the enclosed text will
be used as the option's default value.

Options can be put in different groups. Options in the same group must be on
consecutive lines. Groups are formed by inserting a line that begins with a
space. The text on that line will be output after an empty line.
**/
//import sys, os, textwrap, getopt, re, struct


function _invert($v, $invert) {
    if ($invert)
        return ! $v;
    return $v;
}


function _startswith($haystack, $needle, $start=0) {
    if ($start)
        $haystack = substr($haystack,$start);
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}


function _endswith($haystack, $needle) {
    $length = strlen($needle);
    $start  = $length * -1;  //negative
    return (substr($haystack, $start) === $needle);
}


function _remove_negative_kv($k, $v) {
    if ( _startswith($k, 'no-') || _startswith($k, 'no_') )
        return array(substr($k, 3), ! $v);
    return array($k,$v);
}


/** PHP's getopt() function royally sucks! .... it just sucks.
We'll re-implement python's getopt.gnu_getopt() function to have the same
default behaviour as the python options.py module.

See: http://docs.python.org/library/getopt.html#getopt.gnu_getopt

This function works like python's getopt(), except that GNU style scanning
mode is used by default. This means that option and non-option
arguments may be intermixed. The getopt() function stops
processing options as soon as a non-option argument is
encountered.

If the first character of the option string is `+', or if the
environment variable POSIXLY_CORRECT is set, then option
processing stops as soon as a non-option argument is encountered.
**/
function _gnu_getopt($args, $shortopts, $longopts=array()) {
    $opts = array();
    $prog_args = array();
    if (is_string($longopts))
        $longopts = array($longopts);

    // Allow options after non-option arguments?
    if (_startswith($shortopts, '+')) {
        $shortopts = substr($shortopts,1);
        $all_options_first = True;
    } elseif (array_key_exists("POSIXLY_CORRECT", $_ENV) && $_ENV["POSIXLY_CORRECT"]) {
        $all_options_first = True;
    } else {
        $all_options_first = False;
    }

    while (count($args)) {
        if ($args[0] == '--') {
            array_shift($args);
            array_merge($prog_args, $args);
            break;
        }

        if (substr($args[0],0,2) == '--') {
            $opt = substr($args[0],2);
            array_shift($args);
            list($opts, $args) = _getopt_do_longs($opts, $opt, $longopts, $args);
        } elseif (substr($args[0],0,1) == '-') {
            $opt = substr($args[0],1);
            array_shift($args);
            list($opts, $args) = _getopt_do_shorts($opts, $opt, $shortopts, $args);
        } else {
            if ($all_options_first) {
                array_merge($prog_args, $args);
                break;
            } else {
                array_push($prog_args, $args[0]);
                array_shift($args);
            }
        }
    }

    return array($opts, $prog_args);
}

class GetoptError extends Exception { }

function _getopt_do_longs($opts, $opt, $longopts, $args) {
    $i = strpos($opt,'=');
    if ($i === False) {
        $optarg = Null;
    } else {
        $opt = substr($opt,0,$i);
        $optarg = substr($opt,$i+1);
    }

    list($has_arg, $opt) = _getopt_long_has_args($opt, $longopts);
    if ($has_arg) {
        if ($optarg === Null) {
            if (! count($args))
                throw new GetoptError("option --$opt requires argument");
            $optarg = $args[0];
            array_shift($args);
        }
    } elseif ($optarg !== Null) {
        throw new GetoptError("option --$opt must not have an argument");
    }
    array_push($opts,array('--' . $opt, $optarg ? $optarg : ''));
    return array($opts, $args);
}

function _getopt_long_has_args($opt, $longopts) {
    $possibilities = array();
    foreach ($longopts as $o)
        if (_startswith($o,$opt))
            array_push($possibilities,$o);
    if (! count($possibilities))
        throw new GetoptError("option --$opt not recognized");
    // Is there an exact match?
    if (in_array($opt, $possibilities))
        return array(False, $opt);
    elseif (in_array($opt . '=', $possibilities))
        return array(True, $opt);
    // No exact match, so better be unique.
    if (count($possibilities) > 1)
        throw new GetoptError("option --$opt not a unique prefix");
    assert('count($possibilities) == 1');
    $unique_match = $possibilities[0];
    $has_arg = _endswith($unique_match,'=');
    if ($has_arg)
        $unique_match = substr($unique_match,0,-1);
    return array($has_arg, $unique_match);
}

function _getopt_do_shorts($opts, $optstring, $shortopts, $args) {
    while ($optstring != '') {
        $opt = substr($optstring,0,1);
        $optstring = substr($optstring,1);
        if (_getopt_short_has_arg($opt, $shortopts)) {
            if ($optstring == '') {
                if (! count($args))
                    throw new GetoptError("option -$opt requires argument");
                $optstring = $args[0];
                array_shift($args);
            }
            $optarg = $optstring;
            $optstring = '';
        } else {
            $optarg = '';
        }
        array_push($opts,array('-' . $opt, $optarg));
    }
    return array($opts, $args);
}

function _getopt_short_has_arg($opt, $shortopts) {
    $shortopts_a = str_split($shortopts);
    foreach (range(0, count($shortopts_a)-1) as $i) {
        if ($opt == $shortopts_a[$i] && $opt != ':')
            return _startswith($shortopts,':',$i+1);
    }
    throw new GetoptError("option -$opt not recognized");
}


/** Dictionary that exposes keys as attributes.

Keys can be set or accessed with a "no-" or "no_" prefix to negate the
value.
**/
class OptDict extends ArrayObject {
    private $_opts;
    private $_aliases;

    public function __construct($aliases) {
        parent::__construct(array(), ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS);
        $this->_opts = array();
        $this->_aliases = $aliases;
    }

    // Should be private, but PHP doesn't have the means to make another class a "friend".
    public function _unalias($k) {
        list($k, $reinvert) = _remove_negative_kv($k, False);
        if (! array_key_exists($k, $this->_aliases))
            throw new Exception("KeyError: Option '$k' is unknown.");
        list($k, $invert) = $this->_aliases[$k];
        return array($k, $invert xor $reinvert);
    }

    public function offsetSet($k, $v) {
        if ($k == '_opts' || $k == '_aliases') {
            $this->$k = $v;
            return;
        }
        list($k, $invert) = $this->_unalias($k);
        $this->_opts[$k] = _invert($v, $invert);
    }

    public function offsetGet($k) {
        if ($k == '_opts' || $k == '_aliases')
            return $this->$k;
        list($k, $invert) = $this->_unalias($k);
        return _invert($this->_opts[$k], $invert);
    }
}


function _default_onabort($msg) {
    exit(97);
}


function _intify($v) {
    $vv = intval($v);
    // If intval() fails, we'll see it here.
    if ("$vv" == $v) {
        return $vv;
    }
    return $v;
}


function _atoi($v) {
    return intval($v);
}


function _tty_width() {
    // This part is not a direct port and is a bit ugly, but it works for linux..
    if (PHP_OS != "Linux")
        return 80;

    $dims = explode(" ", shell_exec("stty size"));
    return intval($dims[1]);
}


/**Option parser.

When constructed, a string called an option spec must be given. It
specifies the synopsis and option flags and their description.  For more
information about option specs, see the top of this file.

Two optional arguments specify an alternative parsing function and an
alternative behaviour on abort (after having output the usage string).

By default, the parser function is a tweaked version of getopt(), and the abort
behaviour is to exit the program.
**/
class Options {
    public $optspec;
    public $optfunc;
    private $_onabort;
    private $_aliases;
    private $_shortopts;
    private $_longopts;
    private $_hasparms;
    private $_defaults;
    private $_usagestr;

    public function __construct($optspec, $optfunc='_gnu_getopt',
                 $onabort='_default_onabort') {
        $this->optspec = $optspec;
        $this->_onabort = $onabort;
        $this->optfunc = $optfunc;
        $this->_aliases = array();
        $this->_shortopts = 'h?';
        $this->_longopts = array('help', 'usage');
        $this->_hasparms = array();
        $this->_defaults = array();
        $this->_usagestr = $this->_gen_usage();  // this also parses the optspec
    }

    private function _gen_usage() {
        $out = array();
        $lines = explode("\n", trim($this->optspec));
        $lines = array_reverse($lines);
        $first_syn = True;
        while (count($lines)) {
            $l = array_pop($lines);
            if ($l == '--')
                break;
            array_push($out, sprintf("%s: %s\n", ($first_syn ? 'usage' : '   or'), $l));
            $first_syn = False;
        }
        array_push($out, "\n");
        $last_was_option = False;
        while (count($lines)) {
            $l = array_pop($lines);
            if (_startswith($l, ' ')) {
                array_push($out, sprintf("%s%s\n", ($last_was_option ? "\n" : ''),
                                       ltrim($l)));
                $last_was_option = False;
            } elseif ($l) {
                list($flags,$extra) = explode(' ', $l . ' ', 2);
                $extra = trim($extra);
                if (_endswith($flags, '=')) {
                    $flags = substr($flags,0,-1);
                    $has_parm = 1;
                } else {
                    $has_parm = 0;
                }
                $g = array();
                $gr = preg_match('/\[([^\]]*)\]$/', $extra, $g);
                if ($gr)
                    $defval = _intify($g[1]);
                else
                    $defval = Null;
                $flagl = explode(',', $flags);
                $flagl_nice = array();
                list($flag_main, $invert_main) = _remove_negative_kv($flagl[0], False);
                $this->_defaults[$flag_main] = _invert($defval, $invert_main);
                foreach ($flagl as $_f) {
                    list($f,$invert) = _remove_negative_kv($_f, 0);
                    $this->_aliases[$f] = array($flag_main, $invert_main xor $invert);
                    $this->_hasparms[$f] = $has_parm;
                    if ($f == '#') {
                        $this->_shortopts .= '0123456789';
                        array_push($flagl_nice, '-#');
                    } elseif (strlen($f) == 1) {
                        $this->_shortopts .= $f . ($has_parm ? ':' : '');
                        array_push($flagl_nice, '-' . $f);
                    } else {
                        $f_nice = preg_replace('/\W/', '_', $f);
                        $this->_aliases[$f_nice] = array($flag_main,
                                                 $invert_main xor $invert);
                        array_push($this->_longopts, $f . ($has_parm ? '=' : ''));
                        array_push($this->_longopts, 'no-' . $f);
                        array_push($flagl_nice, '--' . $_f);
                    }
                }
                $flags_nice = implode(', ', $flagl_nice);
                if ($has_parm)
                    $flags_nice .= ' ...';
                $prefix = sprintf('    %-20s  ', $flags_nice);
                // wordwrap doesn't offer a feature similar to "subsequent_indent",
                // so the text will wrap to the beginning of the line. We could
                // implement this to make the usage string look nicer, but it
                // would probably imply too much code for what it's worth.
                $argtext = wordwrap($prefix . $extra, _tty_width());
                array_push($out, $argtext . "\n");
                $last_was_option = True;
            } else {
                array_push($out, "\n");
                $last_was_option = False;
            }
        }
        return rtrim(implode('', $out)) . "\n";
    }

    /* Print usage string to stderr and abort. */
    public function usage($msg="") {
        print($this->_usagestr);
        if ($msg)
            fwrite(STDERR, $msg);
        if ($this->_onabort) {
            $func = $this->_onabort;
            $e = $func($msg);
        } else
            $e = "";
        if ($e)
            throw new Exception($e);
    }

    /* Print an error message to stderr and abort with usage string. */
    public function fatal($msg) {
        $msg = sprintf("\nerror: %s\n", $msg);
        return $this->usage($msg);
    }

    /**Parse a list of arguments and return (options, flags, extra).

    In the returned tuple, "options" is an OptDict with known options,
    "flags" is a list of option flags that were used on the command-line,
    and "extra" is a list of positional arguments.
    **/
    public function parse($args) {
        $func = $this->optfunc;
        if (! function_exists($func))
            $this->fatal("The supplied option parsing function '$func' is undefined.");
        try {
            list($flags, $extra) = $func($args, $this->_shortopts, $this->_longopts);
        } catch (GetoptError $e) {
            $this->fatal($e->getMessage());
        }

        $opt = new OptDict($this->_aliases);

        foreach ($this->_defaults as $k => $v)
            $opt[$k] = $v;

        foreach ($flags as $f) {
            $k = ltrim($f[0],'-');
            $v = $f[1];
            if (in_array($k, array('h', '?', 'help', 'usage')))
                $this->usage();
            if (array_key_exists('#', $this->_aliases) &&
                  in_array($k, array('0','1','2','3','4','5','6','7','8','9'))) {
                $v = _atoi($k);  # guaranteed to be exactly one digit
                list($k, $invert) = $this->_aliases['#'];
                $opt['#'] = $v;
            }
            else {
                list($k, $invert) = $opt->_unalias($k);
                if (! $this->_hasparms[$k]) {
                    assert('$v == ""');
                    $v = (array_key_exists($k,$opt->_opts) ? $opt->_opts[$k] : 0) + 1;
                }
                else
                    $v = _intify($v);
            }
            $opt[$k] = _invert($v, $invert);
        }
        return array($opt,$flags,$extra);
    }
}
