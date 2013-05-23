
php-options: stop hitting your head on the desk, and parse those options, already!
==================================================================================

This PHP module helps you parse command-line arguments without the usual bad
headaches that come from the PHP language.

It is intended as a one-file drop-in to make parsing work.

Origins of the module
---------------------

The code from this module is a PHP port from the original Python module,
"options.py", written by Avery Pennarun and contributors for the bup[1] project.

[1]:https://github.com/apenwarr/bup/

How good is it for you?
-----------------------

The module removes the usual bad design in having to call a method on your
parser to add every stupid option.

Instead of splitting everything apart in unreadable function calls, you declare
a string (called option spec) with pretty much the whole usage text, with
minimal formatting, and this module does the rest: it parses your option spec
to be aware of the program's summary, options both short and long, option
descriptions, and option default values.

Also, contrary to PHP's getopt() function, it's possible to parse any arbitrary
list of arguments (with the first argument always being considered as the
program name, so avoided -- this way you can pass $argv as-is).

An option spec, you say? What exactly is it?
--------------------------------------------

As mentioned above, the option spec is a string of text which defines the
program's summary and all of the options.

An options spec is made up of two parts, separated by a line with two dashes.
The first part is the synopsis of the command and the second one specifies
options, one per line.

Each non-empty line in the synopsis gives a set of options that can be used
together (e.g. alternative ways to call the program).

Option flags must be at the begining of the line and multiple flags are
separated by commas. Usually, options have a short, one character flag, and a
longer one, but the short one can be omitted. More than one long option can be
specified in one line.

Long option flags are used as the option's key for the OptDict produced when
parsing options.

When the flag definition is ended with an equal sign, the option takes
one string as an argument, and that string will be converted to an
integer when possible. Otherwise, the option does not take an argument
and corresponds to a boolean flag that is true when the option is
given on the command line.

Long options that start with 'no-' or 'no\_' negate their boolean value.

The option's description is found at the right of its flags definition, after
one or more spaces. The description ends at the end of the line. If the
description contains text enclosed in square brackets, the enclosed text will
be used as the option's default value.

Options can be put in different groups. Options in the same group must be on
consecutive lines. Groups are formed by inserting a line that begins with a
space. The text on that line will be output after an empty line.

Example usage
-------------

Here's a very short example, just to demonstrate how clear the code looks by
using this module:

    include("options.php");
    
    $spec = "
    my_script [-q] [--config=<file>] file ...
    --
    q,quiet   Don't show progress info on the terminal.
    c,config= Specify an alternative config file [~/.my_script.conf]
    ";
    
    $o = new Options($spec);
    
    list($opt, $flags, $extra) = $o->parse($argv);
    
    if (! $opt->q)
        print("Starting some work\n");
    
    $conf = fopen($opt->config, 'w');
    // and so on ...

For a more complete example that documents the module's capabilities, read the
file 'test.php' included with the module. You can run the file and give it
arguments from the option spec (or not in there, see what happens with unknown
options):

    php test.php -c -f blah.idx -vvv

Licensing
---------

Most of the code is licensed under a two-clause MIT-style license.

The functions that implement \_gnu\_getopt() come from the Python module
"getopt.py" and are under the Python license.

Both licenses should be available as separate files included with the module.
If not, you can find them in the repository at the following URL:

https://github.com/lelutin/php-options

