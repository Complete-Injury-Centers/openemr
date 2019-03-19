<?php
$disableAuthCheck = true;
require_once "./common.php";

$result = validate_user_password($payload["user"],$payload["pass"],"Default");

if (!$result) {
	http_response_code(401);
	echo json_encode( array( "error" => false ) );
	return;
}

echo $result;

/**
 *
 * @param type $username
 * @param type $password    password is passed by reference so that it can be "cleared out"
 *                          as soon as we are done with it.
 * @param type $provider
 */
function validate_user_password($username, $password, $provider)
{
	global $conn;
    $ip=$_SERVER['REMOTE_ADDR'];

    $valid=false;

    $getUserSecureSQL= " SELECT " . implode(",", array(COL_ID,COL_PWD,COL_SALT))
                    ." FROM ".TBL_USERS_SECURE
                    ." WHERE BINARY ".COL_UNM."='".$conn->real_escape_string($username)."'";
                    // Use binary keyword to require case sensitive username match
    $userSecure=runSQL($getUserSecureSQL);
    if (is_array($userSecure)) {
        $phash=oemr_password_hash($password, $userSecure[COL_SALT]);
        if ($phash!=$userSecure[COL_PWD]) {
            return false;
        }

        $valid=true;
    }

    $getUserSQL="select id, authorized, see_auth".
                        ", active ".
                        " from users where BINARY username = '".$conn->real_escape_string($username)."'";
    $userInfo = runSQL($getUserSQL);
    if ($userInfo['active'] != 1) {
//        newEvent('login', $username, $provider, 0, "failure: $ip. user not active or not found in users table");
        $password='';
        return false;
    }

    // Done with the cleartext password at this point!
    $password='';
    if ($valid) {
    	$authGroup = runSQL("select * from `groups` where user='".$conn->real_escape_string($username)."' and name='".$conn->real_escape_string($provider)."'");
        if ($authGroup) {
            $_SESSION['authUser'] = $username;
            $_SESSION['authPass'] = $phash;
            $_SESSION['authGroup'] = $authGroup['name'];
            $_SESSION['authUserID'] = $userInfo['id'];
            $_SESSION['authProvider'] = $provider;
            $_SESSION['authId'] = $userInfo{'id'};
            $_SESSION['userauthorized'] = $userInfo['authorized'];
            // Some users may be able to authorize without being providers:
            if ($userInfo['see_auth'] > '2') {
                $_SESSION['userauthorized'] = '1';
            }

//            newEvent('login', $username, $provider, 1, "success: $ip");
            $valid=true;
        } else {
//            newEvent('login', $username, $provider, 0, "failure: $ip. user not in group: $provider");
            $valid=false;
        }
    }
    return $valid ? json_encode( array( "token" => session_id() ) ) : false;
}

/**
 * Hash a plaintext password for comparison or initial storage.
 *
 * <pre>
 * This function either uses the built in PHP crypt() function, or sha1() depending
 * on a prefix in the salt.  This on systems without a strong enough built in algorithm
 * for crypt(), sha1() can be used as a fallback.
 * If the crypt function returns an error or illegal hash, then will die.
 * </pre>
 *
 * @param type $plaintext
 * @param type $salt
 * @return type
 */
function oemr_password_hash($plaintext, $salt)
{
    // if this is a SHA1 salt, the use prepended salt
    if (strpos($salt, SALT_PREFIX_SHA1)===0) {
        return SALT_PREFIX_SHA1 . sha1($salt.$plaintext);
    } else { // Otherwise use PHP crypt()
        $crypt_return = crypt($plaintext, $salt);
        if (($crypt_return == '*0') || ($crypt_return == '*1') || (strlen($crypt_return) < 6)) {
            // Error code returned by crypt or not hash, so die
            error_log("FATAL ERROR: crypt() function is not working correctly in OpenEMR");
            die("FATAL ERROR: crypt() function is not working correctly in OpenEMR");
        } else {
            // Hash confirmed, so return the hash.
            return $crypt_return;
        }
    }
}
?>
