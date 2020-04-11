<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CreateMaintenance
{
    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public $success = false;

    public function __construct($adminId, $userId, $userJobTitle, $description, $urgency, $lockId, $ttlUsername, $ttlPassword)
    {
        // authentication check the user to make sure they have access to the create locations feature and that they are under a valid admin Id
        if ($this->checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $userJobTitle) == false) {
            return;
        }

        // create the location name into the data store
        if ($this->requirementCheckLocationName($description, $urgency, $lockId) == false) {
            return;
        }


        // create the location name into the data store
        if ($this->createMaintenance($adminId, $description, $urgency, $lockId, $ttlUsername, $ttlPassword) == false) {
            return;
        }

        $this->success = true;
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


        // function completed returns true
        return true;
    }

    // requirement check location name and make sure a location name isn't already created under that name
    public function requirementCheckLocationName($description, $urgency, $lockId) {
        if ($urgency != 'earliestConvenience' && $urgency != 'asap' && $urgency != 'urgent') {
            // returns error alert
            $this->alert['error'] = 'Urgency type issue, please refresh and try again';
            return false;
        }

        // checks if password is under 6 characters long
        if ( strlen($description) == 0 ) {
            return 'Please enter a valid description';
        }

        // checks if password is over 128 characters long
        if ( strlen($description) > 244 ) {
            return 'description must be a maximum of 244 characters, please enter a maximum of 244 characters and try again';
        }

        if (!preg_match('/^[a-z0-9 .\-]+$/i', $description)) {
            return 'Description can only contain letters, numbers, spaces, dashes, and periods';
        }

        if (!is_numeric($lockId)) {
            $this->alert['error'] = 'Invalid lock id, please refresh and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

    // create the location name into the data store
    public function createMaintenance($adminId, $description, $urgency, $lockId, $ttlUsername, $ttlPassword) {
        // datastore connection
        $datastore = new DatastoreClient();

        $accessTokenData = array(
            'headers'   =>
                array(
                    'Accept' => 'application/json',
                ),
            'form_params' =>
                array (
                    'client_id' => 'c54850a0d7f146288d18dc773d9846f6',
                    'client_secret' => '83051eea763c98bb268c322c05e2ba17',
                    'grant_type' => 'password',
                    'username' => $ttlUsername,
                    'password' => md5($ttlPassword),
                    'redirect_uri' => 'http://titanlocks.co'),
        );
        $tokenUrl = 'https://api.sciener.cn/oauth2/token';

        $client = new Client();

        try {
            $accessTokenResponse = $client->request('POST', $tokenUrl, $accessTokenData);
        } catch (RequestException $e) {
            $this->alert['error'] = 'Error connection issue loading locks, please refresh and try again';
            return false;
        }

        $mt = explode(' ', microtime());

        $lockListData = array(
            'headers'   =>
                array(
                    'Accept' => 'application/json',
                ),
            'form_params' =>
                array (
                    'clientId' => 'c54850a0d7f146288d18dc773d9846f6',
                    'accessToken' => json_decode($accessTokenResponse->getBody()->getContents())->access_token,
                    'lockId' => $lockId,
                    'keyboardPwdVersion' => 4,
                    'keyboardPwdType' => 3,
                    'startDate' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000)),
                    'endDate' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000)) + 86400000,
                    'date' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000))
                )
        );
        $lockListUrl = 'https://api.sciener.cn/v3/keyboardPwd/get';

        try {
            $generateLockInfo = $client->request('POST', $lockListUrl, $lockListData);
        } catch (RequestException $e) {
            $this->alert['error'] = 'Error connection issue loading locks, please refresh and try again';
            return false;
        }

        // email verification info to enter info database
        $userInformation = [
            'description' => $description,
            'urgency' => $urgency,
            'lockId' => $lockId,
            'passcode' => json_decode($generateLockInfo->getBody()->getContents())->keyboardPwd,
            'user' => '',
            'status' => 'incomplete',
            'created' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000)),
            'adminUserId' => $adminId
        ];

        $keysWithAllocatedIds = $datastore->allocateIds([$datastore->key('Maintenance')]);

        // create entity for datastore
        $emailVerification = $datastore->entity($keysWithAllocatedIds[0], $userInformation);

        // insert entity to datastore
        // checks if insert had any errors, if so then send error and return false
        $result = $datastore->insert($emailVerification);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'CreateUser::createUser', 'datastore');
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