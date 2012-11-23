<?php

class LoginController extends PHPGController
{

    /**
     * Displays Log-in page
     *
     * @return void
     */
    public function actionIndex()
    {
        $ret_location = Yii::app()->request->getQuery('redir', Yii::app()->homeUrl);
        $this->addscripts('login');

        $this->pageTitle = 'הזדהות לאתר לימוד PHP';
        $this->description = 'עמוד הזדהות וכניסה למערכת';
        $this->keywords = 'הזדהות';

        $this->render('login', ['return_location' => $ret_location]);
    }

    
    /**
     * Password recovery form and validation
     *
     * @return void
     */
    public function actionRecover()
    {
        $this->pageTitle = 'שחזור סיסמה';
        $this->description = 'שחזור סיסמה באתר לימוד PHP';
        $this->keywords = 'שחזור, סיסמה';

        $this->addscripts('login');
        $this->render('passwordRecoveryForm');

    }

    /**
     * Handles submission of the request pw recovery form
     *
     * @return void
     */
    public function actionRecoverSubmit()
    {

        $login = Yii::app()->request->getPost('login');
        $email = Yii::app()->request->getPost('email');

        $recoveryModel = new PasswordRecovery();
        $ip = Yii::app()->request->getUserHostAddress();

        try
        {
            $recoveryResult = $recoveryModel->requestRecovery($login, $email, $ip);
        }
        catch(Exception $e)
        {
            echo 'חלה שגיאה לא מוכרת כלשהי. נסו שוב מאוחר יותר';
            $logmsg = "Password recovery error: " . $e->getMessage();
            Yii::log($logmsg, CLogger::LEVEL_ERROR);
            return;
        }

        switch($recoveryResult)
        {
            case PasswordRecovery::ERROR_INVALID_EMAIL:
                echo 'האימייל שהוזן שגוי';
                break;

            case PasswordRecovery::ERROR_USER_NOT_FOUND:
                echo 'משתמש עם שם כזה לא רשום במערת';
                break;

            case PasswordRecovery::ERROR_NONE:
                echo 'מייל עם הוראות לשחזור סיסמה נשלח לכתובת המייל שלך';
                break;

            default:
                echo 'חלה תקלה במערכת. עמכם הסליחה';
                break;
        }




    }

    
    /**
     * Action to be called when a user clicks recover password link in the mail
     *
     * @throws CHttpException
     * @return void
     */
    public function actionResetUrl()
    {
        $id = Yii::app()->request->getQuery('id');
        $key = Yii::app()->request->getQuery('key');

        $recoveryResult = PasswordRecovery::model()->recover($id, $key);

        switch($recoveryResult)
        {
            case PasswordRecovery::ERROR_INVALID_KEY:
                throw new CHttpException(404);
                break;

            case PasswordRecovery::ERROR_RECOVER_TIMEOUT:
                $this->actionRecover();
                break;

            case PasswordRecovery::ERROR_NONE:

                $this->addscripts('login');

                Yii::app()->clientScript->registerScript(
                    'homepage',
                    'var homepage_url="'.Yii::app()->homeUrl.'"; ',
                    CClientScript::POS_END
                );

                $this->render('changePassword');
                break;
        }
    }
    
    
    public function actionChangepw()
    {
    	if(Yii::app()->request->getIsAjaxRequest() && !Yii::app()->user->isguest)
    	{
    		$password = Yii::app()->request->getPost('pass');
    		if(empty($password)) return;
    		
    		$salt = Helpers::randString(22);
    		$password = WebUser::encrypt_password($password, $salt);
    		
    		User::model()->updateByPk(Yii::app()->user->id, array('password' => $password, 'salt' => $salt));
    		Yii::app()->user->setFlash('successPwChange', 'סיסמתך שונת בהצלחה');    
    	}
    }
    
    
    /**
     * Regular log-in action, called via ajax submit from the login form.
     * @throws CHttpException 
     */
    public function actionLogin()
    {
        $username = Yii::app()->request->getPost('user');
        $password = Yii::app()->request->getPost('pass');


        if(empty($username) || empty($password))
            return;


        $identity = new DbUserIdentity($username, $password);
        $authStatus = $identity->authenticate();


        switch ($authStatus)
        {
            case DbUserIdentity::ERROR_IP_LOCKED:
                echo 'ביצעתם יותר מדי נסניונות התחברות. נסו שוב בעוד שעה';
                break;

            case CUserIdentity::ERROR_PASSWORD_INVALID:
                echo 'סיסמה שגויה';
                break;

            case CUserIdentity::ERROR_USERNAME_INVALID:
                echo 'שם משתמש שגוי';
                break;

            case CUserIdentity::ERROR_NONE:
                $loginDuration = Yii::app()->params['login_remember_me_duration'];
                Yii::app()->user->login($identity, $loginDuration);
                $this->updateExternalAuthInfo();
                echo 'ok';
                break;

            default:
                echo 'שגיאה לא מוכרת';

        }

    }
    
    
    
    
    
    
     /**
     * Fired when the user decides to login with external auth provider.  
     */
    public function actionExternalLogin()
    {
    	
        if (isset($_GET['service'])) 
        {
        	$backto = Yii::app()->request->getQuery('backto');
        	if($backto) Yii::app()->session['backto'] =  $backto;
            $this->authWithExternalProvider($_GET['service']);
        }
        else
        {
            $this->redirect(array('index'));
        }
    }
    
    
    /**
     * Attempts to authenticate the user using external oAuth provider
     * @param string $providerName
     */
    private function authWithExternalProvider($providerName)
    {
        /** @var CUserIdentity $identity  */
        $identity = Yii::app()->eauth->getIdentity($providerName);

        if($identity->authenticate() && $identity->isAuthenticated)
            $this->externalAuthSucceeded($identity);
        else
            $this->externalAuthFailed($identity);
    }
    
    
    
