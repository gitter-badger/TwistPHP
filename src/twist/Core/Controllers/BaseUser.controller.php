<?php
/**
 * This file is part of TwistPHP.
 *
 * TwistPHP is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TwistPHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with TwistPHP.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author     Shadow Technologies Ltd. <contact@shadow-technologies.co.uk>
 * @license    https://www.gnu.org/licenses/gpl.html LGPL License
 * @link       https://twistphp.com
 *
 */

namespace Twist\Core\Controllers;
use \Twist\Core\Models\User\Auth;

/**
 *  An User base controller that can be used instead of Base when you require login, authentication and other user pages. This controller should be used as an extension to a route controller class.
 * @package Twist\Core\Controllers
 */
class BaseUser extends Base{

	/**
	 * @var \Twist\Core\Packages\User
	 */
    protected $resUser = null;
	
    protected $strEntryPageURI = null;

    public function _baseCalls(){

        $this->resUser = \Twist::User();

        $this -> _aliasURI( 'change-password', 'changePassword' );
        $this -> _aliasURI( 'forgotten-password', 'forgottenPassword' );
        $this -> _aliasURI( 'verify-account', 'verifyAccount' );
        $this -> _aliasURI( 'device-manager', 'deviceManager' );
    }

	/**
	 * Set the entry page for the restricted area
	 * @param null $strEntryPageURI
	 */
    public function _entryPage($strEntryPageURI = null){
        $this->strEntryPageURI = $strEntryPageURI;
    }

	/**
	 * Login page, the response for this page would be the login form with a forgotten password link and remember me button.
	 * @return string
	 */
    public function login(){

        if(array_key_exists('logout',$_GET)){
            $this->logout();
        }

        return $this->resUser->viewExtension('login_form');
    }

	/**
	 * Authentication script, upon login the post request will be sent to this script. If the login is successful the user will be redirected to the entry page or './'.
	 * if hte login request has failed the user will be forwarded on to the relevan page i.e Change Password, Verify Account or the login page with an error message.
	 */
    public function authenticate(){

        $arrResult = Auth::login($_POST['email'],$_POST['password'],(array_key_exists('remember',$_POST) && $_POST['remember'] == '1'));

        if($arrResult['issue'] != ''){

            \Twist::Session()->data('site-login_error_message',$arrResult['message']);

            //A login issue has occurred, redirect to the relevant page
            switch($arrResult['issue']){

                case 'temporary':
                    \Twist::redirect('./change-password');
                    break;

                case 'verify':
                    \Twist::redirect('./verify-account');
                    break;

                case 'disabled':
                case 'password':
                case 'email':
                    \Twist::redirect('./login');
                    break;
            }

        }elseif($arrResult['status']){
            //Login complete
            \Twist::redirect(is_null($this->strEntryPageURI) ? './' : './'.$this->strEntryPageURI);
        }else{
            //You are not logged in
            \Twist::redirect('./login');
        }
    }

	/**
	 * Logout route will log the user out and then redirect them on to the login page
	 */
    public function logout(){
        Auth::logout();
        \Twist::redirect('./login');
    }

	/**
	 * Forgotten password page, form here you can enter your email address and you will then be emailed a temporary password.
	 * Once submitted the user will be directed on to the postForgottenPassword function in the controller.
	 *
	 * @return string
	 */
    public function forgottenPassword(){
        return $this->resUser->viewExtension('forgotten_password_form');
    }

	/**
	 * The forgotten password request is processed by this function, if the details are correct the user is emailed a temporary password and then redirected to the login page.
	 * If the request has failed the user will be shown the forgotten password form again.
	 */
    public function postForgottenPassword(){

        //Process the forgotten password request
        if(array_key_exists('forgotten_email',$_POST) && $_POST['forgotten_email'] != ''){
            $arrUserData = $this->resUser->getByEmail($_POST['forgotten_email']);

            //Now if the email exists send out the reset password email.
            if(is_array($arrUserData) && count($arrUserData) > 0){

                $resUser = $this->resUser->get($arrUserData['id']);
                $resUser->resetPassword(true);
                $resUser->commit();

                \Twist::Session()->data('site-login_message','A temporary password has been emailed to you');
                \Twist::redirect('./login');
            }
        }

	    \Twist::redirect('./forgotten-password');
    }

