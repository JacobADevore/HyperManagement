<?php

namespace TitanLocks\OurDashBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use TitanLocks\SLRABundle\Controller\Authentication\Authentication;
use TitanLocks\Brain\Functions\AuthenticationDashboardRedirection;
use Google\Cloud\Datastore\DatastoreClient;
use TitanLocks\Brain\Services\ErrorHandling\Datastore;
use TitanLocks\Brain\Services\Account\EmailChange;
use TitanLocks\Brain\Services\Account\EmailChangeVerification;
use TitanLocks\Brain\Services\Account\PasswordChange;
use TitanLocks\Brain\Services\Account\CreateAdminUser;

class BrainController extends Controller
{
    /**
     * @Route("/", name="our/dashboard")
     */
    public function indexAction()
    {
        // runs authentication check on users LID Li to make sure they are logged in
        $authentication = new Authentication(0);

        // checks if login authentication fails
        if ($authentication->success === false) {
            // returns the users to the login page with the errors captured from the authentication class
            return $this->redirectToRoute('login', $authentication->alert);
        }

        // check if the user has access to that dashboard, if not then redirect to login where they will be redirected to their dashboard
        if ($authentication->authenticationInformation['type'] != 2) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        return $this->render('@OurDash/Dashboard.html.twig');
    }

    /**
     * @Route("/change/email", name="our/dashboard/change/email")
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
        if ($authentication->authenticationInformation['type'] != 2) {
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

        return $this->render('@OurDash/ChangeEmail.html.twig', $authentication->authenticationInformation);
    }

    /**
     * @Route("/change/password", name="our/dashboard/change/password")
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
        if ($authentication->authenticationInformation['type'] != 2) {
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

        return $this->render('@OurDash/ChangePassword.html.twig', $authentication->authenticationInformation);
    }

    /**
     * @Route("/manage/users", name="our/dashboard/manage/users")
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
        if ($authentication->authenticationInformation['type'] != 2) {
            return $this->redirectToRoute(AuthenticationDashboardRedirection::AuthenticationDashboardRedirectionName($authentication->authenticationInformation['type']));
        }

        if (isset($_POST['email']) && isset($_POST['reEnteredEmail']) && isset($_POST['password']) && isset($_POST['ttlusername']) && isset($_POST['ttlpassword'])) {
            $manageUsersCreateUser= new CreateAdminUser($_POST['email'], $_POST['reEnteredEmail'], $_POST['password'], $_POST['ttlusername'], $_POST['ttlpassword']);

            // checks if $manageJobTitlesCreate was a success
            if ($manageUsersCreateUser->success == true) {
                $this->get('session')->getFlashBag()->set('success', '\''.ucfirst($_POST['email']).'\' admin user successfully created');
                return $this->redirectToRoute('our/dashboard/manage/users');
            } else {
                return $this->render('@OurDash/ManageUsers.html.twig', array_merge(array_merge($authentication->authenticationInformation, $manageUsersCreateUser->alert)));
            }
        }

        return $this->render('@OurDash/ManageUsers.html.twig', array_merge($authentication->authenticationInformation));
    }
}
