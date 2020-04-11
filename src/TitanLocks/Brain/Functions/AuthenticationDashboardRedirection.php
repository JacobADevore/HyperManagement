<?php

namespace TitanLocks\Brain\Functions;

/**
 * Class AuthenticationDashboardRedirection
 * @package TitanLocks\Brain\Functions
 *
 * class is only used for authentication dashboard reDirection route name
 */
class AuthenticationDashboardRedirection
{

    // grabs the users type in datastore and gets the @returns the associated dashboard route
    public static function AuthenticationDashboardRedirectionName($type) {
        // sets switch to type of user's account so we can set the correct dashboard user should go too
        switch ($type) {
            // users
            case 0:
                $dashboard = 'dashboard';
                break;
            // admins
            case 1:
                $dashboard = 'admin/dashboard';
                break;
            // ours
            case 2:
                $dashboard = 'our/dashboard';
                break;
        }
        return $dashboard;
    }

}