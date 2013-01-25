<?php

require_once 'Console/GetoptPlus.php';

class Try_Util_OptionsParser {
    private $config;
    private $defaults;

    public function __construct() {
        $this->config = null;
        $this->defaults = null;
    }

    public function getConfig() {
        if (is_null($this->config)) {
            $this->config = array(
                'header' => array('"try" your changeset on Jenkins'),
                'usage' => array('','[options] [subjobs]'),
                'options' => array(
                    array('short' => 'n', 'long' => 'diffonly', 'type' => 'noarg',
                       'desc' => array('Create diff, but do not send to Jenkins')
                    ),

                    array('short' => 'v', 'long' => 'verbose', 'type' => 'noarg',
                        'desc' => array('Show shell commands as they run.')
                    ),

                    array('short' => 'p', 'long' => 'patch', 'type' => 'mandatory',
                        'desc' => array(
                                    '/path/to/patch.diff',
                                    'Don\'t generate diffs; use custom patch file instead'
                                  )
                    ),

                    array('short' => 'c', 'long' => 'poll', 'type' => 'noarg',
                        'desc' => array('Poll for job completion and print results')
                    ),

                    array('short' => 'P', 'long' => 'showprogress', 'type' => 'noarg',
                        'desc' => array('print subtasks progressively as they complete (implies c)')
                    ),

                    array('short' => 's', 'long' => 'staged', 'type' => 'noarg',
                        'desc' => array('use staged changes only to generate the diff')
                    ),

                    array('short' => 'C', 'long' => 'callback', 'type' => 'mandatory',
                        'desc' => array('callback string',
                                'Callback string to execute at the end of the try run.',
                                'Use ${status} and ${url} as placeholders ' .
                                'for the try build status and url',
                                'Example: -C \'echo "**Try status : [\${status}](\${url})**"\''
                                )
                    ),
                ),
                'parameters' => array('[subjobs]',
                                      'try subjobs to execute (without the "try-" prefix)','Example : unit-tests'),
                'footer' => array(''),
            );
        }

        return $this->config;
    }

    public function setConfig($config) {
        $this->config = $config;
    }

    public function getDefaults() {
        if (is_null($this->defaults)) {
            $this->defaults = array(
                'verbose' => false,
                'diffonly' =>false,
                'patch' => null,
                'staged' => false,
                'showprogress' => false,
                'callback' => null
            );
        }
        return $this->defaults;
    }

    public function setDefaults($defaults) {
        $this->defaults = $defaults;
    }

    public function getOptions() {
        try {
            list($options, $jobs) = Console_Getoptplus::getoptplus($this->getConfig(), 'short2long', true);
        } catch(Console_GetoptPlus_Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            exit(1);
        }

        foreach($options as $key=>$val) {
            if ($val === '') {
                $options[$key] = true;
            }
        }

        $options = array_merge($this->getDefaults(), $options);
        $options['jobs'] = $jobs;
        return $options;
    }
}
