<?php

class PatchGenerator {
    private $location;
    private $cmdRunner;
    private $origin;
    
    public function __construct(
        $location,
        $cmdRunner,
        $origin="master"
    ) {    
        $this->location = $location;
        $this->cmdRunner = $cmdRunner;
        $this->origin = $origin;
    }

    function generateDiff($staged_only=false) {    
        $patch = $this->location . "/patch.diff";
        
        $args = array(
            "--src-prefix=''",
            "--dst-prefix=''",
            "--no-color",
            "origin/" . $this->origin,
        );

        if ($staged_only) {
            $args[] = "--staged";
        }
        
        $cmd = "cd $this->location;git diff " . implode(' ', $args) . " > " . $patch;
        $this->cmdRunner->run($cmd);
        return $patch;
    }
}
