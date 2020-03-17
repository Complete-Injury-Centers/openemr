<?php
require_once "./common.php";

/** Define required constants */
define('REPEAT_EVERY_DAY', 0);
define('REPEAT_EVERY_WEEK', 1);
define('REPEAT_EVERY_MONTH', 2);
define('REPEAT_EVERY_YEAR', 3);
define('REPEAT_EVERY_WORK_DAY', 4);
define('REPEAT_DAYS_EVERY_WEEK', 6);

/** Get the required global variables */
$global = runSQL("SELECT gl_value FROM globals WHERE gl_name = 'weekend_days'");
$GLOBALS['weekend_days'] = explode(',', $global["gl_value"]);

echo json_encode( fetchAppointments($payload["from"], $payload["to"], $payload["pid"], $payload["facility_id"]));

/**
 * @param type $pid
 */
function get_appointments($pid)
{
	global $conn;


	//date('Y-m-d', time());

    $queryAppointmentsSQL = "SELECT " .
        "e.pc_eventDate, e.pc_endDate, e.pc_startTime, e.pc_endTime, e.pc_duration, e.pc_recurrtype, e.pc_recurrspec, e.pc_recurrfreq, e.pc_catid, e.pc_eid, e.pc_gid, " .
        "e.pc_title, e.pc_hometext, e.pc_apptstatus, " .
        "p.fname, p.mname, p.lname, p.pid, p.pubpid, p.phone_home, p.phone_cell, " .
        "p.hipaa_allowsms, p.phone_home, p.phone_cell, p.hipaa_voice, p.hipaa_allowemail, p.email, " .
        "u.fname AS ufname, u.mname AS umname, u.lname AS ulname, u.id AS uprovider_id, " .
        "f.name, c.pc_catname, c.pc_catid, e.pc_facility " .
        "FROM openemr_postcalendar_events AS e " .
        "LEFT OUTER JOIN facility AS f ON e.pc_facility = f.id " .
        "LEFT OUTER JOIN patient_data AS p ON p.pid = e.pc_pid " .
        "LEFT OUTER JOIN users AS u ON u.id = e.pc_aid " .
        "LEFT OUTER JOIN openemr_postcalendar_categories AS c ON c.pc_catid = e.pc_catid " .
        "WHERE e.pc_pid = '".$conn->real_escape_string($pid)."' " .
        "ORDER BY e.pc_eventDate, e.pc_startTime";
	return runSQL($queryAppointmentsSQL);
}

/**
 * @param type $from
 * @param type $to
 * @param type $pid
 * @param type $facility_id
 */
