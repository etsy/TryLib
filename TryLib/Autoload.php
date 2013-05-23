<?php

spl_autoload_register(
    function($class_name) {
        $path = __DIR__ . "/../" . str_replace('_', '/', $class_name);
        include $path . '.php';
    }
);
