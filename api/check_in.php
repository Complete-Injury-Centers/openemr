<?php
require_once "./common.php";

echo json_encode( checkInAppointment($payload["eventData"]) );

function checkInAppointment($eventData) {
    global $conn;

    if ($event['pc_recurrtype'] != '0') {

	    $strQuery = "INSERT INTO openemr_postcalendar_events 
	            (pc_pid, pc_title, pc_hometext, pc_time, pc_eventDate, pc_endDate, pc_startTime, pc_endTime, pc_apptstatus, pc_catid, pc_aid, pc_facility, pc_billing_location, pc_duration, pc_multiple, pc_informant, pc_eventstatus, pc_sharing, pc_recurrtype, pc_recurrspec, pc_location) 
	            VALUES (" . $conn->real_escape_string($eventData["pid"]) . ",
	                    '" . $conn->real_escape_string($eventData["pc_title"]) . "' ,
	                    '" . $conn->real_escape_string($eventData["pc_hometext"]) . "' ,
	                    '" . date('Y-m-d H:i:s') . "',
	                    '" . $conn->real_escape_string($eventData["pc_eventDate"]) . "',
	                    '" . $conn->real_escape_string($eventData["pc_eventDate"]) . "',
	                    '" . $conn->real_escape_string($eventData["pc_startTime"]) . "',
	                    '" . $conn->real_escape_string($eventData["pc_endTime"]) . "',
	                    '@',
	                    '" . $conn->real_escape_string($eventData["pc_catid"]) . "',
	                    '" . $conn->real_escape_string($_SESSION['authUserID']) . "',
	                    '" . $conn->real_escape_string($eventData["pc_facility"]) . "',
	                    '" . $conn->real_escape_string($eventData["pc_facility"]) . "',
	                    '" . $conn->real_escape_string($eventData["pc_duration"]) . "',
	                    0,
	                    1,
	                    1,
	                    1,
	                    0,
	                    '" . $conn->real_escape_string(serialize(array("event_repeat_freq" => "",
					        "event_repeat_freq_type" => "",
					        "event_repeat_on_num" => "1",
					        "event_repeat_on_day" => "0",
					        "event_repeat_on_freq" => "0",
					        "exdate" => ""
	    				))) . "',
	                    '" . $conn->real_escape_string(serialize(array("event_location" => "",
					        "event_street1" => "",
					        "event_street2" => "",
					        "event_city" => "",
					        "event_state" => "",
					        "event_postal" => ""
	    				))) . "')";
    } else if ($event['pc_apptstatus'] == "@") {
    	$strQuery = "UPDATE openemr_postcalendar_events SET pc_apptstatus = '>' WHERE pc_eid = '".$eventData["pc_eid"]."'";
    } else {
    	$strQuery = "UPDATE openemr_postcalendar_events SET pc_apptstatus = '@' WHERE pc_eid = '".$eventData["pc_eid"]."'";
    }


    $status = runSQL($strQuery);

    if (!$status) {
    	http_response_code(400);
		echo json_encode(array("error" => false));
	    exit();
    }

	$eventDate = strtotime($eventData["pc_eventDate"]);

    /** Add the current appointment to the exclusion list for the repeating events */
    if ($event['pc_recurrtype'] != '0') {

		$pc_recurrspec = @unserialize($eventData["pc_recurrspec"]);
		$exdate = explode(",", $pc_recurrspec["exdate"]);
		$dateExists = array_search(date("Ymd", $eventDate), $exdate);

		if (!$dateExists) {
			$exdate[] = date("Ymd", $eventDate);
			$pc_recurrspec["exdate"] = implode(",", $exdate);
			runSQL("UPDATE openemr_postcalendar_events SET pc_recurrspec = '".$conn->real_escape_string(serialize($pc_recurrspec))."' WHERE pc_eid = '".$eventData["pc_eid"]."'");
		}

    }

    /** Create a new encounter for the check in */
    $todaysEncounters = runSQL("SELECT count(*) AS count FROM form_encounter WHERE pid = '" . $conn->real_escape_string($eventData["pid"]) . "' AND date = '".date("Y-m-d", $eventDate)." 00:00:00'");
    if ($todaysEncounters["count"] == 0) {

    	$newEncounterSQL = "INSERT INTO form_encounter SET " .
			"date = '".date("Y-m-d", $eventDate)." 00:00:00', " .
			"onset_date = '".date("Y-m-d", $eventDate)." 00:00:00', " .
			"reason = 'Front desk check in', " .
			"encounter = '".generateID()."', " .
			"provider_id = '".$conn->real_escape_string($eventData["uprovider_id"])."', " .
			"facility = '".$conn->real_escape_string($eventData["name"])."', " .
			"facility_id = '".$conn->real_escape_string($eventData["pc_facility"])."', " .
			"pid = '" . $conn->real_escape_string($eventData["pid"]) . "'";

		$result = runSQL($newEncounterSQL);
    }

    return array("status" => $status );
}

function generateID() {
	global $conn;

	$id = 0;

	if ($conn->multi_query("SELECT @id := id FROM sequences; UPDATE sequences SET id = @id + 1; SELECT @id + 1;")) {
	    do {
	        if ($result = $conn->store_result()) {
	            $id = $result->fetch_all()[0][0];
	            $result->free();
	        }
	    } while( $conn->more_results() && $conn->next_result() );
	}

	return $id;
}
?>