<?php
require_once "./common.php";

$result = json_encode(get_patient());

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
function get_patient()
{
	global $conn;
    $queryPatientSQL = "SELECT * "
    	."FROM patient_data ORDER BY lname ASC, fname ASC";
    return runSQL($queryPatientSQL);
}
?>
