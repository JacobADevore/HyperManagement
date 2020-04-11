<?php

namespace TitanLocks\UserDashBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use TitanLocks\Brain\Services\Account\CompleteMaintenance;
use TitanLocks\Brain\Services\Account\CreateMaintenance;
use TitanLocks\Brain\Services\Account\CreatePasscode;
use TitanLocks\Brain\Services\Account\DeleteMaintenance;
use TitanLocks\SLRABundle\Controller\Authentication\Authentication;
use TitanLocks\Brain\Functions\AuthenticationDashboardRedirection;
use TitanLocks\Brain\Services\Account\EmailChange;
use TitanLocks\Brain\Services\Account\EmailChangeVerification;
use TitanLocks\Brain\Services\Account\PasswordChange;
use TitanLocks\Brain\Services\Account\CreateJobTitle;
use TitanLocks\Brain\Services\Account\DeleteJobTitle;
use TitanLocks\Brain\Services\Account\EditJobTitle;
use TitanLocks\Brain\Services\Account\DeleteUser;
use TitanLocks\Brain\Services\Account\CreateUser;
use TitanLocks\Brain\Services\Account\EditUsers;
use TitanLocks\Brain\Services\Account\CreateLocations;
use TitanLocks\Brain\Services\Account\EditLocations;
use TitanLocks\Brain\Services\Account\DeleteLocations;
use TitanLocks\Brain\Services\Account\CreateLock;
use TitanLocks\Brain\Services\Account\DeleteLock;
use TitanLocks\Brain\Services\Account\EditLock;
use TitanLocks\Brain\Services\Account\CreateEkey;
use TitanLocks\Brain\Services\Account\DeletePasscode;
use TitanLocks\Brain\Services\Account\DeleteEkey;
use TitanLocks\Brain\Services\Account\EditPasscode;
use TitanLocks\Brain\Services\Account\EditEkey;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BrainController extends Controller
{
    /**
     * @Route("/", name="dashboard")
     */
    public function dashboardAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        return $this->render('@UserDash/Dashboard.html.twig', $authentication->authenticationInformation);
    }

    /**
     * @Route("/change/email", name="dashboard/change/email")
     */
    public function changeEmailAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        // checks for posts for "Email Change"
        if (isset($_POST['newEmail']) && isset($_POST['reEnteredNewEmail']) && isset($_POST['password'])) {
            $emailChange = new EmailChange($authentication->authenticationInformation['userId'], $authentication->authenticationInformation['email'], $authentication->authenticationInformation['password'], $_POST['newEmail'], $_POST['reEnteredNewEmail'], $_POST['password'], $authentication->authenticationInformation['type']);

            // checks if EmailChange was a success
            if ($emailChange->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Email verification successfully sent, please check '.$_POST['newEmail'].' to complete the email change process');
                return $this->render('@UserDash/ChangeEmail.html.twig', $authentication->authenticationInformation);
            } else {
                return $this->render('@UserDash/ChangeEmail.html.twig', array_merge($authentication->authenticationInformation,$emailChange->alert));
            }
        }

        // checks for gets for "Email Change verification"
        if (isset($_GET['emailVerificationId']) && isset($_GET['secret'])) {
            $emailChangeVerification = new EmailChangeVerification($_GET['emailVerificationId'], $_GET['secret'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['type']);

            // checks if EmailChangeVerification was a success
            if ($emailChangeVerification->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Email has been successfully changed');
                return $this->redirectToRoute('dashboard/change/email');
            } else {
                return $this->render('@UserDash/ChangeEmail.html.twig', array_merge($authentication->authenticationInformation,$emailChangeVerification->alert));
            }
        }

        return $this->render('@UserDash/ChangeEmail.html.twig', $authentication->authenticationInformation);
    }

    /**
     * @Route("/change/password", name="dashboard/change/password")
     */
    public function changePasswordAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        // checks for posts for "Password Change"
        if (isset($_POST['password']) && isset($_POST['newPassword']) && isset($_POST['reEnteredNewPassword'])) {
            $passwordChange = new PasswordChange($authentication->authenticationInformation['userId'], $authentication->authenticationInformation['email'], $authentication->authenticationInformation['password'], $_POST['password'], $_POST['newPassword'], $_POST['reEnteredNewPassword'], $authentication->authenticationInformation['type']);

            // checks if passwordChange was a success
            if ($passwordChange->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Password has been successfully changed');
                return $this->render('@UserDash/ChangePassword.html.twig');
            } else {
                return $this->render('@UserDash/ChangePassword.html.twig', array_merge($authentication->authenticationInformation,$passwordChange->alert));
            }
        }

        return $this->render('@UserDash/ChangePassword.html.twig', $authentication->authenticationInformation);
    }

    /**
     * @Route("/manage/jobtitles", name="dashboard/manage/jobtitles")
     */
    public function manageJobTitlesAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        $jobTitles = array();

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('JobTitles')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($jobTitles, $entity['name']);
        }

        // checks for posts for "Manage Job Titles"
        //if (isset($_POST['jobTitleName']) && isset($_POST['createUsersFeature']) && isset($_POST['editUsers']) && isset($_POST['deleteUsers']) && isset($_POST['addLocksFeature']) && isset($_POST['editLocksFeature']) && isset($_POST['deleteLocksFeature']) && isset($_POST['addEkeyFeature']) && isset($_POST['editEkeyFeature']) && isset($_POST['deleteEkeyFeature']) && isset($_POST['addPasscodesFeature']) && isset($_POST['editPasscodeFeature']) && isset($_POST['deletePasscodeFeature']) && isset($_POST['manageLocationsFeature']) && isset($_POST['manageJobTitles']) && isset($_POST['manageMaintenanceFeature']) && isset($_POST['maintenanceFeature'])) {
        $features = [];

        if (isset($_POST['jobTitleName'])) {
            // 0
            if (isset($_POST['createUsersFeature']) && $_POST['createUsersFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 1
            if (isset($_POST['editUsers']) && $_POST['editUsers'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 2
            if (isset($_POST['deleteUsers']) && $_POST['deleteUsers'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 3
            if (isset($_POST['addLocksFeature']) && $_POST['addLocksFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 4
            if (isset($_POST['editLocksFeature']) && $_POST['editLocksFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 5
            if (isset($_POST['deleteLocksFeature']) && $_POST['deleteLocksFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 6
            if (isset($_POST['addEkeyFeature']) && $_POST['addEkeyFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 7
            if (isset($_POST['editEkeyFeature']) && $_POST['editEkeyFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 8
            if (isset($_POST['deleteEkeyFeature']) && $_POST['deleteEkeyFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 9
            if (isset($_POST['addPasscodesFeature']) && $_POST['addPasscodesFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 10
            if (isset($_POST['editPasscodeFeature']) && $_POST['editPasscodeFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 11
            if (isset($_POST['deletePasscodeFeature']) && $_POST['deletePasscodeFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 12
            if (isset($_POST['manageLocationsFeature']) && $_POST['manageLocationsFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 13
            if (isset($_POST['manageJobTitles']) && $_POST['manageJobTitles'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 14
            if (isset($_POST['manageMaintenanceFeature']) && $_POST['manageMaintenanceFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 15
            if (isset($_POST['maintenanceFeature']) && $_POST['maintenanceFeature'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            // 16
            if (isset($_POST['viewUnlockRecords']) && $_POST['viewUnlockRecords'] == true) {
                array_push($features, true);
            } else {
                array_push($features, false);
            }

            $manageJobTitlesCreate = new CreateJobTitle($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['jobTitleName'], $features);

            // checks if $manageJobTitlesCreate was a success
            if ($manageJobTitlesCreate->success == true) {
                $this->get('session')->getFlashBag()->set('success', '\''.ucfirst($_POST['jobTitleName']).'\' job title successfully created');
                return $this->redirectToRoute('dashboard/manage/jobtitles');
            } else {
                return $this->render('@UserDash/ManageJobTitles.html.twig', array_merge(array_merge($authentication->authenticationInformation,$manageJobTitlesCreate->alert), array('jobTitles' => $jobTitles)));
            }
        }

        if (isset($_POST['delete'])) {
            $manageJobTitlesDelete= new DeleteJobTitle($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['delete']);

            // checks if $manageJobTitlesCreate was a success
            if ($manageJobTitlesDelete->success == true) {
                $this->get('session')->getFlashBag()->set('success', '\''.ucfirst($_POST['delete']).'\' job title successfully deleted');
                return $this->redirectToRoute('dashboard/manage/jobtitles');
            } else {
                return $this->render('@UserDash/ManageJobTitles.html.twig', array_merge(array_merge($authentication->authenticationInformation,$manageJobTitlesDelete->alert), array('jobTitles' => $jobTitles)));
            }
        }

        if (isset($_GET['jobTitleName'])) {
            $jobTitle = '';
            $features = array();
            $alerts = array();

            if (isset($_POST['jobTitleNameEdit'])) {
                // 0
                if (isset($_POST['createUsersFeature']) && $_POST['createUsersFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 1
                if (isset($_POST['editUsers']) && $_POST['editUsers'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 2
                if (isset($_POST['deleteUsers']) && $_POST['deleteUsers'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 3
                if (isset($_POST['addLocksFeature']) && $_POST['addLocksFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 4
                if (isset($_POST['editLocksFeature']) && $_POST['editLocksFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 5
                if (isset($_POST['deleteLocksFeature']) && $_POST['deleteLocksFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 6
                if (isset($_POST['addEkeyFeature']) && $_POST['addEkeyFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 7
                if (isset($_POST['editEkeyFeature']) && $_POST['editEkeyFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 8
                if (isset($_POST['deleteEkeyFeature']) && $_POST['deleteEkeyFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 9
                if (isset($_POST['addPasscodesFeature']) && $_POST['addPasscodesFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 10
                if (isset($_POST['editPasscodeFeature']) && $_POST['editPasscodeFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 11
                if (isset($_POST['deletePasscodeFeature']) && $_POST['deletePasscodeFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 12
                if (isset($_POST['manageLocationsFeature']) && $_POST['manageLocationsFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 13
                if (isset($_POST['manageJobTitles']) && $_POST['manageJobTitles'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 14
                if (isset($_POST['manageMaintenanceFeature']) && $_POST['manageMaintenanceFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 15
                if (isset($_POST['maintenanceFeature']) && $_POST['maintenanceFeature'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                // 16
                if (isset($_POST['viewUnlockRecords']) && $_POST['viewUnlockRecords'] == true) {
                    array_push($features, true);
                } else {
                    array_push($features, false);
                }

                $manageJobTitlesEdit = new EditJobTitle($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['jobTitleOldName'], $_POST['jobTitleNameEdit'], $features);

                // checks if $manageJobTitlesCreate was a success
                if ($manageJobTitlesEdit->success == true) {
                    $this->get('session')->getFlashBag()->set('success', '\''.ucfirst($_POST['jobTitleNameEdit']).'\' job title successfully edited');
                    return $this->redirectToRoute('dashboard/manage/jobtitles');
                } else {
                    $alerts = $manageJobTitlesEdit->alert;
                }

            }

            // datastore connection
            $datastore = new DatastoreClient();

            $query = $datastore->query()
                ->kind('JobTitles')
                ->filter('name', '=', $_GET['jobTitleName'])
                ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId'])
                ->limit(1);

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            // sends the datastore result to datastore ErrorHandling
            $datastoreErrorHandling = Datastore::ErrorHandling($result, 'ManageJobTitles::jobTitleName', 'datastore');
            // checks if datastore error handling caught any errors
            if ($datastoreErrorHandling != false) {
                // returns error alert
                return $this->render('@UserDash/EditJobTitles.html.twig', array_merge($authentication->authenticationInformation,array('jobTitle' => $jobTitle, 'features' => $features, 'error' => 'Issue trying to find job title, please refresh and try again. if that does\'t work people retry the process')));
            }

            // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
            foreach ($result as $entity) {
                $jobTitle = $entity['name'];
                $features = $entity['features'];
            }

            if ($jobTitle == '') {
                return $this->render('@UserDash/EditJobTitles.html.twig', array_merge($authentication->authenticationInformation,array('jobTitle' => $jobTitle, 'features' => $features, 'error' => 'Issue trying to find job title, please refresh and try again. if that does\'t work people retry the process')));
            }

            return $this->render('@UserDash/EditJobTitles.html.twig', array_merge($authentication->authenticationInformation,array_merge(array('jobTitle' => $jobTitle, 'features' => $features), $alerts)));
        }

        return $this->render('@UserDash/ManageJobTitles.html.twig', array_merge($authentication->authenticationInformation, array('jobTitles' => $jobTitles)));
    }

    /**
     * @Route("/manage/users", name="dashboard/manage/users")
     */
    public function manageUsersAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        $users = array();

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('AdminsAndUsers')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($users, array('email' => $entity['email'], 'id' => $entity->key()->path()[0]['id']));
        }

        $jobTitles = array();

        $query = $datastore->query()
            ->kind('JobTitles')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($jobTitles, $entity['name']);
        }

        if (isset($_POST['email']) && isset($_POST['reEnteredEmail']) && isset($_POST['password']) && isset($_POST['jobTitle'])) {
            $manageUsersCreateUser= new CreateUser($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $_POST['email'], $_POST['reEnteredEmail'], $_POST['password'], $_POST['jobTitle'], $authentication->authenticationInformation['jobTitle'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($manageUsersCreateUser->success == true) {
                $this->get('session')->getFlashBag()->set('success', '\''.ucfirst($_POST['email']).'\' user successfully created');
                return $this->redirectToRoute('dashboard/manage/users');
            } else {
                return $this->render('@UserDash/ManageUsers.html.twig', array_merge(array_merge($authentication->authenticationInformation, $manageUsersCreateUser->alert), array('users' => $users, 'jobTitles' => $jobTitles)));
            }
        }

        if (isset($_POST['delete'])) {
            $manageUsersDeleteUser= new DeleteUser($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $_POST['delete'], $authentication->authenticationInformation['jobTitle']);

            // checks if $manageJobTitlesCreate was a success
            if ($manageUsersDeleteUser->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'User \''.ucfirst($_POST['delete']).'\' successfully deleted');
                return $this->redirectToRoute('dashboard/manage/users');
            } else {
                return $this->render('@UserDash/ManageUsers.html.twig', array_merge(array_merge($authentication->authenticationInformation,$manageUsersDeleteUser->alert), array('users' => $users, 'jobTitles' => $jobTitles)));
            }
        }

        if (isset($_GET['userId'])) {

            $userJobTitle = '';
            $userEmail = '';

            $query = $datastore->query()
                ->kind('AdminsAndUsers')
                ->filter('__key__', '=', $datastore->key('AdminsAndUsers', $_GET['userId']));

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
            foreach ($result as $entity) {
                $userJobTitle = $entity['jobTitle'];
                $userEmail = $entity['email'];
            }

            $jobTitles = array();

            $query = $datastore->query()
                ->kind('JobTitles')
                ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
            foreach ($result as $entity) {
                array_push($jobTitles, $entity['name']);
            }

            $alerts = array();

            if (isset($_POST['userEmail']) && isset($_POST['userPassword']) && isset($_POST['userJobTitle'])) {

                $editUsers = new EditUsers($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $_GET['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['userEmail'], $_POST['userPassword'], $_POST['userJobTitle']);

                // checks if $manageJobTitlesCreate was a success
                if ($editUsers->success == true) {
                    $this->get('session')->getFlashBag()->set('success', 'User ' . ucfirst($_POST['userEmail']) . '\' successfully edited');
                    return $this->redirectToRoute('dashboard/manage/users');
                } else {
                    $alerts = $editUsers->alert;
                }

            }

            $jobTitles = array();

            $query = $datastore->query()
                ->kind('JobTitles')
                ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
            foreach ($result as $entity) {
                array_push($jobTitles, $entity['name']);
            }

            return $this->render('@UserDash/EditUsers.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('email' => $userEmail, 'userJobTitle' => $userJobTitle, 'jobTitles' => $jobTitles), $alerts)));
        }

        return $this->render('@UserDash/ManageUsers.html.twig', array_merge($authentication->authenticationInformation, array('users' => $users, 'jobTitles' => $jobTitles)));
    }

    /**
     * @Route("/manage/locations", name="dashboard/manage/locations")
     */
    public function manageLocationsAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        $locations = array();

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('Locations')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($locations, $entity['name']);
        }

        if (isset($_POST['locationName'])) {

            $createLocation = new CreateLocations($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['locationName']);

            // checks if $manageJobTitlesCreate was a success
            if ($createLocation->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Location \'' . ucfirst($_POST['locationName']) . '\' successfully created');
                return $this->redirectToRoute('dashboard/manage/locations');
            } else {
                $alerts = $createLocation->alert;
            }

            return $this->render('@UserDash/ManageLocations.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('locations' => $locations), $alerts)));

        }

        if (isset($_GET['locationName'])) {

            $alerts = array();

            if (isset($_POST['editLocationName'])) {

                $editLocations = new EditLocations($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_GET['locationName'], $_POST['editLocationName']);

                // checks if $manageJobTitlesCreate was a success
                if ($editLocations->success == true) {
                    $this->get('session')->getFlashBag()->set('success', 'Location \'' . ucfirst($_GET['locationName']) . '\' successfully edited to \'' . ucfirst($_POST['editLocationName']) . '\'');
                    return $this->redirectToRoute('dashboard/manage/locations');
                } else {
                    $alerts = $editLocations->alert;
                }

            }

            return $this->render('@UserDash/EditLocations.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('locationName' => $_GET['locationName']), $alerts)));
        }

        if (isset($_POST['delete'])) {
            $deleteLocations= new DeleteLocations($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['delete']);

            // checks if $manageJobTitlesCreate was a success
            if ($deleteLocations->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Location \''.ucfirst($_POST['delete']).'\' successfully deleted');
                return $this->redirectToRoute('dashboard/manage/locations');
            } else {
                return $this->render('@UserDash/ManageLocations.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('locations' => $locations), $deleteLocations->alert)));
            }
        }

        return $this->render('@UserDash/ManageLocations.html.twig', array_merge($authentication->authenticationInformation, array('locations' => $locations)));
    }

    /**
     * @Route("/manage/locks", name="dashboard/manage/locks")
     */
    public function manageLocksAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }
        // datastore connection
        $datastore = new DatastoreClient();

        $lockLocations = array();

        $query = $datastore->query()
            ->kind('Locations')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($lockLocations, $entity['name']);
        }

        // checks for gets for "Email Change verification"
        if (isset($_GET['lockName'])) {
            $lockInfomation = array();
            $editLockLocations = $lockLocations;

            $query = $datastore->query()
                ->kind('Locks')
                ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId'])
                ->filter('name', '=', $_GET['lockName'])
                ->limit(1);

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
            foreach ($result as $entity) {
                $lockInfomation = array('name' => $_GET['lockName'], 'number' => $entity['lockNumber'], 'location' => $entity['location']);
            }

            unset($editLockLocations[array_search($lockInfomation['location'], $editLockLocations)]);

            if (isset($_POST['editLockName']) && isset($_POST['editLocationName'])) {
                $editLock = new EditLock($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'],$_GET['lockName'], $_POST['editLockName'], $_POST['editLocationName']);

                // checks if EmailChangeVerification was a success
                if ($editLock->success == true) {
                    $this->get('session')->getFlashBag()->set('success', 'Lock '.$_GET['lockName'].' successfully edited');
                    return $this->redirectToRoute('dashboard/manage/locks');
                } else {
                    return $this->render('@UserDash/EditLocks.html.twig', array_merge(array_merge($authentication->authenticationInformation, array('lockLocations' => $editLockLocations)), array_merge($editLock->alert, array('lock' => $lockInfomation))));
                }
            }

            return $this->render('@UserDash/EditLocks.html.twig', array_merge(array_merge($authentication->authenticationInformation, array('lockLocations' => $editLockLocations)), array('lock' => $lockInfomation)));
        }

        $locks = array();
        $lockNumbers = array();

        $query = $datastore->query()
            ->kind('Locks')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($locks, array('name' => $entity['name'], 'number' => $entity['lockNumber'], 'location' => $entity['location']));
            array_push($lockNumbers, $entity['lockNumber']);
        }

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
                    'username' => $authentication->authenticationInformation['ttlUsername'],
                    'password' => md5($authentication->authenticationInformation['ttlPassword']),
                    'redirect_uri' => 'http://titanlocks.co'),
        );
        $tokenUrl = 'https://api.sciener.cn/oauth2/token';

        $alerts = array();

        $client = new Client();

        try {
            $accessTokenResponse = $client->request('POST', $tokenUrl, $accessTokenData);
        } catch (RequestException $e) {
            $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
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
                    'pageNo' => 1,
                    'pageSize' => 10000,
                    'date' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000))
                )
        );
        $lockListUrl = 'https://api.sciener.cn/v3/lock/list';

        $apiLockList = array();

        try {
            $lockListResponse = $client->request('POST', $lockListUrl, $lockListData);
        } catch (RequestException $e) {
            $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
        }

        foreach (json_decode($lockListResponse->getBody()->getContents())->list as $lockInfo) {
            array_push($apiLockList, array('lockAlias' => $lockInfo->lockAlias, 'lockName' => $lockInfo->lockName, 'battery' => $lockInfo->electricQuantity));
        }

        if (isset($_POST['lockName']) && isset($_POST['locationName']) && isset($_POST['lockNumber'])) {
            $createLock= new CreateLock($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['lockName'], $_POST['locationName'], $_POST['lockNumber'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($createLock->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Lock \''.ucfirst($_POST['lockName']).'\' successfully created');
                return $this->redirectToRoute('dashboard/manage/locks');
            } else {
                return $this->render('@UserDash/ManageLocks.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('locks' => $locks, 'apiLockList' => $apiLockList), array_merge($alerts, array_merge(array('lockNumbers' => $lockNumbers), array_merge(array( 'lockLocations' => $lockLocations), $createLock->alert))))));
            }
        }

        if (isset($_POST['delete'])) {
            $deleteLock= new DeleteLock($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['delete']);

            // checks if $manageJobTitlesCreate was a success
            if ($deleteLock->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Lock \''.ucfirst($_POST['delete']).'\' successfully deleted');
                return $this->redirectToRoute('dashboard/manage/locks');
            } else {
                return $this->render('@UserDash/ManageLocks.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('locks' => $locks, 'apiLockList' => $apiLockList), array_merge($alerts, array_merge(array('lockNumbers' => $lockNumbers), array_merge(array( 'lockLocations' => $lockLocations), $deleteLock->alert))))));
            }
        }

        return $this->render('@UserDash/ManageLocks.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('locks' => $locks, 'apiLockList' => $apiLockList), array_merge($alerts, array_merge(array('lockNumbers' => $lockNumbers), array( 'lockLocations' => $lockLocations))))));
    }

    /**
     * @Route("/manage/passcodes", name="dashboard/manage/passcodes")
     */
    public function managePasscodesAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $locks = array();
        $lockIds = array();
        $lockNameFromId = array();

        $query = $datastore->query()
            ->kind('Locks')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($locks, $entity['name']);
            array_push($lockIds, $entity['lockId']);
            $lockNameFromId[$entity['name']] = $entity['lockId'];
        }

        $locations = array();
        $locationsAndLocks = array();

        $query = $datastore->query()
            ->kind('Locations')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($locations, $entity['name']);
        }

        foreach ($locations as $location) {
            $query = $datastore->query()
                ->kind('Locks')
                ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId'])
                ->filter('location', '=', $location);

            // runs the query set above by $type
            $result = $datastore->runQuery($query);

            $locksFromLocation = array();

            // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
            foreach ($result as $entity) {
                array_push($locksFromLocation, array('name' => $entity['name']));
            }

            array_push($locationsAndLocks, array($locksFromLocation, 'locationName' => $location));
        }

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
                    'username' => $authentication->authenticationInformation['ttlUsername'],
                    'password' => md5($authentication->authenticationInformation['ttlPassword']),
                    'redirect_uri' => 'http://titanlocks.co'),
        );
        $tokenUrl = 'https://api.sciener.cn/oauth2/token';

        $client = new Client();

        try {
            $accessTokenResponse = $client->request('POST', $tokenUrl, $accessTokenData);
        } catch (RequestException $e) {
            $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
        }

        $accessToken = json_decode($accessTokenResponse->getBody()->getContents())->access_token;

        $mt = explode(' ', microtime());

        $lockPasscodes = array();

        foreach ($lockIds as $lockId) {

            $lockListData = array(
                'headers'   =>
                    array(
                        'Accept' => 'application/json',
                    ),
                'form_params' =>
                    array (
                        'clientId' => 'c54850a0d7f146288d18dc773d9846f6',
                        'accessToken' => $accessToken,
                        'lockId' => $lockId,
                        'pageNo' => 1,
                        'pageSize' => 10000,
                        'date' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000))
                    )
            );
            $lockListUrl = 'https://api.sciener.cn/v3/lock/listKeyboardPwd';

            try {
                $lockListResponse = $client->request('POST', $lockListUrl, $lockListData);
            } catch (RequestException $e) {
                $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
            }

            foreach (json_decode($lockListResponse->getBody()->getContents())->list as $lockInfo) {
                if ($lockInfo->endDate == 0) {
                    array_push($lockPasscodes, array('passcodeType' => $lockInfo->keyboardPwdType,'lockId' => $lockInfo->lockId, 'lockName' => array_search($lockInfo->lockId, $lockNameFromId), 'passcodeId' => $lockInfo->keyboardPwdId, 'passcode' => $lockInfo->keyboardPwd, 'startDate' => date("Y-m-d H:i:s", $lockInfo->startDate/1000), 'endDate' => 0, 'sentDate' => date("Y-m-d H:i", $lockInfo->sendDate/1000)));
                } else {
                    array_push($lockPasscodes, array('passcodeType' => $lockInfo->keyboardPwdType, 'lockId' => $lockInfo->lockId, 'lockName' => array_search($lockInfo->lockId, $lockNameFromId), 'passcodeId' => $lockInfo->keyboardPwdId, 'passcode' => $lockInfo->keyboardPwd, 'startDate' => date("Y-m-d H:i:s", $lockInfo->startDate/1000), 'endDate' => date("Y-m-d H:i:s", $lockInfo->endDate/1000), 'sentDate' => date("Y-m-d H:i", $lockInfo->sendDate/1000)));
                }
            }

        }


        if (isset($_POST['passcode']) && isset($_POST['startTime']) && isset($_POST['endTime']) && isset($_POST['lockName']) && isset($_POST['passcodeType']) && isset($_POST['passcodeGenerate'])) {
            $createPasscode= new CreatePasscode($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['passcode'], $_POST['startTime'], $_POST['endTime'], $_POST['lockName'], $_POST['passcodeType'], $_POST['passcodeGenerate'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($createPasscode->success == true) {
                if ($_POST['passcodeGenerate'] == 0) {
                    $this->get('session')->getFlashBag()->set('success', 'Passcode \''.$_POST['passcode'].'\' successfully added to lock \''.$_POST['lockName'].'\'');
                } elseif ($_POST['passcodeGenerate'] == 1) {
                    $this->get('session')->getFlashBag()->set('success', 'Passcode successfully generated to lock \''.$_POST['lockName'].'\'');
                }
                return $this->redirectToRoute('dashboard/manage/passcodes');
            } else {
                return $this->render('@UserDash/ManagePasscodes.html.twig', array_merge(array_merge($authentication->authenticationInformation, $createPasscode->alert), array_merge(array('lockList' => $locks), array('lockPasscodes' => $lockPasscodes))));
            }
        }

        if (isset($_POST['delete'])) {
            $deletePasscode= new DeletePasscode($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['delete'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($deletePasscode->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Passcode \''.explode(',',$_POST['delete'])[0].'\' successfully deleted');
                return $this->redirectToRoute('dashboard/manage/passcodes');
            } else {
                return $this->render('@UserDash/ManagePasscodes.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('lockList' => $locks), array_merge(array('lockPasscodes' => $lockPasscodes), $deletePasscode->alert))));
            }
        }


        // checks for gets for "Email Change verification"
        if (isset($_GET['passcodeId']) && isset($_GET['lockId']) && isset($_GET['passcode']) && isset($_GET['lock'])) {

            $passcodeInformation = array('passcodeId' => $_GET['passcodeId'], 'lockId' => $_GET['lockId'], 'passcode' => $_GET['passcode'], 'lock' => $_GET['lock']);

            if (isset($_POST['editPasscode']) && isset($_POST['editStartTime']) && isset($_POST['editEndTime'])) {
                $editPasscode= new EditPasscode($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['editPasscode'], $_POST['editStartTime'], $_POST['editEndTime'], $_GET['passcodeId'], $_GET['lockId'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

                // checks if EmailChangeVerification was a success
                if ($editPasscode->success == true) {
                    $this->get('session')->getFlashBag()->set('success', 'Passcode '.$_GET['passcode'].' successfully edited');
                    return $this->redirectToRoute('dashboard/manage/passcodes');
                } else {
                    return $this->render('@UserDash/EditPasscodes.html.twig', array_merge(array_merge($authentication->authenticationInformation, array('lockList' => $locks)), array_merge($passcodeInformation, $editPasscode->alert)));
                }
            }

            return $this->render('@UserDash/EditPasscodes.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('lockList' => $locks), $passcodeInformation)));
        }

        return $this->render('@UserDash/ManagePasscodes.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('lockList' => $locks), array('lockPasscodes' => $lockPasscodes, 'locationsAndLocks' => $locationsAndLocks))));
    }

    /**
     * @Route("/manage/ekeys", name="dashboard/manage/ekeys")
     */
    public function manageEkeysAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $locks = array();
        $lockIds = array();
        $lockNameFromId = array();

        $query = $datastore->query()
            ->kind('Locks')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($locks, $entity['name']);
            array_push($lockIds, $entity['lockId']);
            $lockNameFromId[$entity['name']] = $entity['lockId'];
        }

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
                    'username' => $authentication->authenticationInformation['ttlUsername'],
                    'password' => md5($authentication->authenticationInformation['ttlPassword']),
                    'redirect_uri' => 'http://titanlocks.co'),
        );
        $tokenUrl = 'https://api.sciener.cn/oauth2/token';

        $client = new Client();

        try {
            $accessTokenResponse = $client->request('POST', $tokenUrl, $accessTokenData);
        } catch (RequestException $e) {
            $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
        }

        $accessToken = json_decode($accessTokenResponse->getBody()->getContents())->access_token;

        $mt = explode(' ', microtime());

        $lockEkeys = array();

        foreach ($lockIds as $lockId) {

            $lockListData = array(
                'headers'   =>
                    array(
                        'Accept' => 'application/json',
                    ),
                'form_params' =>
                    array (
                        'clientId' => 'c54850a0d7f146288d18dc773d9846f6',
                        'accessToken' => $accessToken,
                        'lockId' => $lockId,
                        'pageNo' => 1,
                        'pageSize' => 10000,
                        'date' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000))
                    )
            );
            $lockListUrl = 'https://api.sciener.cn/v3/lock/listKey';

            try {
                $lockListResponse = $client->request('POST', $lockListUrl, $lockListData);
            } catch (RequestException $e) {
                $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
            }

            foreach (json_decode($lockListResponse->getBody()->getContents())->list as $lockInfo) {
                if ($lockInfo->endDate == 0) {
                    array_push($lockEkeys, array('lockId' => $lockInfo->lockId, 'lockName' => array_search($lockInfo->lockId, $lockNameFromId), 'ekeyId' => $lockInfo->keyId, 'username' => $lockInfo->username, 'startDate' => date("Y-m-d H:i:s", $lockInfo->startDate/1000), 'endDate' => 0, 'status' => $lockInfo->keyStatus));
                } else {
                    array_push($lockEkeys, array('lockId' => $lockInfo->lockId, 'lockName' => array_search($lockInfo->lockId, $lockNameFromId), 'ekeyId' => $lockInfo->keyId, 'username' => $lockInfo->username, 'startDate' => date("Y-m-d H:i:s", $lockInfo->startDate/1000), 'endDate' => date("Y-m-d H:i:s", $lockInfo->endDate/1000), 'status' => $lockInfo->keyStatus));
                }
            }

        }


        if (isset($_POST['username']) && isset($_POST['startTime']) && isset($_POST['endTime']) && isset($_POST['lockName']) && isset($_POST['remoteEnable'])) {
            $createEkey= new CreateEkey($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['username'], $_POST['startTime'], $_POST['endTime'], $_POST['lockName'], $_POST['remoteEnable'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($createEkey->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Ekey for \''.$_POST['lockName'].'\' successfully sent to user \''.$_POST['username'].'\'');
                return $this->redirectToRoute('dashboard/manage/ekeys');
            } else {
                return $this->render('@UserDash/ManageEkeys.html.twig', array_merge(array_merge($authentication->authenticationInformation, $createEkey->alert), array_merge(array('lockList' => $locks), array('lockEkeys' => $lockEkeys))));
            }
        }

        if (isset($_POST['delete'])) {
            $deleteEkey = new DeleteEkey($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['delete'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($deleteEkey->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Ekey \''.$_POST['delete'].'\' successfully deleted');
                return $this->redirectToRoute('dashboard/manage/ekeys');
            } else {
                return $this->render('@UserDash/ManageEkeys.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('lockList' => $locks), array_merge(array('lockEkeys' => $lockEkeys), $deleteEkey->alert))));
            }
        }

        if (isset($_GET['ekeyid']) && isset($_GET['username'])) {

            if (isset($_POST['editStartTime']) && isset($_POST['editEndTime'])) {
                $editEkey = new EditEkey($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_GET['ekeyid'], $_POST['editStartTime'], $_POST['editEndTime'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

                // checks if $manageJobTitlesCreate was a success
                if ($editEkey->success == true) {
                    $this->get('session')->getFlashBag()->set('success', 'Ekey \''.$_GET['username'].'\' successfully edited');
                    return $this->redirectToRoute('dashboard/manage/ekeys');
                } else {
                    return $this->render('@UserDash/EditEkeys.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('ekeyid' => $_GET['ekeyid'], 'lockId' => $_GET['lockId'], 'username' => $_GET['username']), $editEkey->alert)));
                }
            }

            return $this->render('@UserDash/EditEkeys.html.twig', array_merge($authentication->authenticationInformation, array('ekeyid' => $_GET['ekeyid'], 'username' => $_GET['username'])));

        }


        return $this->render('@UserDash/ManageEkeys.html.twig', array_merge($authentication->authenticationInformation, array_merge(array('lockList' => $locks), array('lockEkeys' => $lockEkeys))));
    }

    /**
     * @Route("/manage/maintenance", name="dashboard/manage/maintenance")
     */
    public function manageMaintenanceAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        // datastore connection
        $datastore = new DatastoreClient();

        $query = $datastore->query()
            ->kind('Locks')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        $locks = array();
        $lockNameFromId = array();

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($locks, array('name' => $entity['name'], 'lockId' => $entity['lockId']));
        }

        $query = $datastore->query()
            ->kind('Locks')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            $lockNameFromId[$entity['name']] = $entity['lockId'];
        }

        $query = $datastore->query()
            ->kind('Maintenance')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        $maintenance = array();

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($maintenance, array('id' => $entity->key()->path()[0]['id'],'description' => $entity['description'], 'lockName' => array_search($entity['lockId'], $lockNameFromId), 'passcode' => $entity['passcode'], 'status' => $entity['status'], 'urgency' => $entity['urgency'], 'created' => date("Y-m-d H:i:s", ($entity['created']+86400000)/1000), 'user' => $entity['user']));
        }

        if (isset($_POST['description']) && isset($_POST['urgency']) && isset($_POST['lockId'])) {
            $createMaintenance= new CreateMaintenance($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['description'], $_POST['urgency'], $_POST['lockId'], $authentication->authenticationInformation['ttlUsername'], $authentication->authenticationInformation['ttlPassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($createMaintenance->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Maintenace Ticket successfully created');
                return $this->redirectToRoute('dashboard/manage/maintenance');
            } else {
                return $this->render('@UserDash/ManageMaintenance.html.twig', array_merge(array_merge($authentication->authenticationInformation, $createMaintenance->alert), array_merge(array('lockList' => $locks))));
            }
        }

        if (isset($_POST['completed'])) {
            $completeMaintenance= new CompleteMaintenance($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['completed']);

            // checks if $manageJobTitlesCreate was a success
            if ($completeMaintenance->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Maintenace Ticket successfully marked as completed');
                return $this->redirectToRoute('dashboard/manage/maintenance');
            } else {
                return $this->render('@UserDash/ManageMaintenance.html.twig', array_merge(array_merge($authentication->authenticationInformation, $completeMaintenance->alert), array('lockList' => $locks, 'maintenances' => $maintenance)));
            }
        }

        if (isset($_POST['delete'])) {
            $deleteMaintenance= new DeleteMaintenance($authentication->authenticationInformation['adminUserId'], $authentication->authenticationInformation['userId'], $authentication->authenticationInformation['jobTitle'], $_POST['delete']);

            // checks if $manageJobTitlesCreate was a success
            if ($deleteMaintenance->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Maintenace Ticket successfully deleted');
                return $this->redirectToRoute('dashboard/manage/maintenance');
            } else {
                return $this->render('@UserDash/ManageMaintenance.html.twig', array_merge(array_merge($authentication->authenticationInformation, $deleteMaintenance->alert), array('lockList' => $locks, 'maintenances' => $maintenance)));
            }
        }

        return $this->render('@UserDash/ManageMaintenance.html.twig', array_merge($authentication->authenticationInformation, array('lockList' => $locks, 'maintenances' => $maintenance)));
    }

    /**
     * @Route("/viewunlockrecords", name="dashboard/viewunlockrecords")
     */
    public function manageViewUnlockRecordsAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 0) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

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
                    'username' => $authentication->authenticationInformation['ttlUsername'],
                    'password' => md5($authentication->authenticationInformation['ttlPassword']),
                    'redirect_uri' => 'http://titanlocks.co'),
        );
        $tokenUrl = 'https://api.sciener.cn/oauth2/token';

        $client = new Client();

        try {
            $accessTokenResponse = $client->request('POST', $tokenUrl, $accessTokenData);
        } catch (RequestException $e) {
            $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
        }

        $accessToken = json_decode($accessTokenResponse->getBody()->getContents())->access_token;

        $mt = explode(' ', microtime());

        $unlockRecords= array();

        // datastore connection
        $datastore = new DatastoreClient();

        $locks = array();

        $query = $datastore->query()
            ->kind('Locks')
            ->filter('adminUserId', '=', $authentication->authenticationInformation['adminUserId']);

        // runs the query set above by $type
        $result = $datastore->runQuery($query);

        // sets $count to 0 and creates for each to get the amount of email change requests in past 21600 ( 6 hours )
        foreach ($result as $entity) {
            array_push($locks, array('lockId' => $entity['lockId'], 'lockName' => $entity['name']));
        }

        $pageValue = 1;

        $currentLock = 'Please choose a lock';
        $lockId = '';


        if (isset($_GET['lockid']) && isset($_GET['pageNo'])) {
            $pageValue = $_GET['pageNo'];
            if (isset($_GET['nextPage'])) {
                $pageValue = $_GET['nextPage'];
            }
            $info = explode(",", $_GET['lockid']);
            $lockId = $info[0];
            $currentLock = $info[1];

            $lockListData = array(
                'headers'   =>
                    array(
                        'Accept' => 'application/json',
                    ),
                'form_params' =>
                    array (
                        'clientId' => 'c54850a0d7f146288d18dc773d9846f6',
                        'accessToken' => $accessToken,
                        'lockId' => $lockId,
                        'startDate' => 0,
                        'endDate' => 0,
                        'pageNo' => $pageValue,
                        'pageSize' => 100,
                        'date' => ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000))
                    )
            );
            $lockListUrl = 'https://api.sciener.cn/v3/lockRecord/list';

            try {
                $lockListResponse = $client->request('POST', $lockListUrl, $lockListData);
            } catch (RequestException $e) {
                $alerts = array('error' => 'Error connection issue loading locks, please refresh and try again');
            }

            foreach (json_decode($lockListResponse->getBody()->getContents())->list as $lockInfo) {
                array_push($unlockRecords, array('lockId' => $lockInfo->lockId, 'recordType' => $lockInfo->recordType, 'success' => $lockInfo->success, 'username' => $lockInfo->username, 'passcode' => $lockInfo->keyboardPwd, 'lockDate' => date("Y-m-d H:i:s", $lockInfo->lockDate/1000)));
            }

            unset($locks[array_search($_GET['lockid'], $locks)]);
        }

        return $this->render('@UserDash/ViewUnlockRecords.html.twig', array_merge($authentication->authenticationInformation, array('unlockRecords' => $unlockRecords, 'lockList' => $locks, 'pageValue' => $pageValue, 'currentLock' => $currentLock, 'lockId' => $lockId)));
    }

}
