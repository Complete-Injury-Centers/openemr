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
        echo "<script>console.log('original: ".$send_to."')</script>";
        $send_to = $_ENV['DEV_EMAIL'];
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
        $mail->setFrom($email, $title); // Add email sent from
        $mail->addAddress($send_to);                                // Add a recipient
        if($replyto) {
            $mail->addReplyTo($replyto, $titleReply);
        }

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
    $send_to = findLawyer($pid);

    $patient = getPatient($pid);
    $subject = "CIC LOP REQUEST";
    if($patient['fname']) {$subject .= " / ".$patient['fname'];}
    if($patient['lname']) {$subject .= " ".$patient['lname'];}
    if($patient['DOB']) {$subject .= " / ".$patient['DOB'];}

    $body = greeting();
    $org = getOrganization($pid);
    if($org['organization']) {$body .= " ".$org['organization'];}
    $body .= "!<br /><br />";
    $body .= "This is a friendly LOP request. Please send LOP via email records@cic.clinic for ";
    if($patient['fname']) {$body .= $patient['fname'];}
    if($patient['lname']) {$body .= " " . $patient['lname'];}
    if($patient['DOB']) {$body .= " DOB: " . $patient['DOB'];}
    elseif($patient['doi']) {$body .= " DOI: " . $patient['doi'];}
    $body .= ". ";

    $facil = getLocation($pid);
    $body .= "Patient is being seen at our ";
    $body .= trim(explode("-", $facil['name'])[0]) . ".";
    $body .= "<br /><br />";
    $body .= "Please contact us with any issues. We are here to help.";
    
    sendMail($send_to, $subject, $body, $_ENV['EMAIL_USER_REC'], $_ENV['EMAIL_PASS_REC'], 'CIC LOP REQUEST');
}

function sendLawyerAppointmentAlert($eid) {
    $patient = getInfoAppointmentAll($eid);

    $send_to = findLawyer($patient['pid']);

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

    sendMail($send_to, $subject, $body, $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
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

    sendMail($send_to, $subject, $body, $_ENV['EMAIL_USER_SCH'], $_ENV['EMAIL_PASS_SCH'], 'CIC SCHEDULE');
}

function findLawyer($pid) {
    $res = sqlStatement("SELECT u.email_direct FROM users AS u LEFT JOIN patient_data AS p ON p.lawyer=u.id WHERE p.pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        $email = preg_replace('/\s+/', '', $row['email_direct']);
        $email = preg_replace('/:/', ',', $email);
        $emails = explode(",", $email);
        foreach($emails as $mail) {
            $mail = strtolower($mail);
            if(preg_match("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$^", $mail)) {
                return $mail;
            }
        }
    }
    return "";
}

function getPatient($pid) {
    $res = sqlStatement("SELECT fname,lname,DOB,doi FROM patient_data WHERE pid=?", array($pid));
    if($row = sqlFetchArray($res)) {
        return $row;
    }
    return "";
}

function getInfoAppointmentAll($eid) {
    $query = 'SELECT '.
        'e.pc_eventDate, e.pc_endDate, e.pc_startTime, e.pc_endTime, e.pc_duration, e.pc_recurrtype, e.pc_recurrspec, e.pc_recurrfreq, e.pc_catid, e.pc_eid, e.pc_gid, '.
        'e.pc_title, e.pc_hometext, e.pc_apptstatus, '.
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
        foreach($emails as $mail) {
            $mail = strtolower($mail);
            if(preg_match("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$^", $mail)) {
                return $mail;
            }
        }
    }
    return "";
}

function greeting() {
    $greeting = date("H") < 12 ? "Good Morning" : "Good Afternoon";
    $greeting = date("H") > 18 ? "Good Evening" : $greeting;

    return $greeting;
}
?>
