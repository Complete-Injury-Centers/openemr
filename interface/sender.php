<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// require_once("../interface/globals.php");
require_once("$srcdir/PHPMailer/src/Exception.php");
require_once("$srcdir/PHPMailer/src/PHPMailer.php");
require_once("$srcdir/PHPMailer/src/SMTP.php");

function sendMail($send_to, $subject, $body, $email = "", $pass = "", $title = "EMR CIC", $replyto = "", $titleReply = "EMR CIC") {
    // Instantiation and passing `true` enables exceptions
    $mail = new PHPMailer(true);

    // $email = $email ? $email : $_ENV['EMAIL_USERNAME'];
    // $pass  = $pass  ? $pass  : $_ENV['EMAIL_PASSWORD'];

    if(isset($_ENV['DEBUG'])) {
        echo '<script>console.log("original: '.implode(", ",$send_to).'")</script>';
        echo '<script>console.log("'.preg_replace('/\'/', ' ', $subject).'")</script>';
        echo '<script>console.log("'.preg_replace('/\'/', ' ', $body).'")</script>';
        $send_to = [$_ENV['DEV_EMAIL']];
    }
    
    try {
        //Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;//SMTP::DEBUG_CONNECTION;// Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $_ENV['HOST'];                          // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $email;                                 // SMTP username
        $mail->Password   = $pass;                                  // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 587;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

        //Recipients
        $mail->setFrom($email, $title);                             // Add email sent from
        foreach($send_to as $addr) {
            $mail->addAddress($addr);                               // Add a recipient
        }
        
        if($replyto) {
            $mail->addReplyTo($replyto, $titleReply);
        }

        //Attachments
        $mail->AddEmbeddedImage($GLOBALS['srcdir'].'/../interface/img/book.jpg', 'book');
        $mail->AddEmbeddedImage($GLOBALS['srcdir'].'/../interface/img/map.jpg', 'map');
        $mail->AddEmbeddedImage($GLOBALS['srcdir'].'/../interface/img/Logo-Small.png', 'logo');

        // Content
        $mail->isHTML(true);                                        // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        echo "<script>console.log('Message has been sent')</script>";
    } catch (Exception $e) {
        // echo "<script>console.log('Message could not be sent. Mailer Error: {$mail->ErrorInfo}')</script>";
    }
}

function sendLOPRequest($pid) {
    $send_to = findLawyerLOP($pid);

    $patient = getPatient($pid);
    $subject = "CIC LOP REQUEST";
    
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / DOB: ".$patient['DOB'];}

    $body = greeting();
    $org = getOrganization($pid);
    if($org['organization']) {$body .= " ".$org['organization'];}
    $body .= "!<br /><br />";
    $body .= "This is a friendly LOP request. Please send LOP via email <a href='mailto:records@cic.clinic'>records@cic.clinic</a> for ";
    if($patient['fname']) {$body .= $patient['fname'];}
    if($patient['lname']) {$body .= " " . $patient['lname'];}
    if($patient['DOB']) {$body .= ", DOB: " . $patient['DOB'];}
    elseif($patient['doi']) {$body .= ", DOI: " . $patient['doi'];}
    $body .= ". ";

    $facil = getLocation($pid);
    $body .= "Patient is being seen at our ";
    $body .= trim(explode("-", $facil['name'])[0]) . ".";
    $body .= "<br /><br />";
    $body .= "Please contact us with any issues. We are here to help.";
    
    sendMail($send_to, $subject, $body . signatureRecords(), $_ENV['EMAIL_USER_REC'], $_ENV['EMAIL_PASS_REC'], 'CIC LOP REQUEST');
}

