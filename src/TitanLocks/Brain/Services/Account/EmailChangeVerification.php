<?php

namespace TitanLocks\Brain\Services\Account;

use Mailgun\Mailgun;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\GlobalVariables;

/**
 * Class EmailChangeVerification
 * @package TitanLocks\Brain\Services\Account
 *
 * Takes in email verification ID, email verification secret, user Id from authentication, users type, twig container
 * Grabs email verification information from email verification ID given in $_GET ( URL )
 * Checks $_GET ( URL ) information with information grabbed from emailVerificationGrabInformationFromID ( step before )
 * Changes the users email to newEmail and emails oldEmail to notify them that their account email has been changed to newEmail
 */
class EmailChangeVerification
{
    // holds the alert text, profile page checks that the text has 1 or more characters and displays that alert
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChangeVerification was a success, false = not success check alert text
    public $success = false;

    // sets in emailVerificationGrabInformationFromID
    // all user data from datastore
    public $datastoreSecret;
    public $datastoreAccId;
    public $datastoreTimestamp;
    public $datastoreOldEmail;
    public $datastoreNewEmail;
    public $datastoreCompleted;

    /**
     * EmailChangeVerification constructor.
     * @param $emailVerificaitonId - email verification id
     * @param $secret - secret of email verification
     * @param $authenticationId - user's Id gathered from authentication class
     * @param $type - type of the users account
     *
     * Handles the full EmailChangeVerification process, if any function has error then stops the EmailChangeVerification process and returns the error in $alert array. we can catch this by seeing if $success is true or not
     * Every function returns false if error which can be found in $alert and true if function was completed
     */
    public function __construct($emailVerificaitonId, $secret, $authenticationId, $type)
    {
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

        // grabs the email verification information from the given email verification ID
        if ($this->emailVerificationGrabInformationFromID($emailVerificaitonId, $authenticationId, $typeName) == false) {
            return;
        }

        // checks the $get information ( URL information ) with the information obtained from emailVerificationGrabInformationFromID
        if ($this->checkInformation($secret, $type) == false) {
            return;
        }

        // changes the users email to the newEmail and sends email to oldEmail to let them know about the update to the account
        if ($this->changeEmail($authenticationId, $typeName) == false) {
            return;
        }

        // EmailChangeVerification has successfully ran through all functions with all success
        // changes success to true so we can know that EmailChangeVerification passed successfully
        $this->success = true;
    }

    // grabs the email verification information from the given email verification ID
    public function emailVerificationGrabInformationFromID($emailVerificationId, $authenticationId, $typeName)
    {
        // datastore connection
        $datastore = new DatastoreClient();

        // creates the key that needs to be search for when getting email verification information from email verification ID
        $this->emailVerificationKey = $datastore->key($typeName, $authenticationId)->pathElement('EmailVerification', $emailVerificationId);

        // query to grab email verification information from email verification ID
        $query = $datastore->query()
            ->kind('EmailVerification')
            ->filter('__key__', '=', $this->emailVerificationKey)
            ->limit(1);

        // run query to get information from query
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'EmailChangeVerification::emailVerificationGrabInformationFromID', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // runs foreach on query results to get email verification information from ID
        foreach ($result as $info) {
            $this->datastoreSecret = $info['secret'];
            $this->datastoreTimestamp = $info['timeStamp'];
            $this->datastoreOldEmail = $info['oldEmail'];
            $this->datastoreNewEmail = $info['newEmail'];
            $this->datastoreCompleted = $info['completed'];
        }

        // checks if there isn't exactly one result from email verification ID query
        if (strlen($this->datastoreSecret) == 0) {
            // returns error alert
            $this->alert['error'] = 'Invalid email verification, please check your email and try again';
            return false;
        }

        // checks if the email verification is already completed or not
        if ($this->datastoreCompleted == 1) {
            // returns error alert
            $this->alert['error'] = 'Invalid email verification, the email verification you are trying to verify has already been completed';
            return false;
        }

        // function completed returns true
        return true;
    }

    // checks the $get information ( URL information ) with the information obtained from emailVerificationGrabInformationFromID
    public function checkInformation($secret, $type) {
        // checks secret given with datastore secret
        if ($this->datastoreSecret != $secret) {
            // returns error alert
            $this->alert['error'] = 'Invalid email verification, please check your email and try again';
            return false;
        }

        // checks if the email verification has expired ( 24 hour expiration )
        if ($this->datastoreTimestamp < (strtotime("now") - 86400)) {
            // returns error alert
            $this->alert['error'] = 'Email verification has expired. Please retry email verification';
            return false;
        }

        // checks that the email is still available to change to
        $newEmailRequirementCheck = RequirementCheck::email($this->datastoreNewEmail, $type);

        // checks if the new email didn't pass requirementCheck
        if ( $newEmailRequirementCheck != true ) {
            // returns error alert
            $this->alert['error'] = $newEmailRequirementCheck;
            return false;
        }

        // function completed returns true
        return true;
    }

    // changes the users email to the newEmail and sends email to oldEmail to let them know about the update to the account
    public function changeEmail($authenticationId, $typeName) {
        // datastore connection
        $datastore = new DatastoreClient();

        // Mail gun connection
        $mg = new Mailgun(GlobalVariables::$mailgun_apikey);

        // commits newEmail to user account
        // creates transaction to update both user and email verification
        $transaction = $datastore->transaction();
        // creates key to search for users account
        $key = $datastore->key($typeName, $authenticationId);
        // creates lookup to find user's account from key
        $user = $transaction->lookup($key);
        // changes the users current email to the newEmail
        $user['email'] = $this->datastoreNewEmail;
        // inserts the change to datastore of user
        $transaction->upsert($user);
        // updates email verification used to completed (1)
        // creates lookup to find user's email verification request used to verify the user's email
        $emailVerification = $transaction->lookup($this->emailVerificationKey);
        // changes the email verification used in the change to completed 1 ( 1 means true )
        $emailVerification['completed'] = 1;
        // inserts the change to datastore of email verification
        $transaction->upsert($emailVerification);
        // pushes the changes to the datastore
        $result = $transaction->commit();

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'EmailChangeVerification::changeEmail', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // sends email to oldEmail to notify them that their account email has been changed
        // sends update to users old email that a emailChange request has been sent to the newEmail given for the users account
        $mg->sendMessage('titanlocks.co', array(
            'from'=>'TitanLocks <noreply@titanlocks.co>',
            'to'=> $this->datastoreOldEmail,
            'subject' => 'Revision to your TitanLocks.co Account',
            'text' => 'Thanks for visiting TitanLocks.co! Per your request, Your email has been changed from '.$this->datastoreOldEmail.' to '.$this->datastoreNewEmail.'.

If this was not you, please contact TitanLocks support ASAP! Thank you again for using TitanLocks.'
        ));

        // function completed returns true
        return true;
    }

}