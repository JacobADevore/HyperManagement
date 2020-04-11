<?php

namespace TitanLocks\Brain\Services\Account;

use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;

class DeleteMaintenance
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
     * CreateJobTitle constructor.
     * @param $userId - holds the userId of the admin account
     * @param $jobTitleName
     */
    public function __construct($adminId, $userId, $userJobTitle, $maintenanceId)
    {
        // authentication check the user to make sure they have access to the create locations feature and that they are under a valid admin Id
        if ($this->checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $userJobTitle) == false) {
            return;
        }

        // check to make sure name meets requirements and there isn't already a name of that job title link to the account ( admin usersID ) and check to make sure all features true or false
        if ($this->requirementCheck($maintenanceId) == false) {
            return;
        }

        // upload job to 'JobTitles'
        if ($this->deleteLocation($maintenanceId) == false) {
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

        if ($usersFeatures[14] !== true) {
            $this->alert['error'] = 'You don\'t have access to this feature, please contact your admin if you are suppose to';
            return false;
        }

        // function completed returns true
        return true;
    }


    // requirement check location name and make sure a location name isn't already created under that name
    public function requirementCheck($maintenanceId) {
        if (!is_numeric($maintenanceId)) {
            $this->alert['error'] = 'Invalid maintenance Id, please refresh and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // upload job to 'JobTitles'
    public function deleteLocation($maintenanceId) {
        // datastore connection
        $datastore = new DatastoreClient();

        $result = $datastore->delete($datastore->key('Maintenance', $maintenanceId));

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'DeleteJobTitle::deleteJobTitle', 'datastore');
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