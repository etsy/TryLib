<?php

class Try_Util_Etsyutil {

    public static function getUserAndRepoPath() {
        $user = self::getUser();
        return array($user, self::getRepoPath($user));
    }

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
