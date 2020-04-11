<?php

namespace TitanLocks\Brain\Functions;

class Secrets {

    // creates a random string with all characters on keyboard with a min length of $minLength and a max length of $maxLength
    static public function secretGenerator($minLength, $maxLength) {
        $length = rand($minLength, $maxLength);
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=]}[{;:"?/>.<,\|';
        $charactersLength = strlen($characters);
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $characters[rand(0, $charactersLength - 1)];
        }
        return $secret;
    }

    // creates a random string with only letters and numbers with a min length of $minLength and a max length of $maxLength
    static public function secretGeneratorOnlyLettersAndNumbers($minLength, $maxLength) {
        $length = rand($minLength, $maxLength);
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $characters[rand(0, $charactersLength - 1)];
        }
        return $secret;
    }

    // creates a random number with a min length of $minLength and a max length of $maxLength
    static public function secretGeneratorOnlyNumbers($minLength, $maxLength) {
        $length = rand($minLength, $maxLength);
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $characters[rand(0, $charactersLength - 1)];
        }
        return $secret;
    }

}
