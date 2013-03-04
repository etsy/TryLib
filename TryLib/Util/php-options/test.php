<?php
include('options.php');

// First line is the summary
// one separator '--'
// one line per argument, list short and corresponding long arguments, describe
// it and optionally give its default value between square brackets.
// an empty line outputs an empty line in the usage(), so helps to group
// options.
//
// this option spec was shamelessly taken from the bup project, from which the
// options.py Python module originated. it was modified to be able to show off
// all of the options module's capabilities.
$s = "
bup save [options...] [-tc] [-n name] <filenames...>
--
t,tree     output a tree id
c,commit   output a commit id

r,remote=  hostname:/path/to/repo of remote repository
n,name=    name of backup set to update (if any)
d,date=    date for the commit (seconds since the epoch)
v,verbose  increase log output (can be used more than once)
q,quiet,no-progress    don't show progress meter
smaller=   only back up files smaller than n bytes
bwlimit=   maximum bytes/sec to transmit to server
f,indexfile=  the name of the index file (normally BUP_DIR/bupindex)
strip      strips the path to every filename given
strip-path= path-prefix to be stripped when saving
graft=     a graft point *old_path*=*new_path* (can be used more than once)
#,compress=  set compression level to # (0-9, 9 is highest) [1]
";

$o = new Options($s);

// give it any arbitrary list of arguments, the first one will be ignored
// (program name).
list($opt, $flags, $extra) = $o->parse($argv);

// Arguments with no trailing '=' give a boolean value (almost.. see below)
if ($opt->c)
    print "Committing!\n";

// Arguments that are specified in the option spec above with a prefix of 'no-'
// or 'no_' get their boolean value inversed.
// Try using -q to reverse the value of this argument
if ($opt->progress)
    print "Showing progress meter\n";
else
    print "NOT showing progress meter\n";

// well ... actually, arguments with no trailing '=' can be used multiple times
// and add 1 to the variable each time. (which will stay True as a boolean)
// Try using -v multiple times to see.
print "verbose level: ". $opt->v. "\n";

// Access arguments both by short and long argument names
print "short arg file name: ". $opt->f. "\n";
print "long arg file name: ". $opt->indexfile. "\n";

// A short argument of '#' means the program expects an argument like -4 or -8
print "-# should be an integer: ". $opt->compress. "\n";