function fetchAppointments($from_date, $to_date, $patient_id = null, $facility_id = null)
{
    global $conn;
    $where =
    "((e.pc_endDate >= '".$conn->real_escape_string($from_date)."' AND e.pc_eventDate <= '".$conn->real_escape_string($to_date)."' AND e.pc_recurrtype > '0') OR " .
    "(e.pc_eventDate >= '".$conn->real_escape_string($from_date)."' AND e.pc_eventDate <= '".$conn->real_escape_string($to_date)."')) AND " .
    "e.pc_pid = '".$conn->real_escape_string($patient_id)."'";


    if ($facility_id) {
        $where .= " AND e.pc_facility = ".$conn->real_escape_string($facility_id);
    }

    $query = "SELECT " .
    "e.pc_eventDate, e.pc_endDate, e.pc_startTime, e.pc_endTime, e.pc_duration, e.pc_recurrtype, e.pc_recurrspec, e.pc_recurrfreq, e.pc_catid, e.pc_eid, e.pc_gid, " .
    "e.pc_title, e.pc_hometext, e.pc_apptstatus, " .
    "p.fname, p.mname, p.lname, p.pid, p.pubpid, p.phone_home, p.phone_cell, " .
    "p.hipaa_allowsms, p.phone_home, p.phone_cell, p.hipaa_voice, p.hipaa_allowemail, p.email, " .
    "u.fname AS ufname, u.mname AS umname, u.lname AS ulname, u.id AS uprovider_id, " .
    "f.name, " .
    "c.pc_catname, c.pc_catid, e.pc_facility " .
    "FROM openemr_postcalendar_events AS e " .
    "LEFT OUTER JOIN facility AS f ON e.pc_facility = f.id " .
    "LEFT OUTER JOIN patient_data AS p ON p.pid = e.pc_pid " .
    "LEFT OUTER JOIN users AS u ON u.id = e.pc_aid " .
    "LEFT OUTER JOIN openemr_postcalendar_categories AS c ON c.pc_catid = e.pc_catid " .
    "WHERE $where " .
    "ORDER BY e.pc_eventDate, e.pc_startTime";

    $res = runSQL($query, false);

    foreach ($res as &$event) {
        $stopDate = ($event['pc_endDate'] <= $to_date) ? $event['pc_endDate'] : $to_date;
        switch ($event['pc_recurrtype']) {
            case '0':
                $events2[] = $event;
                break;
            case '1':
            case '3':
                $event_recurrspec = @unserialize($event['pc_recurrspec']);
                if (checkEvent($event['pc_recurrtype'], $event_recurrspec)) { break; }
                $rfreq = $event_recurrspec['event_repeat_freq'];
                $rtype = $event_recurrspec['event_repeat_freq_type'];
                $exdate = $event_recurrspec['exdate'];
                list($ny,$nm,$nd) = explode('-', $event['pc_eventDate']);
                $occurance = $event['pc_eventDate'];
                while ($occurance < $from_date) {
                    $occurance =& __increment($nd, $nm, $ny, $rfreq, $rtype);
                    list($ny,$nm,$nd) = explode('-', $occurance);
                }
                while ($occurance <= $stopDate) {
                    $excluded = false;
                    if (isset($exdate)) {
                        foreach (explode(",", $exdate) as $exception) {
                            if (preg_replace("/-/", "", $occurance) == $exception) {
                                $excluded = true;
                            }
                        }
                    }
                    if ($excluded == false) {
                        $event['pc_eventDate'] = $occurance;
                        $event['pc_endDate'] = '0000-00-00';
                        $events2[] = $event;
                    }
                    $occurance =& __increment($nd, $nm, $ny, $rfreq, $rtype);
                    list($ny,$nm,$nd) = explode('-', $occurance);
                }
                break;
            case '2':
                $event_recurrspec = @unserialize($event['pc_recurrspec']);
                if (checkEvent($event['pc_recurrtype'], $event_recurrspec)) { break; }
                $rfreq = $event_recurrspec['event_repeat_on_freq'];
                $rnum  = $event_recurrspec['event_repeat_on_num'];
                $rday  = $event_recurrspec['event_repeat_on_day'];
                $exdate = $event_recurrspec['exdate'];
                list($ny,$nm,$nd) = explode('-', $event['pc_eventDate']);
                if (isset($event_recurrspec['rt2_pf_flag']) && $event_recurrspec['rt2_pf_flag']) {
                    $nd = 1;
                }
                $occuranceYm = "$ny-$nm";
                $from_dateYm = substr($from_date, 0, 7);
                $stopDateYm = substr($stopDate, 0, 7);
                while ($occuranceYm < $from_dateYm) {
                    $occuranceYmX = date('Y-m-d', mktime(0, 0, 0, $nm+$rfreq, $nd, $ny));
                    list($ny,$nm,$nd) = explode('-', $occuranceYmX);
                    $occuranceYm = "$ny-$nm";
                }
                while ($occuranceYm <= $stopDateYm) {
                    $dnum = $rnum;
                    do {
                        $occurance = Date_Calc::NWeekdayOfMonth($dnum--, $rday, $nm, $ny, $format = "%Y-%m-%d");
                    } while ($occurance === -1);
                    if ($occurance >= $from_date && $occurance <= $stopDate) {
                        $excluded = false;
                        if (isset($exdate)) {
                            foreach (explode(",", $exdate) as $exception) {
                                if (preg_replace("/-/", "", $occurance) == $exception) {
                                    $excluded = true;
                                }
                            }
                        }
                        if ($excluded == false) {
                            $event['pc_eventDate'] = $occurance;
                            $event['pc_endDate'] = '0000-00-00';
                            $events2[] = $event;
                        }
                    }
                    $occuranceYmX = date('Y-m-d', mktime(0, 0, 0, $nm+$rfreq, $nd, $ny));
                    list($ny,$nm,$nd) = explode('-', $occuranceYmX);
                    $occuranceYm = "$ny-$nm";
                }
                break;
        }
    }
    return $events2 ? $events2 : array();
}

