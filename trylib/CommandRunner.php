<?php

class CmdRunner {
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
}
