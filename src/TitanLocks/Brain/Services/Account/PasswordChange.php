<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\GlobalVariables;
use Mailgun\Mailgun;

/**
 * Class PasswordChange
 * @package Hymont\Brain\Services\Account
 *
 * allows for users to change their password connected to their account
 */
class PasswordChange
{
    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public $success = false;

    /**
     * PasswordChange constructor.
     * @param $userId - id of the user
     * @param $email - current datastore email of the user
     * @param $password - current datastore password of the user
     * @param $inputtedPassword - inputted password
     * @param $inputtedNewPassword - inputted new password
     * @param $inputtedreEnteredNewPassword - inputted reEntered new password
     * @param $type - type of the user's account
     * @param \Twig_Environment $twig - twig environment so that we can send rendered template emails
     */
    public function __construct($userId, $email, $password, $inputtedPassword, $inputtedNewPassword, $inputtedreEnteredNewPassword, $type)
    {

        // checks password for requirements and checks to make sure new password and inputted password are not the same
        if ($this->checkPassword($password, $inputtedPassword, $inputtedNewPassword, $inputtedreEnteredNewPassword, $type) == false) {
            return;
        }

        // update users password to inputted new password and sends email to user's email to notify them that their password has been changed
        if ($this->updatePasswordAndSendNotifyEmail($userId, $email, $inputtedNewPassword, $type) == false) {
            return;
        }

        // PasswordChange has successfully ran through all functions with all success
        // changes success to true so we can know that PasswordChange passed successfully
        $this->success = true;
    }

    // checks password for requirements and checks to make sure new password and inputted password are not the same
    public function checkPassword($password, $inputtedPassword, $inputtedNewPassword, $inputtedreEnteredNewPassword, $type) {
        // requirement check password
        $passwordRequirementCheck = RequirementCheck::password($inputtedNewPassword);

        // checks if the inputted password didn't pass requirementCheck
        if ($passwordRequirementCheck !== true) {
            // returns error alert
            $this->alert['error'] = $passwordRequirementCheck;
            return false;
        }

        // checks to see if inputted new password and inputted reEntered new password match
        if ($inputtedNewPassword != $inputtedreEnteredNewPassword) {
            // returns error alert
            $this->alert['error'] = 'New password and re-entered new password don\'t match, please make sure they match and try again';
            return false;
        }

        // checks if password doesn't match the password on the users account
        if (!password_verify($inputtedPassword, $password)) {
            // returns error alert
            $this->alert['error'] = 'Password doesn\'t match our records, please try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // update users password to inputted new password and sends email to user's email to notify them that their password has been changed
    public function updatePasswordAndSendNotifyEmail($userId, $email, $inputtedNewPassword, $type) {
        // datastore connection
        $datastore = new DatastoreClient();

        // Mail gun connection
        $mg = new Mailgun(GlobalVariables::$mailgun_apikey);

        // sets $typeName so we can search datastore key with type name from the type number given
        switch ($type) {
            case 0:
                $typeName = 'AdminsAndUsers';
                break;
            case 1:
                $typeName = 'AdminsAndUsers';
                break;
            case 2:
                $typeName = 'Ours';
                break;
        }

        // commits newPassword to user account
        // creates transaction to update user
        $transaction = $datastore->transaction();
        // creates key to search for users account
        $key = $datastore->key($typeName, $userId);
        // creates lookup to find user's account from key
        $user = $transaction->lookup($key);
        // changes the users current password to newPassword
        $user['password'] = password_hash($inputtedNewPassword, PASSWORD_ARGON2I);
        // inserts the change to datastore of user
        $transaction->upsert($user);
        // pushes the changes to the datastore
        $result = $transaction->commit();

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'PasswordChange::updatePasswordAndSendNotifyEmail', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // sends email to notify user that their password has been changed
        $mg->sendMessage('titanlocks.co', array(
            'from'=>'TitanLocks <noreply@titanlocks.co>',
            'to'=> $email,
            'subject' => 'Revision to your TitanLocks.co Account',
            'text' => 'Thanks for visiting TitanLocks.co! Per your request, Your password has been changed.

If this was not you, please contact TitanLocks support ASAP! Thank you again for using TitanLocks.'
        ));

        // function completed returns true
        return true;
    }

}