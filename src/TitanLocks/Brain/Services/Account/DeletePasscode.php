<?php

namespace TitanLocks\Brain\Services\Account;

use TitanLocks\Brain\Services\Account\RequirementCheck;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class DeletePasscode
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
     * DeletePasscode constructor.
     * @param $adminId
     * @param $userId
     * @param $userJobTitle
     * @param $passcodeId
     * @param $ttlUsername
     * @param $ttlPassword
     */
    public function __construct($adminId, $userId, $userJobTitle, $passcodeId, $ttlUsername, $ttlPassword)
    {
        // authentication check the user to make sure they have access to the create locations feature and that they are under a valid admin Id
        if ($this->checkCreateAUserFeatureAccessAuthentication($adminId, $userId, $userJobTitle) == false) {
            return;
        }

        // upload job to 'JobTitles'
        if ($this->deletePasscode($passcodeId, $ttlUsername, $ttlPassword) == false) {
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

        if ($usersFeatures[11] !== true) {
            $this->alert['error'] = 'You don\'t have access to this feature, please contact your admin if you are suppose to';
            return false;
        }

        // function completed returns true
        return true;
    }

    // upload job to 'JobTitles'
    public function deletePasscode($passcodeId, $ttlUsername, $ttlPassword) {
        $passcodeAndLockId = explode(",", $passcodeId);
        $passcodeId = $passcodeAndLockId[0];
        $lockId = $passcodeAndLockId[1];

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
            $this->alert['error'] = 'Error connection issue, please refresh and try again';
            return false;
        }

        $accessToken = json_decode($accessTokenResponse->getBody()->getContents())->access_token;

        $mt = explode(' ', microtime());

        $deletePasscode = array(
            'headers'   =>
                array(
                    'Accept' => 'application/json',
                ),
            'form_params' =>
                array (
                    'clientId' => 'c54850a0d7f146288d18dc773d9846f6',
                    'accessToken' => $accessToken,
                    'lockId' => $lockId,
                    'keyboardPwdId' => $passcodeId,
                    'deleteType' => '2',
                    'date' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000))
                ),
        );
        $deletePasscodeURL = 'https://api.sciener.cn/v3/keyboardPwd/delete';

        try {
            $client->request('POST', $deletePasscodeURL, $deletePasscode);
        } catch (RequestException $e) {
            $this->alert['error'] = 'Error connection issue loading, please refresh and try again';
            return false;
        }

        // function completed returns true
        return true;
    }

}