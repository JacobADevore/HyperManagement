<?php

namespace TitanLocks\Brain\Services\Account;

use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\Services\GlobalVariables;
use Symfony\Component\HttpFoundation\Response;


/**
 * Class RequirementCheck
 * @package Hymont\Brain\Services\Account
 *
 * All functions static and return true when success and return != true when function is not a success
 * @param $type - must be 1 = AgentsAndClients or 2 = BrokerageAndMLSs or 3 = Ours
 */
class RequirementCheck
{

    // requirement check on full name
    public static function jobTitleName($jobTitleName) {
        // checks if name is 0 characters
        if ( strlen($jobTitleName) == 0 ) {
            return 'Please enter a job title name';
        }

        // checks if name is over 244 characters long
        if (strlen($jobTitleName) > 244) {
            return 'Job title name must be a maximum of 244 characters';
        }

        if (!preg_match('/^[a-z0-9 .\-]+$/i', $jobTitleName)) {
            return 'Job title name can only contain letters, numbers, spaces, dashes, and periods';
        }

        // function completed returns true
        return true;
    }

    // requirement check on full name
    public static function passcode($passcode) {
        // checks if name is 0 characters
        if ( strlen($passcode) < 4 || !is_numeric($passcode)) {
            return 'Passcodes must be at least 4 digits long';
        }

        // checks if name is over 244 characters long
        if ( strlen($passcode) > 9 || !is_numeric($passcode)) {
            return 'Passcodes must be a maximum of 9 digits long';
        }

        // function completed returns true
        return true;
    }

    public static function lockName($lockName) {
        // checks if name is 0 characters
        if ( strlen($lockName) == 0 ) {
            return 'Please enter a lock name';
        }

        // checks if name is over 244 characters long
        if (strlen($lockName) > 244) {
            return 'Lock name must be a maximum of 244 characters';
        }

        if (!preg_match('/^[a-z0-9 _]+$/i', $lockName)) {
            return 'Lock name can only contain letters, numbers, spaces, and underscores';
        }

        // function completed returns true
        return true;
    }

    // runs requirement check on username
    public static function username($username, $type) {
        // checks if username is 0 characters
        if ( strlen($username) == 0 ) {
            return 'Please enter a username';
        }

        // checks if username is over 32 characters long
        if (strlen($username) > 32) {
            return 'Username must be 32 characters or less';
        }

        // check if username has anything other than letters and numbers
        if (preg_match('/[^A-Za-z0-9]/', $username)) {
            return 'Username can only contain letters and numbers';
        }

        // datastore connection
        $datastore = new DatastoreClient();

        // type = brokerageAndMLS ( default )
        $query = $datastore->query()
            ->kind('Ours')
            ->filter('username', '=', $username);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'RequirementCheck::Username', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            return $datastoreErrorHandling;
        }

        // checks if username is already taken
        foreach ( $result as $taken) {
            return 'Username not available, please enter another username';
        }

        // function completed returns true
        return true;
    }

    public static function email($email, $type) {
        // checks if email given isn't valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email';
        }

        // datastore connection
        $datastore = new DatastoreClient();

        // type = AgentsAndUsers ( default )
        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('email', '=', $email);

        // type = Ours
        if ($type == 2) {
            $query = $datastore->query()
                ->kind('Ours')
                ->filter('email', '=', $email);
        }

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'RequirementCheck::email', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            return $datastoreErrorHandling;
        }

        // checks if email is already taken
        foreach ( $result as $taken) {
            return 'This email address is already in use, please use a different one';
        }

        // function completed returns true
        return true;
    }

    // runs requirement check on password
    public static function password($password) {
        // checks if password is under 6 characters long
        if ( strlen($password) < 6 ) {
            return 'Password must be a minimum of 6 characters, please enter at least 6 characters and try again';
        }

        // checks if password is over 128 characters long
        if ( strlen($password) > 128 ) {
            return 'Password must be a maximum of 128 characters, please enter a maximum of 128 characters and try again';
        }

        // function completed returns true
        return true;
    }

    // runs requirement check on password
    public static function location($locationName) {
        // checks if password is under 6 characters long
        if ( strlen($locationName) == 0 ) {
            return 'Please enter a valid Location name';
        }

        // checks if password is over 128 characters long
        if ( strlen($locationName) > 244 ) {
            return 'Location name must be a maximum of 244 characters, please enter a maximum of 244 characters and try again';
        }

        if (!preg_match('/^[a-z0-9 .\-]+$/i', $locationName)) {
            return 'Location name can only contain letters, numbers, spaces, dashes, and periods';
        }

        // function completed returns true
        return true;
    }


}