function sendLawyerAppointmentAlert($eid) {
    $patient = getInfoAppointmentAll($eid);

    $send_to = findLawyerAlert($patient['pid'], true);

    $subject = "CONFIRMATION OF FIRST APPOINTMENT";
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / DOB: ".$patient['DOB'];}
    $facil = getLocation($patient['pid']);
    $subject .= " / " . $facil['city'];
    $subject .= " / " . $patient['pc_eventDate'] . " " . $patient['pc_startTime'];

    $body = greeting() . "!";
    $body .= "<br /><br />";
    $body .= "This email is to confirm patient's first appointment scheduled with us.";
    $body .= "<br /><br />";
    $body .= $subject;
    $body .= "<br /><br />";
    $body .= "Please let us know if we can help in any way.";

    sendMail($send_to, $subject, $body . signatureSchedule(), $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function sendClinicAppointmentAlert($eid) {
    $patient = getInfoAppointmentAll($eid);

    $send_to = getLocationEmail($patient['pid']);

    $subject = "CIC NEW PATIENT 1ST VISIT CONFIRMATION";
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / DOB: ".$patient['DOB'];}
    $facil = getLocation($patient['pid']);
    $subject .= " / " . $facil['city'];
    $subject .= " / " . $patient['pc_eventDate'] . " " . $patient['pc_startTime'];

    $body = "This email is to confirm new patient scheduled.";
    $body .= "<br /><br />";
    $body .= $subject;
    $body .= "<br /><br />";
    $body .= "We appreciate working with you!";

    sendMail($send_to, $subject, $body . signatureSchedule(), $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function sendLawyerAppointmentNotice($eid) {
    $patient = getInfoAppointmentAll($eid);

    $send_to = findLawyerAlert($patient['pid']);

    $subject = "NOTICE OF CLIENT'S APPOINTMENT";
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / DOB: ".$patient['DOB'];}
    $facil = getLocation($patient['pid']);
    $subject .= " / " . $facil['city'];
    $subject .= " / " . $patient['pc_eventDate'] . " " . $patient['pc_startTime'];

    $body = greeting() . "!";
    $body .= "<br /><br />";
    $body .= "This email is to kindly notify you of our mutual client's scheduled visit with us.";
    $body .= "<br /><br />";
    $body .= $subject;
    $body .= "<br /><br />";
    $body .= "Please let us know if we can help in any way.";

    sendMail($send_to, $subject, $body . signatureSchedule(), $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function sendClinicAppointmentNotice($eid) {
    $patient = getInfoAppointmentAll($eid);

    $send_to = getLocationEmail($patient['pid']);

    $subject = "CIC - NOTICE OF PATIENT'S APPOINTMENT";
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / DOB: ".$patient['DOB'];}
    $facil = getLocation($patient['pid']);
    $subject .= " / " . $facil['city'];
    $subject .= " / " . $patient['pc_eventDate'] . " " . $patient['pc_startTime'];

    $body = "This email is to kindly notify you of this patient's scheduled visit with your clinic.";
    $body .= "<br /><br />";
    $body .= $subject;
    $body .= "<br /><br />";
    $body .= "We appreciate working with you!";

    sendMail($send_to, $subject, $body . signatureSchedule(), $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function sendImportantEmail($pid) {
    $send_to = findDoctor($pid);

    $subject = "Patient Notification";

    $patient = getPatient($pid);
    $patient_name = $patient['lname'] . ", ". $patient['fname'] . (isset($patient['mname']) ? " " . $patient['mname'] : "");

    $body = "Your patient <b>" . $patient_name . "</b> has an alert in messages section.";

    sendMail($send_to, $subject, $body . signatureSchedule(), $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function sendNoteEmail($pid, $note) {
    $patient = getPatient($pid);
    $send_to = findLawyerNotice($pid);

    $subject = "CIC Progress Note Addition";
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / DOB: ".$patient['DOB'];}

    $body = "";
    $org = getOrganization($pid);
    if($org['organization']) {$body .= " ".$org['organization'];}
    $body .= "!<br /><br />";

    $body .= "This email is to kindly notify you of a patient progress note addition that we wanted to share with you for ";
    if($patient['fname']) {$body .= " / ".$patient['fname'];}
    if($patient['lname']) {$body .= " ".$patient['lname'];}
    if($patient['DOB']) {$body .= " / DOB: ".$patient['DOB'];}
    $body .= "<br /><br />";

    $body .= "<b>" . $note . "</b><br /><br />";

    $body .= "We appreciate working with you!<br /><br />";
    $body .= "Let us know if there is anything we can do to help.";

    sendMail($send_to, $subject, $body . signatureSchedule(), $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function notifyBack($pid, $note) {
    $patient = getPatient($pid);
    $send_to = [$_ENV['EMAIL_USER_SCH']];

    $subject = "CIC Clinic to Back Office Note";
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / DOB: ".$patient['DOB'];}

    $body = $patient['fname'] . " " . $patient['lname'] . " / DOB: " . $patient['DOB'] . ",<br /><br />";

    $body .= "<b>" . $note . "</b><br /><br />";
    $body .= "Please address this note from clinic ASAP.";

    sendMail($send_to, $subject, $body . signatureSchedule(), $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function findDoctor($pid) {
    $res = sqlStatement("SELECT f.email FROM facility AS f LEFT JOIN patient_data AS p ON p.refer_facilities=f.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        $email = preg_replace('/\s+/', '', $row['email']);
        $email = explode(",", $email);
        return [$email[0]];
    }
    return [];
}

function findLawyerLOP($pid) {
    $res = sqlStatement("SELECT u.email_lop FROM users AS u LEFT JOIN patient_data AS p ON p.lawyer=u.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        $email = preg_replace('/\s+/', '', $row['email_lop']);
        $email = preg_replace('/:/', ',', $email);
        $emails = explode(",", $email);
        return $emails;
    }
    return [];
}

function findLawyerAlert($pid, $first = false) {
    $res = sqlStatement("SELECT u.email_visit_law,u.email_appoint FROM users AS u LEFT JOIN patient_data AS p ON p.lawyer=u.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        $email = preg_replace('/\s+/', '', $first ? $row['email_appoint'] : $row['email_visit_law']);
        $email = preg_replace('/:/', ',', $email);
        return explode(",", $email);
    }
    return [];
}

function findLawyerNotice($pid) {
    $res = sqlStatement("SELECT u.notify_lawyer FROM users AS u LEFT JOIN patient_data AS p ON p.lawyer=u.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        $email = preg_replace('/\s+/', '', $row['notify_lawyer']);
        $email = preg_replace('/:/', ',', $email);
        return explode(",", $email);
    }
    return [];
}

function getPatient($pid) {
    $res = sqlStatement("SELECT fname,mname,lname,DOB,doi FROM patient_data WHERE pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        return $row;
    }
    return "";
}

function getInfoAppointmentAll($eid) {
    $query = 'SELECT '.
        'e.pc_eventDate, e.pc_endDate, e.pc_startTime, e.pc_endTime, e.pc_duration, e.pc_recurrtype, e.pc_recurrspec, e.pc_recurrfreq, e.pc_catid, e.pc_eid, '.
        'e.pc_gid, e.pc_title, e.pc_hometext, e.pc_apptstatus, '.
        'p.fname, p.mname, p.lname, p.pid, p.pubpid, p.phone_home, p.phone_cell, '.
        'p.hipaa_allowsms, p.hipaa_voice, p.DOB, p.doi, '.
        'p.hipaa_allowemail, p.email, u.fname AS ufname, u.mname AS umname, u.lname AS ulname, u.id AS uprovider_id, '.
        'f.name, '.
        'c.pc_catname, c.pc_catid, e.pc_facility '.
        'FROM openemr_postcalendar_events AS e '.
        'LEFT OUTER JOIN facility AS f ON e.pc_facility = f.id '.
        'LEFT OUTER JOIN patient_data AS p ON p.pid = e.pc_pid '.
        'LEFT OUTER JOIN users AS u ON u.id = e.pc_aid '.
        'LEFT OUTER JOIN openemr_postcalendar_categories AS c ON c.pc_catid = e.pc_catid '.
        'WHERE e.pc_eid='.$eid;
    $res = sqlStatement($query);
    if($row = sqlFetchArray($res)) {
        return $row;
    }
}

function getOrganization($pid) {
    $res = sqlStatement("SELECT u.organization FROM patient_data AS p JOIN users AS u ON p.lawyer=u.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        return $row;
    }
    return "";
}

function getLocation($pid) {
    $res = sqlStatement("SELECT f.city,f.name FROM facility AS f LEFT JOIN patient_data AS p ON p.refer_facilities=f.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        return $row;
    }
    return "";
}

function getLocationEmail($pid) {
    $res = sqlStatement("SELECT f.email FROM facility AS f LEFT JOIN patient_data AS p ON p.refer_facilities=f.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        $email = preg_replace('/\s+/', '', $row['email']);
        $email = preg_replace('/:/', ',', $email);
        $emails = explode(",", $email);
        return $emails;
    }
    return [];
}

function greeting() {
    $greeting = date("H") < 12 ? "Good Morning" : "Good Afternoon";
    $greeting = date("H") > 18 ? "Good Evening" : $greeting;

    return $greeting;
}

function signatureSchedule() {
    $s = "<br /><br /><br /><br />";

    $s .= "<b>Please send all reports and records requests to <a href='mailto:records@cic.clinic'>records@cic.clinic</a> or fax, " .
            "<a href='tel:214-853-5126'>214-853-5126</a>.</b><br /><br />";
    $s .= "<b>Here is quick video of Dr. Farzad introducing Complete Injury Centers.</b><br />";
    $s .= "<a href='https://www.youtube.com/watch?v=uo8tdPQ01mY'>https://www.youtube.com/watch?v=uo8tdPQ01mY</a><br /> <br />";

    $s .= "We appreciate working with you.<br /><br />";

    $s .= "<b>For your convenience:</b><br />";
    $s .= "<a href='mailto:schedule@cic.clinic'>schedule@cic.clinic</a><br />";
    $s .= "<a href='mailto:records@cic.clinic'>records@cic.clinic</a><br />";
    $s .= "<a href='mailto:reduction@cic.clinic'>reduction@cic.clinic</a><br />";
    $s .= "<a href='mailto:ashley@cic.clinic'>ashley@cic.clinic</a><br /><br />";

    $s .= "Have a Blessed Day!<br /><br />";

    $s .= "<b>Scheduling Team</b><br />";
    $s .= "<img src='cid:logo' /><br /><br />";

    $s .= "<b>Phone:</b> <a href='tel:(214) 666-6651'>(214) 666-6651</a> | <b>Fax:</b> <a href='tel:214-853-5126'>214-853-5126</a><br />";
    $s .= "<b>Email:</b> <a href='mailto:schedule@cic.clinic'>schedule@cic.clinic</a> <b>Web:</b> <a href='www.cic-clinic'>www.CIC.clinic</a><br /><br />";

    $s .= "<b>All mail correspondence to:</b><br />";
    $s .= "1930 E. Rosemeade Pkwy #106, Carrollton, TX 75007<br /><br />";

    $s .= "<img src='cid:map' width='420' height='324' /><br /><br />";
    $s .= "<img src='cid:book' width='420' height='324' /><br /><br />";

    $s .= "<b>CONFIDENTIALITY NOTICE</b>: This e-mail message is covered by the Electronic Communications Privacy Act, 18 U.S.C. " .
            "&sect;<a href='tel:2510-2521'>2510-2521</a> and is legally privileged. This message, together with any attachments, is intended only for the addressee. " .
            "It may contain information which is legally privileged, confidential and exempt from disclosure. " .
            "If you are not the intended recipient of this message, you may not disclose, print, copy or disseminate this information. " .
            "If you have received this in error, please reply and notify the sender (only) and delete the message along with any attachments. " .
            "Unauthorized interception of this email is a violation of federal criminal law.";

    return $s;
}

function signatureRecords() {
    $s = "<br /><br /><br /><br />";

    $s .= "<b>Please see our attached clinic maps as well.</b><br /><br />";

    $s .= "<b>We appreciate working with you.</b><br /><br />";

    $s .= "<b>Please send all record requests to:</b><br />";
    $s .= "<a href='mailto:records@cic.clinic'>records@cic.clinic</a> or fax us at <a href='tel:(214) 853-5126'>(214) 853-5126</a><br /><br />";

    $s .= "<b>Please send all reductions requests to:</b><br />";
    $s .= "<a href='mailto:reduction@cic.clinic'>reduction@cic.clinic</a> or fax us at <a href='tel:(214) 853-5126'>(214) 853-5126</a><br /><br />";

    $s .= "<b>Please have all checks sent to:</b><br />";
    $s .= "1930 E. Rosemeade Pkwy #106, Carrollton, TX 75007<br /><br />";

    $s .= "<b>We appreciate working with you.</b><br /><br />";

    $s .= "<b>Here is quick video of Dr. Farzad introducing Complete Injury Centers.</b><br />";
    $s .= "<a href='https://www.youtube.com/watch?v=uo8tdPQ01mY'>https://www.youtube.com/watch?v=uo8tdPQ01mY</a><br /> <br />";

    $s .= "Have a Blessed Day!<br /> <br />";

    $s .= "<b>For your convenience:</b><br />";
    $s .= "<a href='mailto:schedule@cic.clinic'>schedule@cic.clinic</a><br />";
    $s .= "<a href='mailto:records@cic.clinic'>records@cic.clinic</a><br />";
    $s .= "<a href='mailto:reduction@cic.clinic'>reduction@cic.clinic</a><br />";
    $s .= "<a href='mailto:ashley@cic.clinic'>ashley@cic.clinic</a><br /><br />";

    $s .= "<b>CIC RecordsTeam</b><br /><br />";
    $s .= "<img src='cid:logo' /><br /><br />";

    $s .= "<b>Phone:</b> <a href='tel:(214) 666-6651'>(214) 666-6651</a><br />";
    $s .= "<b>Fax:</b> <a href='tel:(214) 853-5126'>(214) 853-5126</a><br />";
    $s .= "<b>Email:</b> <a href='mailto:records@cic.clinic'>records@cic.clinic</a><br />";
    $s .= "<b>Web:</b> <a href='www.cic-clinic'>www.CIC.clinic</a><br /><br />";

    $s .= "<b>All mail correspondence to:</b><br />";
    $s .= "1930 E. Rosemeade Pkwy #106, Carrollton, TX 75007<br /><br />";

    $s .= "<img src='cid:map' width='420' height='324' /><br /><br />";
    $s .= "<img src='cid:book' width='420' height='324' /><br /><br />";

    $s .= "CONFIDENTIALITY NOTICE: This e-mail message is covered by the Electronic Communications Privacy Act, 18 U.S.C. " .
            "&sect;<a href='tel:2510-2521'>2510-2521</a> and is legally privileged. This message, together with any attachments, is intended only for the addressee. " .
            "It may contain information which is legally privileged, confidential and exempt from disclosure. " .
            "If you are not the intended recipient of this message, you may not disclose, print, copy or disseminate this information. " .
            "If you have received this in error, please reply and notify the sender (only) and delete the message along with any attachments. " .
            "Unauthorized interception of this email is a violation of federal criminal law.";

    return $s;
}
?>
