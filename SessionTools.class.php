<?php
ini_set('session.use_only_cookies', 1);
define('DEFAULT_SESSION_NAME', 'PHPSESSID');
class SessionTools
{
    private $session_exists;

    /* CLASS CONSTRUCTOR */
    function __construct($session_name=DEFAULT_SESSION_NAME)
    {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on';
        $this->session_exists = false;
        session_name($session_name);
        session_set_cookie_params(0, '/', $https, true);
    }

    /* START THE SESSION */
    function start()
    {
        $this->session_exists = session_start();
        $_SESSION['ST_request_token'] = sha1(microtime(true));
    }

    /* CUSTOM FUNCTION TO CHECK FOR A VALID SESSION */
    function isValid()
    {
        if($this->session_exists) {
            if(isset($_SESSION['ST_request_token']))
                return true;
        }
        return false;
    }

    /* GET USER FINGERPRINT (USER_AGENT+IP) */
    function getFingerprint()
    {
        return hash('sha256', $_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR']);
    }

    /* PRINT HTML FIELD FOR TOKEN VALIDATION */
    function printTokenField()
    {
        echo '<input type="hidden" name="request_token" value="'.$_SESSION['ST_request_token'].'" />';
    }

    /* VALIDATE REQUEST TOKEN */
    function validateToken($user_submission)
    {
        return 0===strcmp($user_submission, $_SESSION['ST_request_token']);
    }

    /* REFRESH TOKEN */
    function refreshToken()
    {
        $_SESSION['ST_request_token'] = sha1(microtime(true).getFingerprint());
    }

    /* DESTROY THE SESSION */
    function destroy()
    {
        if($this->session_exists) {
            $_SESSION = array();
            if(ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 31536000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            $this->session_exists = false;
        }
    }

    /* CLASS DESTRUCTOR */
    function __destruct()
    {
        foreach($this as &$attr)
            $attr = null;
    }
}
?>
