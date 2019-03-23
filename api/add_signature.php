<?php
require_once "./common.php";

echo json_encode( addSignature() );

function addSignature() {
    global $conn, $payload;

    if ($payload["signin"]) {
    	$formtitle = "LBFsignin";
    	$formname = "Patient Sign In";
    } else {
    	$formtitle = "LBFsignout";
    	$formname = "Patient Sign Out";
    }

	runSQL("INSERT INTO lbf_data ( field_id, field_value ) VALUES ( '', '' )");
	$formid = $conn->insert_id;
	runSQL("DELETE FROM lbf_data WHERE form_id = '".$formid."' AND field_id = ''");

	runSQL("INSERT INTO forms (date, encounter, form_name, form_id, pid, user, groupname, authorized, formdir) values (NOW(), '".$payload["encounter"]."', '".$formname."', '".$formid."', '".$payload["pid"]."', '".$_SESSION['authUser']."', '".$_SESSION['authGroup']."', '0', '".$formtitle."')");

	runSQL("INSERT INTO lbf_data ( form_id, field_id, field_value ) VALUES ('".$formid."', 'signature', '<img src=\'".$payload["signature"]."\'>' )");

	return array("status" => true);
}

?>