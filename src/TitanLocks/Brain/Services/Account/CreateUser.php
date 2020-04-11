<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\Functions\Secrets;

class CreateUser
{
    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public $success = false;

    public function __construct($adminId, $userId, $email, $reEnteredEmail, $password, $jobTitle, $usersJobTitle, $ttlUsername, $ttlPassword)
    {
        // check if email and reEntered email are the same and if password meets requirement check then check if email is not associated with any other account and check to make sure jobTitle is a valid job title
        if ($this->checkCreateUserInformation($adminId, $email, $reEnteredEmail, $password, $jobTitle) == false) {
            return;
        }

        // check to see if user has access to create a feature or if user is admin ( check if user is admin by if adminId and userId are the same )
        if ($this->checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $usersJobTitle) == false) {
            return;
        }

        // create the user under the adminId with the information given
        if ($this->createUser($adminId, $email, $password, $jobTitle, $ttlUsername, $ttlPassword) == false) {
            return;
        }

        // CreateUser has successfully ran through all functions with all success
        // changes success to true so we can know that CreateUser passed successfully
        return $this->success = true;
    }

    // check if email and reEntered email are the same and if password meets requirement check then check if email is not associated with any other account and check to make sure jobTitle is a valid job title
    public function checkCreateUserInformation($adminId, $email, $reEnteredEmail, $password, $jobTitle) {
        if ($jobTitle == '') {
            // returns error alert
            $this->alert['error'] = 'Please choose a job title';
            return false;
        }

        // checks if the new email and reEntered new email are not the same
        if ($email != $reEnteredEmail) {
            // returns error alert
            $this->alert['error'] = 'Email and re-entered new email don\'t match';
            return false;
        }

        // requirement check password
        $passwordRequirementCheck = RequirementCheck::password($password);

        // checks if the inputted password didn't pass requirementCheck
        if ($passwordRequirementCheck !== true) {
            // returns error alert
            $this->alert['error'] = $passwordRequirementCheck;
            return false;
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('email', '=', $email)
            ->limit(2);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'CreateJobTitle::checkUserId', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        $count = 0;
        foreach ($result as $entity) {
            $count++;
        }

        // checks if results amount is greater than 25 requests in the past 6 hours ( 1 array default given in array )
        if ($count != 0) {
            // returns error alert
            $this->alert['error'] = 'Email is already in use, please try again using a different email';
            return false;
        }

        $query = $datastore->query()
            ->kind('JobTitles')
            ->filter('adminUserId', '=', $adminId)
            ->filter('name', '=', $jobTitle)
            ->limit(2);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'CreateJobTitle::checkUserId', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        $count = 0;
        foreach ($result as $entity) {
            $count++;
        }

        // checks if results amount is greater than 25 requests in the past 6 hours ( 1 array default given in array )
        if ($count != 1) {
            // returns error alert
            $this->alert['error'] = 'Job title is not valid, please refresh and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // check to see if user has access to create a feature or if user is admin ( check if user is admin by if adminId and userId are the same )
    public function checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $usersJobTitle) {
        if ($adminId == $userId) {
            // function completed returns true
            return true;
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('JobTitles')
            ->filter('adminUserId', '=', $adminId)
            ->filter('name', '=', $usersJobTitle)
            ->limit(2);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'CreateJobTitle::checkCreateAUserFeatureAccessAuthentication', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        $usersFeatures = array();

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        $count = 0;
        foreach ($result as $entity) {
            $usersFeatures = $entity['features'];
            $count++;
        }

        // checks if results amount is greater than 25 requests in the past 6 hours ( 1 array default given in array )
        if ($count == 0) {
            // returns error alert
            $this->alert['error'] = 'We are having trouble verifying your job title, please contact your admin';
            return false;
        }

        if ($usersFeatures[0] !== true) {
            $this->alert['error'] = 'You don\'t have access to this feature, please contact your admin if you are suppose to';
            return false;
        }

        // function completed returns true
        return true;
    }

    // create the user under the adminId with the information given
    public function createUser($adminId, $email, $password, $jobTitle, $ttlUsername, $ttlPassword) {
        // datastore connection
        $datastore = new DatastoreClient();

        // creates the secret for the emailVerification ( uses only letters and numbers because its sent over url and url cant hold some symbols )
        $secret = Secrets::secretGeneratorOnlyLettersAndNumbers(16, 32);

        // email verification info to enter info database
        $userInformation = [
            'adminUserId' => $adminId,
            'email' => $email,
            'jobTitle' => $jobTitle,
            'password' => password_hash($password, PASSWORD_ARGON2I),
            'secret' => $secret,
            'type' => 0,
            'ttlpassword' => $ttlPassword,
            'ttlusername' => $ttlUsername,
        ];

            $keysWithAllocatedIds = $datastore->allocateIds([$datastore->key('AdminsAndUsers')]);

        // create entity for datastore
        $emailVerification = $datastore->entity($keysWithAllocatedIds[0], $userInformation);

        // insert entity to datastore
        // checks if insert had any errors, if so then send error and return false
        $result = $datastore->insert($emailVerification);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'CreateUser::createUser', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // function completed returns true
        return true;
    }

}