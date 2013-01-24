<?php

class CommandRunner {
    private $verbose;
    private $stderr;
    private $out;
    
    public function __construct(
        $verbose = false,
        $stderr = null
    ) {
        $this->verbose = $verbose;

        if (is_null($stderr)) {
            $stderr = fopen('php://stderr', 'w');
        }

        $this->stderr = $stderr;    

        $this->out = array();
    }
    
    public function getOutput() {
        return implode("\n", $this->out);
    } 
    public function getLastOutput() {
        return end($this->out);
    } 
    
    public function run($cmd) {
        if ($this->verbose) {
            fputs($this->stderr, "$cmd\n");
        }
        exec($cmd, $this->out, $ret);
        return $ret;
    }
    
    public function terminate($how) {
        fputs($this->stderr, "$how\n");
        exit(1);
    }

    public function chdir($wd) {
        if ($this->verbose) {
            fputs($this->stderr, "\$ cd $wd");
        }

        if (!chdir($wd)) {
            $this->terminate("chdir $wd");
        }
    }
}
