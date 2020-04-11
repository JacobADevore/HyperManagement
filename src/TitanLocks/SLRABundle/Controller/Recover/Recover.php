<?php

namespace TitanLocks\SLRABundle\Controller\Recover;

use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use Mailgun\Mailgun;
use TitanLocks\Brain\GlobalVariables;
use TitanLocks\Brain\Functions\Secrets;

class Recover
{

    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public $success = false;

    // holds user's datastore Id of account so we know what account the recover is associated to
    public $datastoreId;

    // need to check that email is connect to a user account and hasn't send over 10 request in the past hour
    public function __construct($email)
    {
        // requirement check email before touching datastore
        if ($this->requirementCheckEmail($email) == false) {
            return;
        }

        // checks that email is connected to an account
        if ($this->checkEmailInConnectionToAnAccount($email) == false) {
            return;
        }

        // checks that account hasn't send 25 requests in the past hour
        if ($this->checkMaximumPasswordChangeRequests($email) == false) {
            return;
        }

        // send email so user can change password
        if ($this->sendRecoverPasswordEmail($email) == false) {
            return;
        }

        // Recover password successfully sent
        $this->success = true;
    }

    // requirement check email before touching datastore
    public function requirementCheckEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // returns error alert
            $this->alert['error'] = 'Please enter a valid e-mail';
            return false;
        }
        // function completed returns true
        return true;
    }

    // checks that email is connected to an account
    public function checkEmailInConnectionToAnAccount($email) {
        // datastore connection
        $datastore = new DatastoreClient();

        // sets query to search for user's email entered
        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('email', '=', $email)
            ->limit(1);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'Recover::checkEmailInConnectionToAnAccount', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling.'fsafas';
            return false;
        }

        $count = 0;
        // runs foreach on query results to get email verification information from ID
        foreach ($result as $info) {
            $this->datastoreId = $info->key()->path()[0]['id'];
            $count++;
        }

        // checks if there isn't exactly one result from email verification ID query
        if ($count == 0) {
            // returns error alert
            $this->alert['error'] = 'No email associated to that email, please try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // checks that account hasn't send 25 requests in the past hour
    public function checkMaximumPasswordChangeRequests($email) {
        // datastore connection
        $datastore = new DatastoreClient();

            $query = $datastore->query()
                ->kind('PasswordChange')
                ->filter('email', '=', $email)
                ->filter('timeStamp', '>', (strtotime("now") - 3600))
                ->limit(25);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'Recover::checkMaximumPasswordChangeRequests', 'datastore');
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
        if ($count >= 25) {
            // returns error alert
            $this->alert['error'] = 'You may only send 25 password change requests every hour, please try again later';
            return false;
        }

        // function completed returns true
        return true;
    }

    public function sendRecoverPasswordEmail($email) {
        // datastore connection
        $datastore = new DatastoreClient();

        // Mail gun connection
        $mg = new Mailgun(GlobalVariables::$mailgun_apikey);

        // creates the secret for the emailVerification ( uses only letters and numbers because its sent over url and url cant hold some symbols )
        $secret = Secrets::secretGeneratorOnlyLettersAndNumbers(16, 32);

        // password change info to enter info database
        $passwordChangeInformation = [
            'id' => $this->datastoreId,
            'email' => $email,
            'secret' => $secret,
            'timeStamp' => strtotime("now"),
            'completed' => 0
        ];

        // gets an auto generated key id we can use for email Verification
        // uses different type to know how to search for the parent keys
            $keysWithAllocatedIds = $datastore->allocateIds([$datastore->key('PasswordChange')]);

        // create entity for datastore
        $passwordChange = $datastore->entity($keysWithAllocatedIds[0], $passwordChangeInformation);

        // insert entity to datastore
        // checks if insert had any errors, if so then send error and return false
        $result = $datastore->insert($passwordChange);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'Recover::sendRecoverPasswordEmail', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }


        $mg->sendMessage('titanlocks.co', array(
                        'from'=>'TitanLocks <noreply@titanlocks.co>',
                        'to'=> $email,
                        'subject' => 'Recovery request for your TitanLocks.co Account',
                        'text' => 'Thanks for visiting TitanLocks.co! Per your request, we have created a new password link for you to change your password.

Please click the link http://localhost/web/app_dev.php/recover?id='.$keysWithAllocatedIds[0]->path()[0]['id'].'&secret='.$secret.' to reset your password. Thank you again for using TitanLocks.'
                         ));

        // function completed returns true
        return true;
    }

}