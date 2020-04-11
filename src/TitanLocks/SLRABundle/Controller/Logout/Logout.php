<?php

namespace TitanLocks\SLRABundle\Controller\Logout;

/**
 * Class Logout
 * @package TitanLocks\SLRABundle\Controller\Logout
 *
 * unsets session authentication information if set
 * and unsets user's authentication cookie information
 */
class Logout
{
    // unsets the LID and Li ( users authentication information )
    public function __construct()
    {

        // checks if session authentication information is set
        if (isset($_SESSION["LID"])) {
            // unsets session authentication information
            unset($_SESSION["LID"]);
        }

        // checks if session authentication information is set
        if (isset($_SESSION["Li"])) {
            // unsets session authentication information
            unset($_SESSION["Li"]);
        }

        // unsets cookie authentication information
        setcookie('LID', '', time() - 3600, '/', 'localhost', isset($_SERVER["HTTPS"]), true);
        setcookie('Li', '', time() - 3600, '/', 'localhost', isset($_SERVER["HTTPS"]), true);
    }
}