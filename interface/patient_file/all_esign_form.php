<?php
/**
 *
 * eSign all notes
 *
 * Copyright (C) 2020 angeling <angel-na@hotmail.es>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://opensource.org/licenses/gpl-license.php>;.
 *
 * @package OpenEMR
 * @author  angeling <angel-na@hotmail.es>
 * @link    http://www.open-emr.org
 */

use OpenEMR\Core\Header;

require_once('../globals.php');
require_once($GLOBALS['srcdir'].'/log.inc');
require_once($GLOBALS['srcdir'].'/acl.inc');
require_once($GLOBALS['srcdir'].'/sl_eob.inc.php');
include '../../library/ESign/Form/Controller.php';

function getDoctor($patient) {
    $query = "SELECT gacl_aro.name AS name, users.id AS userID, gacl_aro.value AS username, gacl_aro_groups.name AS user_role, facility.id AS facility_id,
        facility.name AS facility_name FROM gacl_aro LEFT JOIN gacl_groups_aro_map ON gacl_aro.id = gacl_groups_aro_map.aro_id
        LEFT JOIN gacl_aro_groups ON gacl_aro_groups.id = gacl_groups_aro_map.group_id
        LEFT JOIN users ON users.username = gacl_aro.value AND users.active = '1'
        LEFT JOIN facility ON facility.name = users.facility
        RIGHT JOIN patient_data ON facility.id = patient_data.refer_facilities AND patient_data.pid = ?
        ORDER BY user_role DESC LIMIT 1";

    $res = sqlStatement($query, array($patient));
    if($row = sqlFetchArray($res)) {
        return $row['userID'];
    }
    return $_SESSION['authUserID'];
}

function getEncounterDate($encounter) {
    $res = sqlStatement("SELECT date FROM form_encounter WHERE encounter=?", array($encounter));
    $row = sqlFetchArray($res);
    $enc_date = date('Y-m-d', strtotime($row['date']));
    $enc_time = date('H:i:s', strtotime($row['date']));
    $enc_time_hms = explode(":",$enc_time);
    $signInNote = sqlQuery("SELECT DATE_FORMAT(CONVERT_TZ(`date`, '+00:00', '-06:00'),'%d:%m:%Y:%I:%i:%s:%p') as `time` FROM forms WHERE formdir = 'LBFsignin' AND encounter = ". $encounter . " ORDER BY id LIMIT 1");
    $time = explode(':', sqlFetchArray($signInNote)['time']);
    $actual_date = explode(':',date("d:m:Y:H:i:s", (time() - 3600 * 6)));

    $date_h = $actual_date[3];
    //Check if the actual date is the same as the date on the DB encounter
    if($actual_date[0] > $time[0] or $actual_date[1] > $time[1] or $actual_date[2] > $time[2] or
      ($enc_time_hms[0] == "00" and $enc_time_hms[1] == "00" and $enc_time_hms[2] == "00")) {
        $date_h = rand(21, 23);
        $actual_date[4] = rand(0, 59);
        $actual_date[5] = rand(0, 59);
    }
    $enc_date .= " ".$date_h.":".$actual_date[4].":".$actual_date[5];
    return $enc_date;
}

function sign($userId, $tableId, $encounter) {
    $statement = "INSERT INTO `esign_signatures` ( `tid`, `table`, `uid`, `datetime`, `is_lock`, `hash`, `amendment`, `signature_hash` ) ";
    $statement .= "VALUES ( ?, ?, ?, ?, ?, ?, ?, ? ) ";
    // Create a hash of the signable object so we can verify it's integrity
    $hash = hash("sha1","encounter");
    $isLock = 1;
    $date = getEncounterDate($encounter);
    $tableName = "forms";
    $amendment = $_POST['amendment'];
    
    // Create a hash of the signature data itself
    $signature = array(
        $tableId,
        $tableName,
        $userId,
        $isLock,
        $hash,
        $amendment
    );
    $signatureHash = hash("sha1",$signature[0].$signature[1].$signature[2].$signature[3].$signature[4].$signature[5]);

    array_splice($signature, 3, 0, $date);

    // Append the hash of the signature data to the insert array before we insert
    $signature[]= $signatureHash;
    $id = sqlInsert($statement, $signature);

    if($id === false) {
        throw new \Exception("Error occured while attempting to insert a signature into the database.");
    }

    return $id;
}

// Find all encounters that don't have a signable item
function checkNotes($patient) {
    $statement = "SELECT date, encounter FROM form_encounter WHERE pid = ?
        AND encounter NOT IN (SELECT DISTINCT encounter FROM forms WHERE authorized = 1 AND pid = ? AND deleted = 0
        AND form_name IN (SELECT DISTINCT grp_title FROM layout_group_properties WHERE grp_title != '')
        AND id NOT IN (SELECT DISTINCT encounter FROM forms))";
    $res = sqlStatement($statement, array($patient, $patient));

    echo "</br></br>";
    while($row = sqlFetchArray($res)) {
        $date_enc = explode(" ", $row['date']);
        echo "<p style='color:red'>The encounter <b>".$date_enc[0]." (".$row['encounter'].")</b> can not e-sign, make note and try again.</p>";
    }
    return sqlNumRows($res) == 0;
}

