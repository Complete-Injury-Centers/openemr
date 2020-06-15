<?php

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

/** Import database credentials */
require_once "../sites/default/sqlconf.php";

/** Get POST body */
$payload = json_decode(file_get_contents('php://input'),1);

if ($payload["token"]) {
	session_id($payload["token"]);
}

$lifetime=86400;

ini_set("session.gc_maxlifetime", $lifetime);

session_start();
setcookie(session_name(),session_id(),time()+$lifetime);

date_default_timezone_set("America/Chicago");

/** Define required globals */
define("SALT_PREFIX_SHA1", '$SHA1$');

define("TBL_USERS", "users");
define("TBL_USERS_SECURE", "users_secure");
define("COL_PWD", "password");
define("COL_UNM", "username");
define("COL_ID", "id");
define("COL_SALT", "salt");
define("COL_ACTIVE", "active");

header("Content-Type: application/json; charset=UTF-8");

/** Initialize the DB connection used for the API */
$conn = new mysqli($sqlconf["host"], $sqlconf["login"], $sqlconf["pass"], $sqlconf["dbase"]);

/** Quit if the connection fails as the API depends on it */
if ($conn->connect_error) {
    die("Database connection failed");
}

if (!isset($disableAuthCheck) && !authCheckSession()) {
    http_response_code(401);
	echo json_encode(array("error" => "Invalid session."));
    exit();
}

function runSQL($sql, $reduceSingleArray = true) {
	global $conn;
	$result = $conn->query($sql);
	$data = [];

	if (is_bool($result)) {
		return $result;
	}

	if ($result->num_rows > 0) {
	    while($row = $result->fetch_assoc()) {
	        $data[] = $row;
	    }
	}

	if (count($data) === 0) {
		return false;
	} else if ($reduceSingleArray && count($data) === 1) {
		return $data[0];
	}

	return $data;
}

function authCheckSession() {
	global $conn;
    if (isset($_SESSION['authId'])) {
        $authDB = runSQL("select ".implode(",", array(TBL_USERS.".".COL_ID,
	                                            TBL_USERS.".".COL_UNM,
	                                            TBL_USERS_SECURE.".".COL_PWD,
	                                            TBL_USERS_SECURE.".".COL_ID))
                . " FROM ". implode(",", array(TBL_USERS,TBL_USERS_SECURE))
                . " WHERE ". TBL_USERS.".".COL_ID." = '".$conn->real_escape_string($_SESSION['authId'])."' "
                . " AND ". TBL_USERS.".".COL_UNM . "=" . TBL_USERS_SECURE.".".COL_UNM
                . " AND ". TBL_USERS.".".COL_ACTIVE . "=1");

        if ($_SESSION['authUser'] == $authDB['username']
            && $_SESSION['authPass'] == $authDB['password'] ) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

?>
