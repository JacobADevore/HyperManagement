<?php

namespace TitanLocks\SLRABundle\Controller\Recover;

use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\Account\RequirementCheck;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\GlobalVariables;
use Mailgun\Mailgun;


class RecoverChangePassword
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

    // holds user's datastore email of account so we know what email to email about the password being changed
    public $datastoreEmail;

    public function __construct($newPassword, $reEnteredNewPassword, $id, $secret)
    {
        // checks to make sure that new password meets password requirements and checks to make sure that new password and reEntered new password are the same
        if ($this->checkPassword($newPassword, $reEnteredNewPassword) == false) {
            return;
        }

        // checks to make sure that id and secret information match datastore passwordchange information
        if ($this->passwordChangeCheck($id, $secret) == false) {
            return;
        }

        // change users password and send email
        if ($this->changeUsersPasswordAndSendEmail($newPassword, $id) == false) {
            return;
        }

        // Recover password successfully sent
        $this->success = true;
    }

    // checks password for requirements and checks to make sure new password and inputted password are not the same
    public function checkPassword($newPassword, $reEnteredNewPassword) {
        // requirement check password
        $passwordRequirementCheck = RequirementCheck::password($newPassword);

        // checks if the inputted password didn't pass requirementCheck
        if ($passwordRequirementCheck !== true) {
            // returns error alert
            $this->alert['error'] = $passwordRequirementCheck;
            return false;
        }

        // checks to see if inputted new password and inputted reEntered new password match
        if ($newPassword != $reEnteredNewPassword) {
            // returns error alert
            $this->alert['error'] = 'New password and re-entered new password don\'t match, please make sure they match and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // checks to make sure that id and secret information match datastore passwordchange information
    public function passwordChangeCheck($id, $secret) {
        // datastore connection
        $datastore = new DatastoreClient();

            $query = $datastore->query()
                ->kind('PasswordChange')
                ->filter('__key__', '=', $datastore->key('PasswordChange', $id))
                ->filter('timeStamp', '>', (strtotime("now") - 3600))
                ->limit(1);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'RecoverChangePassword::passwordChangeCheck', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        $count = 0;
        foreach ($result as $entity) {
            $this->datastoreId = $entity['id'];
            $this->datastoreEmail = $entity['email'];
            $count++;
        }

        // checks if results amount is greater than 25 requests in the past 6 hours ( 1 array default given in array )
        if ($count != 1) {
            // returns error alert
            $this->alert['error'] = 'There is no recover password request with that information, please try recovering your password again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // change users password and send email
    public function changeUsersPasswordAndSendEmail($newPassword, $id) {
        // datastore connection
        $datastore = new DatastoreClient();

        // Mail gun connection
        $mg = new Mailgun(GlobalVariables::$mailgun_apikey);

        // commits newPassword to user account
        // creates transaction to update user
        $transaction = $datastore->transaction();
        // creates key to search for users account
        $key = $datastore->key('AdminsAndUsers', $this->datastoreId);
        // creates lookup to find user's account from key
        $user = $transaction->lookup($key);
        // changes the users current password to newPassword
        $user['password'] = password_hash($newPassword, PASSWORD_ARGON2I);
        // inserts the change to datastore of user
        $transaction->upsert($user);
        // creates lookup to find user's account from key
        $passwordChange = $transaction->lookup($datastore->key('PasswordChange', $id));
        // changes the users current password to newPassword
        $passwordChange['completed'] = 1;
        // inserts the change to datastore of user
        $transaction->upsert($passwordChange);
        // pushes the changes to the datastore
        $result = $transaction->commit();


        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'RecoverChangePassword::changeUsersPasswordAndSendEmail', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        $mg->sendMessage('titanlocks.co', array(
            'from'=>'TitanLocks <noreply@titanlocks.co>',
            'to'=> $this->datastoreEmail,
            'subject' => 'Revision to your TitanLocks.co Account',
            'text' => 'Thanks for visiting TitanLocks.co! Per your request, we have change your password to your account.'
        ));

        // function completed returns true
        return true;
    }

}