function checkSignEncounter($encounter) {
    $today = date("Y:m:d:h:i");
    $today = explode(":", $today);
    
    $signDate = sqlQuery("SELECT DATE_FORMAT(CONVERT_TZ(`date`, '+00:00', '-06:00'),'%Y:%m:%d:%I:%i:%s') as `time` FROM forms WHERE form_name = 'Patient Sign In' AND encounter = ". $encounter . " ORDER BY id LIMIT 1");
    $time = explode(':',$signDate ? $signDate['time'] : '');
    if($today[0] > $time[0] or $today[1] > $time[1] or $today[2] > $time[2] or
       $today[0] < $time[0] or $today[1] < $time[1] or $today[2] < $time[2]){
        return true;
    } elseif($today[0] == $time[0] and $today[1] == $time[1] and $today[2] == $time[2]) {
        if(($time[3] + 1) == $today[3]) {
            if($today[4] >= $time[4]) {
                return true;
            } else {
                echo "<p>It's ".$today[3].":".$today[4].", you can e-sign after ".($time[3]+1).":".($time[4])."</p>";
                return false;
            }
        } elseif(($time[3] + 1) > $today[3]) {
            return true;
        } else {
            echo "<p>It's ".$today[3].":".$today[4].", you can e-sign after ".($time[3]+1).":".($time[4])."</p>";
            return false;
        }
    }
}
?>

<html>
<head>
    <?php Header::setupHeader(['opener']);
    date_default_timezone_set("America/Chicago");?>
    <title><?php echo xlt('All eSign form'); ?></title>
</head>

<body class="body_top">
    <form id='esign-signature-form' method='post' action='all_esign_form.php?patient=<?php echo attr($patient) ?>' name="signAllNotes">
        <div class="esign-signature-form-element">
            <span id='esign-signature-form-prompt'><?php echo xlt("Your password is your signature"); ?></span>
        </div>

        <div class="esign-signature-form-element">
            <label for='password'><?php echo xlt('Password');?></label>
            <input type='password' id='password' name='password' size='10' />
        </div>

        <div class="esign-signature-form-element" style="margin:3px 0px;">
            <textarea name='amendment' id='amendment' style='width:100%' placeholder='<?php echo xlt("Enter an amendment..."); ?>'></textarea>
        </div>

        <div class="esign-signature-form-element">
            <div class="btn-group" style="margin:10px 0px;">
                <input name='button_sub' value="<?php echo xlt('eSign all notes'); ?>" type="submit" class="btn btn-lg btn-save btn-default" />
                <a href='#' class="btn btn-lg btn-link btn-cancel" id='esign-back-button' onclick="dlgclose(); "><?php echo xlt('Cancel');?></a>
            </div>
            <?php
            if(isset($_POST['button_sub'])) {
                // user name but not uid
                $user = htmlentities($_SESSION['authUser'],ENT_QUOTES,'utf-8');
                $userId = $_SESSION['authUserID'];
                $patient = $_SESSION['pid'];
                $pass = $_POST['password'];
                
                if(confirm_user_password($user,$pass)) {
                    // check first if all encounters have at least a note
                    if(checkNotes($patient)) {
                        // check id rows with the pid
                        $close_time = false;
                        $allSigned = true;
                        $res = sqlStatement("SELECT * FROM forms where authorized = 1 and pid = ? and deleted = 0 AND form_name IN (SELECT DISTINCT grp_title FROM layout_group_properties WHERE grp_title != '')", $patient);
                        while($rows = sqlFetchArray($res)) {
                            // check if exists id with tid
                            $signed = true;
                            $locked = sqlStatement("SELECT * FROM esign_signatures where tid = ?", $rows['id']);
                            $count_signatures = sqlNumRows($locked);  // count signatures
                            $date_enc = explode(" ", $rows['date'])[0];

                            if($count_signatures == 0) {
                                $allSigned = $signed = false;
                            }
                            if($count_signatures > 1) { // check if the asignature has more than one sign
                                echo "<p style='color:red'>The encounter <b>".$date_enc." (".$rows['encounter'].")</b> has more than one singature, please review and check again.</p>";
                                $close_time = true;
                                break;
                            }
                            if(!$signed) { // if flag is false, insert sign in esign_asignature table
                                if(checkSignEncounter($rows['encounter'])) { // before esign, need check the parameters
                                    $doctorFacility = getDoctor($patient);
                                    sign($doctorFacility, $rows['id'], $rows['encounter']);
                                } else {
                                    echo "<p style='color:red'>The encounter <b>".$date_enc." (".$rows['encounter'].")</b> is too close to sign.</p>";
                                    $close_time = true;
                                    break;
                                }
                            }
                        }

                        if(!$close_time) {
                            if($allSigned) {
                                echo "<script>alert('All eSigns were already signed');</script>";
                            } else {
                                echo "<script>alert('The eSigns are signed now');</script>";
                            }
                            echo "<script>dlgclose();</script>";
                        }
                    }
                } else {
                    echo "<br /><p style='color:red;'>Please check your password</p>";
                }
            }
            ?>
            <input type='hidden' id='cancel' name='cancel' value='<?php echo $GLOBALS['srcdir']." ".$_SESSION['authUser']." ".$_SESSION['authUserID'] ?>' />
        </div>
        <input type='hidden' id='userId' name='userId' value='<?php echo $_SESSION['authUserID'] ?>' />
    </form>
</body>
</html>
