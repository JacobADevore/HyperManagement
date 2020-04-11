<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;

class EditLocations
{

    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public $success = false;

    public $datastoreLocationId;

    /**
     * EditLocations constructor.
     * @param $adminId
     * @param $userId
     * @param $userJobTitle
     * @param $oldLocationName
     * @param $newLocationName
     */
    public function __construct($adminId, $userId, $userJobTitle, $oldLocationName, $newLocationName)
    {
        // authentication check the user to make sure they have access to the create locations feature and that they are under a valid admin Id
        if ($this->checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $userJobTitle) == false) {
            return;
        }

        // check to make sure name meets requirements and there isn't already a name of that job title link to the account ( admin usersID ) and check to make sure all features true or false
        if ($this->requirementCheckLocationName($adminId, $oldLocationName, $newLocationName) == false) {
            return;
        }

        // upload job to 'JobTitles'
        if ($this->updatesLocation($newLocationName) == false) {
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

        if ($usersFeatures[12] !== true) {
            $this->alert['error'] = 'You don\'t have access to this feature, please contact your admin if you are suppose to';
            return false;
        }

        // function completed returns true
        return true;
    }

    // requirement check location name and make sure a location name isn't already created under that name
    public function requirementCheckLocationName($adminId, $oldLocationName, $newLocationName) {
        if ($oldLocationName == $newLocationName) {
            $this->alert['error'] = 'Location name is the same, please change the location name before clicking Edit';
            return false;
        }

        // requirement check password
        $passwordRequirementCheck = RequirementCheck::location($newLocationName);

        // checks if the inputted password didn't pass requirementCheck
        if ($passwordRequirementCheck !== true) {
            // returns error alert
            $this->alert['error'] = $passwordRequirementCheck;
            return false;
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('Locations')
            ->filter('adminUserId', '=', $adminId)
            ->filter('name', '=', $oldLocationName)
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
            $this->datastoreLocationId = $entity->key()->path()[0]['id'];
            $count++;
        }

        // checks if results amount is greater than 25 requests in the past 6 hours ( 1 array default given in array )
        if ($count == 0) {
            // returns error alert
            $this->alert['error'] = 'Invalid location name, can\'t find '.$oldLocationName.' in our records for your account. please go back and try clicking edit again';
            return false;
        }

        $query = $datastore->query()
            ->kind('Locations')
            ->filter('adminUserId', '=', $adminId)
            ->filter('name', '=', $newLocationName)
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
            $this->alert['error'] = 'There is a Location name already using that name, please try to use a differnt name and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // updates the location name
    public function updatesLocation($newLocationName) {
        // datastore connection
        $datastore = new DatastoreClient();

        // commits newPassword to user account
        // creates transaction to update user
        $transaction = $datastore->transaction();
        // creates key to search for users account
        $key = $datastore->key('Locations', $this->datastoreLocationId);
        // creates lookup to find user's account from key
        $user = $transaction->lookup($key);
        // changes the users current password to newPassword
        $user['name'] = $newLocationName;
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