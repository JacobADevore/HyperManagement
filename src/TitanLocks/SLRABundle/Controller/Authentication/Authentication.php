<?php

namespace TitanLocks\SLRABundle\Controller\Authentication;

use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\Services\Security\Cryption;
use TitanLocks\Brain\Functions\AuthenticationDashboardRedirection;


/**
 * Class Authentication
 * @package TitanLocks\SLRABundle\Controller\Authentication
 *
 * with Type of user given, Authentication class will authenticate the users session or cookie information for user login
 * and if authentication is complete then $success will = true, if not then $success = false
 *
 * if  $type = 3 then we don't know the user's type and we still have to authenticate for the user and return the user's type as well
 */
class Authentication
{
    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array();

    // true = Authentication was a success, false = not success check alert text
    public $success = false;

    // holds all user information when authentication was a success so we don't have to make another datastore connection for it
    public $authenticationInformation = array();

    // sets the route of the dashboard the user should go to dependent on the user's type
    public $dashboard;

    /**
     * Authentication constructor.
     * @param $type - users type of their account ( can only be 1 2 or 3 )
     */
    public function __construct($type)
    {
        // checks if a session has been started and if it has then don't start another
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // checks session first for login information - if set then run authenticate function with that information
        if ( isset($_SESSION['LID']) && isset($_SESSION['Li'])) {
            $this->authenticate($type, $_SESSION['LID'], $_SESSION['Li']);
        }

        // if session couldn't authenticate the user then try with cookie information
        if ( isset($_COOKIE['LID']) && isset($_COOKIE['Li'])) {
            $this->authenticate($type, $_COOKIE['LID'], $_COOKIE['Li']);
        }
    }

    public function authenticate($type, $LID, $Li) {
        // datastore connection
        $datastore = new DatastoreClient();

        // checks what type the user is so we know which parent kind to search for, $type must be 0, 1, or 2
            $query = $datastore->query()
                ->kind('AdminsAndUsers')
                ->filter('__key__', '=', $datastore->key('AdminsAndUsers', $LID))
                ->limit(1);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'Authentication::authenticate', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            $this->alert['error'] = $datastoreErrorHandling;
            return false;
        }

        $count = 0;
        // sets information from datastore of user gotten from users email or username
        foreach ($result as $entity) {
            $datastoreEmail = $entity['email'];
            $datastoreSecret = $entity['secret'];
            $datastoreType = $entity['type'];
            $datastorePassword = $entity['password'];
            $datastoreJobTitle = $entity['jobTitle'];
            $datastoreAdminUserId = $entity['adminUserId'];
            $datastoreTTLUsername = $entity['ttlusername'];
            $datastoreTTLPassword = $entity['ttlpassword'];
            $count++;
        }

        if ($type == 3 && $count == 0) {
            $query = $datastore->query()
                ->kind('Ours')
                ->filter('__key__', '=', $datastore->key('Ours', $LID))
                ->limit(1);

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            // sends the datastore result to datastore ErrorHandling
            $datastoreErrorHandling = Datastore::ErrorHandling($result, 'Authentication::authenticate', 'datastore');
            // checks if datastore error handling caught any errors
            if ($datastoreErrorHandling != false) {
                // returns error alert
                $this->alert['error'] = $datastoreErrorHandling;
                return false;
            }

            // sets information from datastore of user gotten from users email or username
            foreach ($result as $entity) {
                $datastoreEmail = $entity['email'];
                $datastoreSecret = $entity['secret'];
                $datastoreType = $entity['type'];
                $datastorePassword = $entity['password'];
                $datastoreJobTitle = $entity['jobTitle'];
                $datastoreTTLUsername = $entity['ttlusername'];
                $datastoreTTLPassword = $entity['ttlpassword'];
                $count++;
            }
        }

        // have a type system stored in datastore,
        // 0 = agents, 1 = client, 2 = brokerage, 3 = mls, 4 = ours, 5 = demo

        // checks if there is no information to be gathered from query ( LID is wrong )
        if ($count == 0) {
            // returns error alert
            $this->alert['error'] = 'Authentication issue, please login again';
            return false;
        }

        if ($datastoreType != 1 && $datastoreType != 2) {
            $query = $datastore->query()
                ->kind('JobTitles')
                ->filter('adminUserId', '=', $datastoreAdminUserId)
                ->filter('name', '=', $datastoreJobTitle);

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            // sends the datastore result to datastore ErrorHandling
            $datastoreErrorHandling = Datastore::ErrorHandling($result, 'GetJobFeaturesFromJobTitle::getJobFeaturesFromJobTitle', 'datastore');
            // checks if datastore error handling caught any errors
            if ($datastoreErrorHandling != false) {
                // returns error alert
                $this->alert['error'] = $datastoreErrorHandling;
                return false;
            }

            // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
            foreach ($result as $entity) {
                $datastoreJobFeatures = $entity['features'];
            }
        }

        // checks if the users Li and account secret are the same
        if (Cryption::hashingVerifying($datastoreSecret, $Li)) {

            // sets users profile picture url header response to variable so we can check if they have one or not
            $profilePictureHeader = @get_headers("https://storage.googleapis.com/hymontprofilepictures/".$LID);

            // checks if user has a profile picture
            if (!$profilePictureHeader || $profilePictureHeader != 'HTTP/1.1 403 Not Found') {
                $profilePicture = "https://storage.googleapis.com/hymontprofilepictures/".$LID;
            } else {
                $profilePicture = false;
            }

            // sets the user dashboard reDirection route
            $this->dashboard = AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($datastoreType);

            if ($datastoreType != 1 && $datastoreType != 2) {
                // sets all user information
                $this->authenticationInformation = array(
                    'userId' => $LID,
                    'email' => $datastoreEmail,
                    'password' => $datastorePassword,
                    'type' => $datastoreType,
                    'jobTitle' => $datastoreJobTitle,
                    'adminUserId' => $datastoreAdminUserId,
                    'jobFeatures' => $datastoreJobFeatures,
                    'ttlUsername' => $datastoreTTLUsername,
                    'ttlPassword' => $datastoreTTLPassword
                );
            } else {
                // sets all user information
                $this->authenticationInformation = array(
                    'userId' => $LID,
                    'email' => $datastoreEmail,
                    'password' => $datastorePassword,
                    'type' => $datastoreType,
                    'jobTitle' => $datastoreJobTitle,
                    'adminUserId' => $datastoreAdminUserId,
                    'ttlUsername' => $datastoreTTLUsername,
                    'ttlPassword' => $datastoreTTLPassword
                );
            }

            // authentication was a success
            $this->success = true;
            return true;
        }

        // authentication was a failure
        $this->alert['error'] = 'Authentication issue, please login again';
        return false;
    }

}