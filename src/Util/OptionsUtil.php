<?php

namespace TryLib\Util;

class OptionsUtil {

    /**
      * parse a param string in the form of k=v
      * and return an array of 2 elements (key,value)
      * if value does not exists, use ""
      */
    public static function parseParam($param) {
        $p = explode('=', $param, 2);
        if (count($p) === 1) {
            $p[] = "";
        }
        return $p;
    }

    /**
      * Parse a string or array of extra_param options formatted as key=value
      * and return an array of key->value
      */
    public static function parseExtraParameters($extra_param_option) {
        $params = [];
        if (is_string($extra_param_option)) {
            $params[] = self::parseParam($extra_param_option);
        } elseif (is_array($extra_param_option)) {
            foreach ($extra_param_option as $param) {
                $params[] = self::parseParam($param);
            }
        }
        return $params;
    }
}
