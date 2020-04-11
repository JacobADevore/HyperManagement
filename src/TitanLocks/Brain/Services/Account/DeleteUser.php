<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;

class DeleteUser
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
     * CreateJobTitle constructor.
     * @param $userId - holds the userId of the admin account
     * @param $jobTitleName
     */
    public function __construct($adminId, $userId, $deleteUserEmail, $userJobTitle)
    {
        // get delete user id from email
        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('email', '=', $deleteUserEmail)
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
            $deleteUserId = $entity->key()->path()[0]['id'];
            $count++;
        }

        // check to make sure name meets requirements and there isn't already a name of that job title link to the account ( admin usersID ) and check to make sure all features true or false
        if ($this->checkDeleteUserId($adminId, $deleteUserId) == false) {
            return;
        }

        // check to make sure that userId is a valid account with type 1
        if ($this->checkAccess($adminId, $userId, $userJobTitle) == false) {
            return;
        }

        // upload job to 'JobTitles'
        if ($this->deleteJobTitle($deleteUserId) == false) {
            return;
        }

        // CreateJobTitle has successfully ran through all functions with all success
        // changes success to true so we can know that CreateJobTitle passed successfully
        return $this->success = true;
    }

    // check to make sure name meets requirements and there isn't already a name of that job title link to the account ( admin usersID ) and check to make sure all features true or false
    public function checkDeleteUserId($adminId, $deleteUserId) {

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('__key__', '=', $datastore->key('AdminsAndUsers', $deleteUserId))
            ->filter('adminUserId', '=', $adminId)
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
            $count++;
        }

        // checks if results amount is greater than 25 requests in the past 6 hours ( 1 array default given in array )
        if ($count == 0) {
            // returns error alert
            $this->alert['error'] = 'User your trying to delete was not found, please refresh and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // check to make sure that userId is a valid account with type 1
    public function checkAccess($adminId, $userId, $userJobTitle) {
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
        if ($count != 0) {
            // returns error alert
            $this->alert['error'] = 'We are having trouble verifying your job title, please contact your admin';
            return false;
        }

        if ($usersFeatures[2] !== true) {
            $this->alert['error'] = 'You don\'t have access to this feature, please contact your admin if you are suppose to';
            return false;
        }

        // function completed returns true
        return true;
    }

    // upload job to 'JobTitles'
    public function deleteJobTitle($deleteUserId) {
        // datastore connection
        $datastore = new DatastoreClient();

        $result = $datastore->delete($datastore->key('AdminsAndUsers', $deleteUserId));

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