function checkEvent($recurrtype, $recurrspec)
{
    $eFlag = 0;
    switch ($recurrtype) {
        case 1:
        case 3:
            if (empty($recurrspec['event_repeat_freq']) || !isset($recurrspec['event_repeat_freq_type'])) {
                $eFlag = 1; }
            break;
        case 2:
            if (empty($recurrspec['event_repeat_on_freq']) || empty($recurrspec['event_repeat_on_num']) || !isset($recurrspec['event_repeat_on_day'])) {
                $eFlag = 1; }
            break;
    }
    return $eFlag;
}

function &__increment($d, $m, $y, $f, $t)
{
    if ($t == REPEAT_EVERY_DAY) {
        return date('Y-m-d', mktime(0, 0, 0, $m, ($d+$f), $y));
    } elseif ($t == REPEAT_EVERY_WORK_DAY) {
        $orig_freq = $f;
        for ($daycount=1; $daycount<=$orig_freq; $daycount++) {
            $nextWorkDOW = date('w', mktime(0, 0, 0, $m, ($d+$daycount), $y));
            if (is_weekend_day($nextWorkDOW)) {
                $f++;
            }
        }
        $nextWorkDOW = date('w', mktime(0, 0, 0, $m, ($d+$f), $y));
        if (count($GLOBALS['weekend_days']) === 2) {
            if ($nextWorkDOW == $GLOBALS['weekend_days'][0]) {
                $f+=2;
            } elseif ($nextWorkDOW == $GLOBALS['weekend_days'][1]) {
                 $f++;
            }
        } elseif (count($GLOBALS['weekend_days']) === 1 && $nextWorkDOW === $GLOBALS['weekend_days'][0]) {
            $f++;
        }
        return date('Y-m-d', mktime(0, 0, 0, $m, ($d+$f), $y));
    } elseif ($t == REPEAT_EVERY_WEEK) {
        return date('Y-m-d', mktime(0, 0, 0, $m, ($d+(7*$f)), $y));
    } elseif ($t == REPEAT_EVERY_MONTH) {
        return date('Y-m-d', mktime(0, 0, 0, ($m+$f), $d, $y));
    } elseif ($t == REPEAT_EVERY_YEAR) {
        return date('Y-m-d', mktime(0, 0, 0, $m, $d, ($y+$f)));
    } elseif ($t == REPEAT_DAYS_EVERY_WEEK) {
        $old_appointment_date = date('Y-m-d', mktime(0, 0, 0, $m, $d, $y));
        $next_appointment_date = getTheNextAppointment($old_appointment_date, $f);
        return $next_appointment_date;
    }
}

function getTheNextAppointment($appointment_date, $freq)
{
    $day_arr = explode(",", $freq);
    $date_arr = array();
    foreach ($day_arr as $day) {
        $day = getDayName($day);
        $date = date('Y-m-d', strtotime("next " . $day, strtotime($appointment_date)));
        array_push($date_arr, $date);
    }
    $next_appointment = getEarliestDate($date_arr);
    return $next_appointment;
}


function getDayName($day_num)
{
    if ($day_num == "1") {
        return "sunday";
    }
    if ($day_num == "2") {
        return "monday";
    }
    if ($day_num == "3") {
        return "tuesday";
    }
    if ($day_num == "4") {
        return "wednesday";
    }
    if ($day_num == "5") {
        return "thursday";
    }
    if ($day_num == "6") {
        return "friday";
    }
    if ($day_num == "7") {
        return "saturday";
    }
}

 /**
 * Check if day is weekend day
 * @param (int) $day
 * @return boolean
 */
function is_weekend_day($day)
{
    if (in_array($day, $GLOBALS['weekend_days'])) {
        return true;
    } else {
        return false;
    }
}

function getEarliestDate($date_arr)
{
    $earliest = ($date_arr[0]);
    foreach ($date_arr as $date) {
        if (strtotime($date) < strtotime($earliest)) {
            $earliest = $date;
        }
    }
    return $earliest;
}
?>