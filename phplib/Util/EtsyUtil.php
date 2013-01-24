<?php

require_once 'Autoload.php';

class Util_Etsyutil {
    public static function getRepoPath($user) {
        $location = getenv('ETSY_SRC');
        if (!$location) {
            if (preg_match(";development/([^/]+);", getcwd(), $matches) > 0) {
                $location = "/home/$user/development/".$matches[1];
            } else {
                $location = "/home/$user/development/Etsyweb"; // Backwards compatibility
            }
        }
        return $location;
    }
    
    public static function getUser() {
        $user = getenv('LDAP_USER');  // LDAP_USER will override USER
        if (!$user) {
            $user = getenv("USER");
        }
        return $user;
    }
}
