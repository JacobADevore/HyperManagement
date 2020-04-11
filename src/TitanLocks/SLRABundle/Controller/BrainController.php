<?php

namespace TitanLocks\SLRABundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use TitanLocks\Brain\Services\Security\Cryption;
use TitanLocks\SLRABundle\Controller\Authentication\Authentication;
use TitanLocks\SLRABundle\Controller\Logout\Logout;
use TitanLocks\SLRABundle\Controller\Login\Login;
use TitanLocks\SLRABundle\Controller\Recover\Recover;
use TitanLocks\SLRABundle\Controller\Recover\RecoverChangePassword;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BrainController extends Controller
{
    /**
     * @Route("/", name="login")
     */
    public function loginAction()
    {
        // runs authentication check on users LID Li for account type 0 and redirects if success
        $authentication = new Authentication(2);

        // checks if login authentication succeeds
        if ($authentication->success === true) {
            // checks if there is reDirect in url, if so then send user back to that page, if not then sent to dashboard
            if (isset($_GET['reDirect'])) {
                // checks if the container name given in 'reDirect' is an actual route, and if so then allow the redirect. if not then send user to dashboard
                if ($this->container->get('router')->getRouteCollection()->get($_GET['reDirect']) !== null) {
                    // reDirect to reDirection route
                    return $this->redirectToRoute($_GET['reDirect']);
                }
            }
            // reDirect to dashboard
            return $this->redirectToRoute($authentication->dashboard);
        }

        // checks if login information was posted and isset
        if ( isset($_POST['emailOrUsername']) && isset($_POST['password'])) {

            // checks if remember me box isset
            if (isset($_POST['rememberMe'])) {
                // check if remember me box is true
                if ($_POST['rememberMe'] == true) {
                    // create new login with remember me = true
                    $login = new Login(0, $_POST['emailOrUsername'], $_POST['password'], true);
                } else {
                    // create new login with remember me = false
                    $login = new Login(0, $_POST['emailOrUsername'], $_POST['password'], false);
                }
            } else {
                // create new login with remember me = false
                $login = new Login(0, $_POST['emailOrUsername'], $_POST['password'], false);
            }

            // checks if the login process was a success and if so logs the user back in
            if ($login::$success === true) {
                // checks if there is reDirect in url, if so then send user back to that page, if not then sent to dashboard
                if (isset($_GET['reDirect'])) {
                    // checks if the container name given in 'reDirect' is an actual route, and if so then allow the redirect. if not then send user to dashboard
                    if ($this->container->get('router')->getRouteCollection()->get($_GET['reDirect']) !== null) {
                        // reDirect to reDirection route
                        return $this->redirectToRoute($_GET['reDirect']);
                    }
                }
                // reDirect to dashboard
                return $this->redirectToRoute($login->dashboard);
            }
            // render login page with alerts
            return $this->render('@SLRA/Login.html.twig', $login::$alert);
        }

        // render login page
        return $this->render('@SLRA/Login.html.twig');
    }

    /**
     * @Route("/recover", name="recover")
     */
    public function recoverAction()
    {
        // runs authentication check on users LID Li for account type 0 and redirects if success
        $authentication = new Authentication(2);

        // checks if login authentication succeeds
        if ($authentication->success === true) {
            // checks if there is reDirect in url, if so then send user back to that page, if not then sent to dashboard
            if (isset($_GET['reDirect'])) {
                // checks if the container name given in 'reDirect' is an actual route, and if so then allow the redirect. if not then send user to dashboard
                if ($this->container->get('router')->getRouteCollection()->get($_GET['reDirect']) !== null) {
                    // reDirect to reDirection route
                    return $this->redirectToRoute($_GET['reDirect']);
                }
            }
            // reDirect to dashboard
            return $this->redirectToRoute($authentication->dashboard);
        }

        // checks if recover information isset
        if ( isset($_POST['email']) ) {

            $recover = new Recover($_POST['email']);

            // checks if the login process was a success and if so logs the user back in
            if ($recover->success === true) {
                $this->get('session')->getFlashBag()->set('success', 'Recover email successfully sent, please check your email and follow the recover process');
                // reDirect to login
                return $this->redirectToRoute('recover');
            }
            // render login page with alerts
            return $this->render('@SLRA/Recover.html.twig', $recover->alert);
        }

        // checks if recover information isset
        if ( isset($_GET['secret']) && isset($_GET['id']) && isset($_POST['newPassword']) && isset($_POST['reEnteredNewPassword']) ) {

            $recoverChangePassword = new RecoverChangePassword($_POST['newPassword'], $_POST['reEnteredNewPassword'], $_GET['id'], $_GET['secret']);

            // checks if the login process was a success and if so logs the user back in
            if ($recoverChangePassword->success == true) {
                $this->get('session')->getFlashBag()->set('success', 'Password successfully changed, please log in');
                // reDirect to login
                return $this->redirectToRoute('login');
            }
            // render login page with alerts
            return $this->render('@SLRA/RecoverChangePassword.html.twig', $recoverChangePassword->alert);
        }

        // checks if recover information isset
        if ( isset($_GET['secret']) && isset($_GET['id']) ) {
            // render recover change password page
            return $this->render('@SLRA/RecoverChangePassword.html.twig');
        }

        return $this->render('@SLRA/Recover.html.twig');
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction()
    {
        new Logout();
        return $this->redirectToRoute('login');
    }
}