	/**
	 * The change password form can display two types of form, the one that contains a box to enter your current password and your new password or a form that only required your new password to be entered.
	 * The form that does not required you to enter your current password as well will only be displayed when you have a temporary password that needs to be personalised.
	 *
	 * @return string
	 */
    public function changePassword(){
        return $this->resUser->viewExtension('change_password_form');
    }

	/**
	 * The change password request is processed by this function, if the details are all correct the user will be redirected to the entry page or './'.
	 * If there is a problem with the data entered the user will see the change password page again.
	 */
    public function postChangePassword(){

        if(array_key_exists('password',$_POST) && array_key_exists('confirm_password',$_POST)){

            if($this->resUser->loggedIn()){

                if($_POST['password'] === $_POST['confirm_password']){

                    if(\Twist::Session()->data('user-temp_password') === '0'){

                        if(array_key_exists('current_password',$_POST)){

                            $strNewPassword = $_POST['password'];

                            //Change the users password and re-log them in (Only for none-temp password users)
                            $this->resUser->changePassword(\Twist::Session()->data('user-id'),$strNewPassword,$_POST['current_password'],false);

                            //Remove the two posted password vars
                            unset($_POST['password']);
                            unset($_POST['current_password']);

                            Auth::login(\Twist::Session()->data('user-email'),$strNewPassword);
                            \Twist::redirect('./');
                        }
                    }else{

                        $strNewPassword = $_POST['password'];

                        //Change the users password and re-log them in
                        $this->resUser->updatePassword(\Twist::Session()->data('user-id'),$strNewPassword);

                        //Remove the posted password and reset the session var
                        unset($_POST['password']);
                        \Twist::Session()->data('user-temp_password','0');

                        Auth::login(\Twist::Session()->data('user-email'),$strNewPassword);
                        \Twist::redirect('./');
                    }

                }else{
                    \Twist::Session()->data('site-error_message','The passwords you entered do not match');
                    \Twist::redirect('./change-password');
                }
            }
        }

	    \Twist::redirect('./change-password');
    }

	/**
	 * Account verification page allows the user to verify their account, when an account is registered (depending on the what settings have been enabled) as use might be require to verify there email address by entering the code received in the welcome email.
	 * This will then confirm that the user has received the email and that the email address is valid.
	 * @return string
	 */
    public function verifyAccount(){
        return $this->resUser->viewExtension('account_verification');
    }

	/**
	 * Process the email verification code that has been submitted for validation, upon successful process that user will be redirected to the login page.
	 */
    public function postVerifyAccount(){

        //Resend a new verification code
        if(array_key_exists('verification_email',$_POST) && $_POST['verification_email'] != ''){
            $arrUserData = $this->resUser->getByEmail($_POST['verification_email']);

            //Now if the email exists send out the reset password email.
            if(is_array($arrUserData) && count($arrUserData) > 0){

                $resUser = $this->resUser->get($arrUserData['id']);
                $resUser->requireVerification();
                $resUser->commit();
            }
        }

        if(array_key_exists('verify',$_GET) && array_key_exists('verify',$_GET) && $_GET['verify'] != ''){
            $this->resUser->verifyEmail($_GET['verify']);
        }

	    \Twist::redirect('./login');
    }

