<?php

class CmdRunner {
    private $verbose;
    private $stderr;
    private $lastOutput;
    
    public function __construct(
        $verbose = false,
        $stderr = null
    ) {
        $this->verbose = $verbose;

        if (is_null($stderr)) {
            $stderr = fopen('php://stderr', 'w');
        }

        $this->stderr = $stderr;    

        $this->lastOutput = null;
    }
    
    public function getLastOutput() {
        return $this->lastOutput;
    } 
    
    public function run($cmd) {
        if ($this->verbose) {
            fputs($this->stderr, "$cmd\n");
        }
        $this->lastOutput = system($cmd, $ret);
        return $ret;
    }
}