    /**
     * Called on external Authentication success
     * @param IAuthService $provider 
     */
    private function externalAuthSucceeded(IAuthService $provider)
    {
       $identity = new ServiceUserIdentity($provider) ;

       // Did someone use this external ID in the past?
       if($identity ->isKnownUser() )
       {
           Yii::app()->user->login($identity);
           $this->redirect(Yii::app()->session['backto'] ?: array('homepage/index'));
       }
       // external auth succeeded, but we don't know whom do this external ID belongs to
       else
       {
       		$userInfo = $provider->getAttributes();
       		$externalAuthProviders = Yii::app()->session['externalAuth'];
       		
       		if($externalAuthProviders === null) $externalAuthProviders = array();
       		$externalAuthProviders[$provider->getServiceName()] = $userInfo;
       		Yii::app()->session['externalAuth'] = $externalAuthProviders;
       		
       		$return_location = Yii::app()->session['backto'] ?: Yii::app()->request->getQuery('redir',   Yii::app()->homeUrl );
       		$this->addscripts('login');
       		$this->render('chooseNameAfterExternalLogin', array('provider' => $provider->getServiceName(), 'name' => $userInfo['name'], 'return_location' => $return_location));
       		
       }
    }
    
    private function externalAuthFailed(IAuthService $serviceAuthenticator)
    {
        Yii::app()->user->setFlash('externalAuthFail', 'הזדהות באמצעות ' . $serviceAuthenticator->getServiceName() . ' נכשלה');
        $this->redirect(array('login/index'));
    }

    
    
    public function actionLogout()
    {
        Yii::app()->user->logout();
        $this->redirect(array('homepage/index'));
    }

    
    public function actionRegister()
    {
        $username = Yii::app()->request->getPost('reguser');
        $email = Yii::app()->request->getPost('regemail');
        
        
        try
        {              
        	$externalAuthData = Yii::app()->session['externalAuth'];
        	
        	// Allow registration only using oAuth external services
        	if(!is_array($externalAuthData) || sizeof($externalAuthData) < 1)
        	{
        		echo 'הרשמה ניתנן באמצעות פייסבוק';
        		return;
        	}
        	
        	// registration of new user means taking the existing, unregistered one and updating his name and info
            $user = new User();
            $user->scenario = 'register';
            $user->attributes = array('login' => $username, 'email' => $email);
            $user->reg_date = new SDateTime();
            $user->last_visit = new SDateTime();
            $user->salt = Helpers::randString(22);
            $user->password = WebUser::encrypt_password( Helpers::randString(22), $user->salt);
            $user->ip = Yii::app()->request->getUserHostAddress();
            

            try
            {
                $user->save();
            }
            catch(CDbException $e)
            {
                throw (false !== mb_strpos($e->getMessage(), 'Duplicate') ) ? new UsernameAlreadyTaken() : $e;
            }
            
            $allErrors = array();
            $errors = $user->getErrors();
            
            if(sizeof($errors) > 0)
            {
                foreach($errors as $fieldErrors)
                {
                    $allErrors = array_merge($allErrors, $fieldErrors);
                }
                echo '— ' . nl2br(e(implode("\r\n — ", $allErrors)));
            }
            else
            {
                $identity = new AuthorizedIdentity($user);
                Yii::app()->user->login($identity, Yii::app()->params['login_remember_me_duration']);
                $this->updateExternalAuthInfo();               
                echo 'ok';
            }
        }
        catch (UsernameAlreadyTaken $e)
        {
            echo  'שם משתמש זה תפוס';
        }
        catch (Exception $e)
        {
            echo 'שגיאת שרת בתהליך ההרשמה. אנה נסו במועד מאוחר יותר';
            Yii::log("Signup error : " . $e->getMessage(), CLogger::LEVEL_ERROR);
            
        }
        

    }
    
    
    /**
     * Takes external auth data from session 
     * and updates the user record with the corresponding external ID's
     */
    private function updateExternalAuthInfo()
    {
    	$externalAuthenticatedProviders = Yii::app()->session['externalAuth'];
    	if(is_array($externalAuthenticatedProviders) && sizeof($externalAuthenticatedProviders) > 0)
    	{
    		$userUpdateData = array();
    		$userInfoUpdateData = array();
    	
    		foreach($externalAuthenticatedProviders as $serviceName => $userinfo)
    		{
    			$userUpdateData[ ServiceUserIdentity::$service2fieldMap[$serviceName] ] = $userinfo['id'];
    			$userInfoUpdateData['real_name'] = $userinfo['name'];
    		}
    		$user = User::model()->updateByPk(Yii::app()->user->id, $userUpdateData);
    		return true;
    	}
    	else
    	{
    		return false;
    	}
    }

}


/**
 * Indicates the authentication attempt has been blocked to avoid brute-force 
 */
class UsernameAlreadyTaken extends Exception{}