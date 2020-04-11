<?php

namespace TitanLocks\SLRABundle\Controller\Login;

use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\Services\Security\Cryption;
use TitanLocks\Brain\Functions\AuthenticationDashboardRedirection;


/**
 * Class Login
 * @package Hymont\SLRABundle\Controller\Login
 *
 * takes in login inputs and type of user and checks inputted information with datastore information and if
 * $success = true then login inputted information was correct and if not then login inputted information was not correct
 */
class Login
{

    // holds the alerts text, every page checks for alerts when visiting them
    public static $alert = array(
        'error' => '',
        'warning' => '',
        'success' => ''
    );

    // true = EmailChange was a success, false = not success check alert text
    public static $success = false;

    // sets the route of the dashboard the user should go to dependent on the user's type
    public $dashboard;

    /**
     * Login constructor.
     * @param $type - type of the user's account
     * @param $emailOrUsername - email or user dependent on the users type
     * @param $password - password the user entered to login
     * @param $rememberMe - boolean if user has remember me on or not
     */
    public function __construct($type, $emailOrUsername, $password, $rememberMe)
    {
        // runs login function with information given in constructor
        $this->login($type, $emailOrUsername, $password, $rememberMe);
    }

    public function login($type, $emailOrUsername, $password, $rememberMe)
    {
        // datastore connection
        $datastore = new DatastoreClient();

        // sets query to search for user's email entered
        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('email', '=', $emailOrUsername)
            ->limit(1);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sends the datastore result to datastore ErrorHandling
        $datastoreErrorHandling = Datastore::ErrorHandling($result, 'EmailChange::maximumEmailChangeRequestCheck', 'datastore');
        // checks if datastore error handling caught any errors
        if ($datastoreErrorHandling != false) {
            // returns error alert
            self::$alert['error'] = $datastoreErrorHandling;
            return false;
        }

        $count = 0;
        // sets information from datastore of user gotten from users email or username
        foreach ($result as $entity) {
            $datastoreUserId = $entity->key()->path()[0]['id'];
            $datastorePassword = $entity['password'];
            $datastoreSecret = $entity['secret'];
            $datastoreType = $entity['type'];
            $count++;
        }

        // checks if there is no information to be gathered from ( wrong email or username )
        if ($count == 0) {
            // returns error alert
            self::$alert['error'] = 'Email or password you entered did not match our records. Please try again';
            return false;
        }

        // checks if password doesn't match the password on the users account
        if (!password_verify($password, $datastorePassword)) {
            // returns error alert
            self::$alert['error'] = 'Email or password you entered did not match our records. Please try again';
            return false;
        }

        // user gets logged in with LID and Li for username and password and passcode if they have one, but they do not get signed in for two step auth
        $LID = $datastoreUserId;
        $Li = Cryption::hashing($datastoreSecret);

        // checks if a session has been started and if it has then don't start another
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // sets authentication information in user's session
        $_SESSION["LID"] = $LID;
        $_SESSION["Li"] = $Li;

        // if rememberMe checkbox is checked then also set the users cookies
        if ($rememberMe) {
            setcookie('LID', $LID, time() + (86400 * 90), '/', 'localhost', isset($_SERVER["HTTPS"]), true);
            setcookie('Li', $Li, time() + (86400 * 90), '/', 'localhost', isset($_SERVER["HTTPS"]), true);
        }

        // sets the user dashboard reDirection route
        $this->dashboard = AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($datastoreType);

        // sets this login to true
        self::$success = true;
        return true;
    }

}