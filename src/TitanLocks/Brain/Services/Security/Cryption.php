<?php

namespace TitanLocks\Brain\Services\Security;

/**
 * Class Cryption
 * @package TitanLocks\Brain\Services\Security
 */
class Cryption
{

    public static function hashing($string) {
        return password_hash( '$fL07Fj&j3,li'.$string.'2lU', PASSWORD_ARGON2I);
    }

    public static function hashingVerifying($string, $hash) {
        return password_verify( '$fL07Fj&j3,li'.$string.'2lU', $hash);
    }

}