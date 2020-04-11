<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class EditEkey
{
    // holds the alerts text, every page checks for alerts when visiting them
    public $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public $success = false;

    public function __construct($adminId, $userId, $userJobTitle, $eKeyId, $editStartTime, $editEndTime, $ttlUsername, $ttlPassword)
    {
        // authentication check the user to make sure they have access to the create locations feature and that they are under a valid admin Id
        if ($this->checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $userJobTitle) == false) {
            return;
        }

        // requirement check location name and make sure a location name isn't already created under that name
        if ($this->requirementCheckInfo($editStartTime, $editEndTime) == false) {
            return;
        }

        // create the location name into the data store
        if ($this->editEkey($eKeyId, $editStartTime, $editEndTime, $ttlUsername, $ttlPassword) == false) {
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

        if ($usersFeatures[7] !== true) {
            $this->alert['error'] = 'You don\'t have access to this feature, please contact your admin if you are suppose to';
            return false;
        }

        // function completed returns true
        return true;
    }

    // requirement check location name and make sure a location name isn't already created under that name
    public function requirementCheckInfo($editStartTime, $editEndTime) {

        if (round(microtime(true) * 1000) > (strtotime($editStartTime) * 1000) && (strtotime($editStartTime) * 1000) > (strtotime($editEndTime) * 1000)) {
            // returns error alert
            $this->alert['error'] = 'Start time or End time can\'t be in the past and start time and end time can\'t be set individually ( if one is set the other must be too ) as well as End time must be equal to or greater than the Start time';
            return false;
        }

        // function completed returns true
        return true;
    }

    // create the location name into the data store
    public function editEkey($eKeyId, $editStartTime, $editEndTime, $ttlUsername, $ttlPassword) {

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
                        'keyId' => $eKeyId,
                        'startDate' => strtotime($editStartTime) * 1000,
                        'endDate' => strtotime($editEndTime) * 1000,
                        'date' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000))
                    )
            );

        $lockListUrl = 'https://api.sciener.cn/v3/key/changePeriod';

        try {
            $client->request('POST', $lockListUrl, $lockListData);
        } catch (RequestException $e) {
            $this->alert['error'] = 'Error connection issue loading locks, please refresh and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

}