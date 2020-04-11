<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;

class EditJobTitle
{

    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public $success = false;

    public $datastoreJobTitleId;

    /**
     * CreateJobTitle constructor.
     * @param $userId - holds the userId of the admin account
     * @param $jobTitleName
     * @param $jobOldName
     */
    public function __construct($adminId, $userId, $userJobTitle, $jobOldName, $jobTitleName, $features)
    {
        // authentication check the user to make sure they have access to the create locations feature and that they are under a valid admin Id
        if ($this->checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $userJobTitle) == false) {
            return;
        }

        // check to make sure name meets requirements and there isn't already a name of that job title link to the account ( admin usersID ) and check to make sure all features true or false
        if ($this->checkJobTitleName($jobOldName, $userId) == false) {
            return;
        }

        // check to make sure that userId is a valid account with type 1
        if ($this->checkUserId($userId) == false) {
            return;
        }

        // upload job to 'JobTitles'
        if ($this->uploadJobTitle($userId, $jobTitleName, $features) == false) {
            return;
        }

        // CreateJobTitle has successfully ran through all functions with all success
        // changes success to true so we can know that CreateJobTitle passed successfully
        return $this->success = true;
    }

    // check to see if user has access to create a feature or if user is admin ( check if user is admin by if adminId and userId are the same )
    public function checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $userJobTitle) {
        if ($adminId == $userId) {
            // function completed returns true
            return true;
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('JobTitles')
            ->filter('adminUserId', '=', $adminId)
            ->filter('name', '=', $userJobTitle)
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

        if ($usersFeatures[13] !== true) {
            $this->alert['error'] = 'You don\'t have access to this feature, please contact your admin if you are suppose to';
            return false;
        }

        // function completed returns true
        return true;
    }

    // check to make sure name meets requirements and there isn't already a name of that job title link to the account ( admin usersID ) and check to make sure all features true or false
    public function checkJobTitleName($jobOldName, $userId) {
        // requirement check job title name
        $jobTitleRequirementCheck = RequirementCheck::jobTitleName($jobOldName);

        // checks if the job title name didn't pass requirementCheck
        if ($jobTitleRequirementCheck !== true) {
            // returns error alert
            $this->alert['error'] = $jobTitleRequirementCheck;
            return false;
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('JobTitles')
            ->filter('adminUserId', '=', $userId)
            ->filter('name', '=', $jobOldName)
            ->limit(1);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'CreateJobTitle::checkJobTitleName', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        $count = 0;
        foreach ($result as $entity) {
            $this->datastoreJobTitleId = $entity->key()->path()[0]['id'];
            $count++;
        }

        // checks if results amount is greater than 25 requests in the past 6 hours ( 1 array default given in array )
        if (!isset($this->datastoreJobTitleId)) {
            // returns error alert
            $this->alert['error'] = 'No job title found, please retry edit process';
            return false;
        }

        // function completed returns true
        return true;
    }

    // check to make sure that userId is a valid account with type 1
    public function checkUserId($userId) {
        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('__key__', '=', $datastore->key('AdminsAndUsers', $userId))
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
            $this->alert['error'] = 'Admin account access issue, please refresh and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // upload job to 'JobTitles'
    public function uploadJobTitle($userId, $jobTitleName, $features) {
        // datastore connection
        $datastore = new DatastoreClient();

        // commits newPassword to user account
        // creates transaction to update user
        $transaction = $datastore->transaction();
        // creates key to search for users account
        $key = $datastore->key('JobTitles', $this->datastoreJobTitleId);
        // creates lookup to find user's account from key
        $user = $transaction->lookup($key);
        // changes the users current password to newPassword
        $user['name'] = $jobTitleName;
        $user['features'] = $features;
        // inserts the change to datastore of user
        $transaction->upsert($user);
        // pushes the changes to the datastore
        $result = $transaction->commit();

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'EditJobTitle::uploadJobTitle', 'datastore');
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