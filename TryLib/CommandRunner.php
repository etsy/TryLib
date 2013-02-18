<?php

class TryLib_CommandRunner {
    protected $verbose;
    protected $stderr;
    protected $out;

    public function __construct($verbose = false, $stdout = null, $stderr = null) {
        $this->verbose = $verbose;

        if (is_null($stderr)) {
            $stderr = fopen('php://stderr', 'w');
        }

        $this->stderr = $stderr;

        if (is_null($stdout)) {
            $stdout = fopen('php://stderr', 'w');
        }

        $this->stdout = $stdout;

        $this->out = '';
    }

    public function getOutput() {
        return $this->out;
    }

    public function run($cmd, $silent=true, $ignore_errors=false) {
        if ($this->verbose) {
            fputs($this->stderr, '$ ' . $cmd . PHP_EOL);
        }

        if ($silent) {
            exec($cmd, $out, $ret);
            $this->out = implode(PHP_EOL, $out);
        } else {
            $this->out = system($cmd, $ret);
        }

        if (!$ignore_errors && $ret) {
            $this->terminate('Failed running command ' . $cmd);
        }
        return $ret;
    }

    public function terminate($how) {
        fputs($this->stderr, $how . PHP_EOL);
        exit(1);
    }

    public function info($what, $new_line=true) {
        fputs($this->stdout, $what);
		if ($new_line) {
			fputs($this->stdout, PHP_EOL);
		}
    }

    public function warn($about) {
        fputs($this->stderr, PHP_EOL . 'WARNING : ' . $about . PHP_EOL);
    }

    public function chdir($wd) {
        if ($this->verbose) {
            fputs($this->stderr, '$ cd ' . $wd . PHP_EOL);
        }

        if (!chdir($wd)) {
            $this->terminate("chdir $wd");
        }
    }
}