	/**
	 * Manage all the connected devices registered under a users account, once the user is logged in they can view this page and see all the devices that have been used to connect to the account.
	 * The user has the ability to forget a device (removes the device and associated session) effectively and remotely login the user out of the site on the requested device. Also the ability to
	 * rename the device so that at a glance you know which device is which.
	 * @return string
	 */
    public function deviceManager(){

        $arrUserData = Auth::current();

        $arrCurrentDevices = Auth::SessionHandler()->getCurrentDevice($arrUserData['user_id']);
        $arrDevices = Auth::SessionHandler()->getDeviceList($arrUserData['user_id']);

        $strDeviceList = '';
        foreach($arrDevices as $arrEachDevice){

            $arrEachDevice['current'] = ($arrCurrentDevices['id'] == $arrEachDevice['id']) ? true : false;

	        if(array_key_exists('forget-device',$_GET)) {
		        Auth::SessionHandler()->forgetDevice($arrUserData['user_id'], $_GET['forget-device']);
		        \Twist::redirect('./device-manager');
	        }

	        if(array_key_exists('notifications',$_GET)) {
		        Auth::SessionHandler()->notifications($arrUserData['user_id'], ($_GET['notifications'] == 'on') ? true : false);
		        \Twist::redirect('./device-manager');
	        }

            if(array_key_exists('edit-device',$_GET) && $arrEachDevice['device'] == $_GET['edit-device']){
                $strDeviceList .= $this->_view(sprintf('%suser/device-each-edit.tpl',TWIST_FRAMEWORK_VIEWS), $arrEachDevice);
            }else{
                $strDeviceList .= $this->_view(sprintf('%suser/device-each.tpl',TWIST_FRAMEWORK_VIEWS), $arrEachDevice);
            }
        }

	    $arrTags = array(
		    'device_list' => $strDeviceList,
		    'notifications' => Auth::SessionHandler()->notifications($arrUserData['user_id'])
	    );

        return $this->_view(sprintf('%suser/devices.tpl',TWIST_FRAMEWORK_VIEWS), $arrTags);
        //return $this->resUser->viewExtension('devices_form');
    }

	/**
	 * Processes the requested to forget a users connected device and or rename a users connected device.
	 */
    public function postDeviceManager(){

        $arrUserData = Auth::current();

        if(array_key_exists('save-device',$_POST) && array_key_exists('device-name',$_POST)){
            Auth::SessionHandler()->editDevice($arrUserData['user_id'],$_POST['save-device'],$_POST['device-name']);
        }

        \Twist::redirect('./device-manager');
    }

	/**
	 * Registration form to allow a user to register for an account within the system. The registration form can be disabled within the frameworks settings for closed/invite only systems.
	 * @return string
	 */
    public function register(){
        return $this->resUser->viewExtension('registration_form');
    }

	/**
	 * Process the users registration request and then redirect onto the relevant page.
	 */
    public function postRegister(){

        //Process the register user request
        if(array_key_exists('register',$_POST) && $_POST['register'] != ''){

            $resUser = $this->resUser->create();
            $resUser->email($_POST['email']);
            $resUser->firstname($_POST['firstname']);
            $resUser->surname($_POST['lastname']);
            $resUser->level(10);

            $blContinue = true;

            if(\Twist::framework()->setting('USER_REGISTER_PASSWORD')){

                if($_POST['password'] === $_POST['confirm_password']){
                    $arrResponse = $resUser->password($_POST['password']);

                    if(!$arrResponse['status']){
                        \Twist::Session()->data('site-register_error_message',$arrResponse['message']);
                        $blContinue = false;
                    }
                }else{
                    \Twist::Session()->data('site-register_error_message','Your password and confirm password do not match');
                    $blContinue = false;
                }
            }else{
                $resUser->resetPassword();
            }

            //If the password configuration has passed all checks then continue
            if($blContinue){
                $intUserID = $resUser->commit();

                if($intUserID > 0){

                    //AUTO_LOGIN
                    if(\Twist::framework()->setting('USER_REGISTER_PASSWORD') && !\Twist::framework()->setting('USER_EMAIL_VERIFICATION') && \Twist::framework()->setting('USER_AUTO_AUTHENTICATE')){

                        //@todo redirect - test or work out best way of doing this
                        //$this->resUser->afterLoginRedirect(); --- set the value that this function uses, authenticate will do the redirect

                        //Authenticate the user (log them in)
                        $this->resUser->authenticate($_POST['email'],$_POST['password']);

                        //@todo redirect - test or work out best way of doing this
                        $this->resUser->afterLoginRedirect();
                    }else{
                        \Twist::Session()->data('site-register_message','Thank you for your registration, your password has been emailed to you');
                        unset( $_POST['email'] );
                        unset( $_POST['firstname'] );
                        unset( $_POST['lastname'] );
                        unset( $_POST['register'] );
                    }
                }else{
                    \Twist::Session()->data('site-register_error_message','Failed to register user');
                }
            }
        }
    }
}