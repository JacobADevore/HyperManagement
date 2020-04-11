<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\Functions\Secrets;
use TitanLocks\Brain\GlobalVariables;
use Mailgun\Mailgun;

/**
 * Class EmailChange
 * @package Hymont\Brain\Services\Account
 *
 * Checks to see that new email and reEntered new email are the same
 * Checks to make sure that password user entered and database hashed password are the same
 * Creates the email Verification inside datastore with info - Sends the email for verification to new email - Sends notification email to user's old email
 */
class EmailChange
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
     * @param $userId - user's ID for user changing their email
     * @param $oldEmail - user's current email before the change
     * @param $datastorePassword - user's hashed password from database
     * @param $newEmail - user's new email they are trying to change to
     * @param $reEnteredNewEmail - user's new email reEntered they are trying to change to
     * @param $password - user's password that they entered to confirm the email change ( must match the database hashed password )
     * @param $type - user's type of their account so we know which kind to search for
     * @param $twig - twig environment so that we can send rendered template emails
     * @return bool - ( $this->success ) true = Email change process was a success, false = was not
     * Handles the full EmailChange process, if any function has error then stops the EmailChange process and returns the error in $alert array. we can catch this by seeing if $success is true or not
     * Every function returns false if error which can be found in $alert and true if function was completed
     */
    public function __construct($userId, $oldEmail, $datastorePassword, $newEmail, $reEnteredNewEmail, $password, $type)
    {
        // checks if new email and reEntered email match and if not then stop email change and return with the error
        if ($this->newEmailAndreEnteredNewEmailCheck($newEmail, $reEnteredNewEmail) == false) {
            return;
        }
        // checks if password given matches the users current password and if not then stop email change and return with error
        if ($this->passwordCheck($password, $datastorePassword) == false) {
            return;
        }
        // checks if new email meets our email requirements and if not then stop email change and return with the error
        if ($this->newEmailRequirementCheck($newEmail, $type) == false) {
            return;
        }
        // checks if user has sent out the max 25 email change requests in the last 6 hours, if so stop the email change and return with error
        if ($this->maximumEmailChangeRequestCheck($userId, $type) == false) {
            return;
        }

        // creates the datastore entity for the email change for the user and send Emails out to old and new emails and if can't create for some reason stop email change and return with error
        if ($this->emailVerificationDatastoreCreateAndSendEmails($userId, $oldEmail, $newEmail, $type) == false) {
            return;
        }

        // EmailChange has successfully ran through all functions with all success
        // changes success to true so we can know that EmailChange passed successfully
        return $this->success = true;
    }

    // checks if new email and reEntered email match and if not then stop email change and return with the error ( should be called before any function touching database )
    public function newEmailAndreEnteredNewEmailCheck($newEmail, $reEnteredNewEmail) {
        // checks if the new email and reEntered new email are not the same
        if ($newEmail != $reEnteredNewEmail) {
            // returns error alert
            $this->alert['error'] = 'New email and reEntered new email don\'t match';
            return false;
        }
        // function completed returns true
        return true;
    }

    // checks if password matches password from users database
    public function passwordCheck($password, $datastorePassword) {

        // checks if password doesn't match the password on the users account
        if (!password_verify($password, $datastorePassword)) {
            // returns error alert
            $this->alert['error'] = 'Current password doesn\'t match our records, please try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // checks if new email meets our email requirements and if not then stop email change and return with the error
    public function newEmailRequirementCheck($newEmail, $type) {
        // requirement check email before running email verification on it
        $newEmailRequirementCheck = RequirementCheck::email($newEmail, $type);

        // checks if the new email didn't pass requirementCheck
        if ( $newEmailRequirementCheck != true ) {
            // returns error alert
            $this->alert['error'] = $newEmailRequirementCheck;
            return false;
        }
        // function completed returns true
        return true;
    }

    // checks if user has sent out the max 25 email change requests in the last 6 hours, if so stop the email change and return with error
    public function maximumEmailChangeRequestCheck($userId, $type) {
        // datastore connection
        $datastore = new DatastoreClient();

        // checks what type the user is so we know which parent kind to search for, $type must be 0, 1, or 2
        if ($type != 2) {
            $query = $datastore->query()
                ->kind('EmailVerification')
                ->hasAncestor($datastore->key('AdminsAndUsers', $userId))
                ->filter('timeStamp', '>', (strtotime("now") - 21600))
                ->limit(25);
        } else {
            $query = $datastore->query()
                ->kind('EmailVerification')
                ->hasAncestor($datastore->key('Ours', $userId))
                ->filter('timeStamp', '>', (strtotime("now") - 21600))
                ->limit(25);
        }

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'EmailChange::maximumEmailChangeRequestCheck', 'datastore');
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
            $this->alert['error'] = 'You may only send 25 email change requests every 6 hours';
            return false;
        }

        // function completed returns true
        return true;
    }

    // creates the datastore entity for the email change for the user and send Emails out to old and new emails and if can't create for some reason stop email change and return with error
    public function emailVerificationDatastoreCreateAndSendEmails($userId, $oldEmail, $newEmail, $type) {
        // datastore connection
        $datastore = new DatastoreClient();

        // Mail gun connection
        $mg = new Mailgun(GlobalVariables::$mailgun_apikey);

        // creates the secret for the emailVerification ( uses only letters and numbers because its sent over url and url cant hold some symbols )
        $secret = Secrets::secretGeneratorOnlyLettersAndNumbers(16, 32);

        // email verification info to enter info database
        $emailVerificationInfo = [
            'oldEmail' => $oldEmail,
            'newEmail' => $newEmail,
            'secret' => $secret,
            'timeStamp' => strtotime("now"),
            'completed' => 0
        ];

        // gets an auto generated key id we can use for email Verification
        // uses different type to know how to search for the parent keys
        if ($type != 2) {
            $keysWithAllocatedIds = $datastore->allocateIds([$datastore->key('AdminsAndUsers', $userId)->pathElement('EmailVerification')]);
        } else {
            $keysWithAllocatedIds = $datastore->allocateIds([$datastore->key('Ours', $userId)->pathElement('EmailVerification')]);
        }

        // create entity for datastore
        $emailVerification = $datastore->entity($keysWithAllocatedIds[0], $emailVerificationInfo);

        // insert entity to datastore
        // checks if insert had any errors, if so then send error and return false
        $result = $datastore->insert($emailVerification);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'EmailChange::emailVerificationDatastoreCreateAndSendEmails', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // sends emailChange confirmation email to the users newEmail given in email change process
        if ($type == 0) {
            $mg->sendMessage('titanlocks.co', array(
                'from'=>'TitanLocks <noreply@titanlocks.co>',
                'to'=> $newEmail,
                'subject' => 'Email change request for your TitanLocks.co Account',
                'text' => 'Thanks for visiting TitanLocks.co! Per your request, to change your email from '.$oldEmail.' to '.$newEmail.' please click the link below.

Please click the link http://localhost/web/app_dev.php/dashboard/change/email?emailVerificationId='.$keysWithAllocatedIds[0]->path()[1]['id'].'&secret='.$secret.' to complete your email change process. Thank you again for using TitanLocks.'
            ));
        } else if ($type == 1) {
            $mg->sendMessage('titanlocks.co', array(
                'from'=>'TitanLocks <noreply@titanlocks.co>',
                'to'=> $newEmail,
                'subject' => 'Email change request for your TitanLocks.co Account',
                'text' => 'Thanks for visiting TitanLocks.co! Per your request, to change your email from '.$oldEmail.' to '.$newEmail.' please click the link below.

Please click the link http://localhost/web/app_dev.php/admin/dashboard/change/email?emailVerificationId='.$keysWithAllocatedIds[0]->path()[1]['id'].'&secret='.$secret.' to complete your email change process. Thank you again for using TitanLocks.'
            ));
        } else if ($type == 2) {
            $mg->sendMessage('titanlocks.co', array(
                'from'=>'TitanLocks <noreply@titanlocks.co>',
                'to'=> $newEmail,
                'subject' => 'Email change request for your TitanLocks.co Account',
                'text' => 'Thanks for visiting TitanLocks.co! Per your request, to change your email from '.$oldEmail.' to '.$newEmail.' please click the link below.

Please click the link http://localhost/web/app_dev.php/our/dashboard/change/email?emailVerificationId='.$keysWithAllocatedIds[0]->path()[1]['id'].'&secret='.$secret.' to complete your email change process. Thank you again for using TitanLocks.'
            ));
        }

        // function completed returns true
        return true;
    }

}