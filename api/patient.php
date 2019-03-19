<?php
require_once "./common.php";

$result = json_encode(get_patient($payload["fname"], $payload["lname"], $payload["dob"]));

if ($result === "false") {
    http_response_code(404);
    echo json_encode( array( "error" => false ) );
    return;
}

echo $result;

/**
 * @param type $fname
 * @param type $lname
 * @param type $dob
 */
function get_patient($fname, $lname, $dob)
{
	global $conn;
    $queryPatientSQL = "SELECT pid, id, lname, fname, mname "
    	."FROM patient_data "
    	."WHERE "
    		."fname = '".$conn->real_escape_string($fname)."' AND "
    		."lname = '".$conn->real_escape_string($lname)."' AND "
    		."DOB = '".$conn->real_escape_string($dob)."' "
    	."ORDER BY lname ASC, fname ASC";
    return runSQL($queryPatientSQL);
}
?>