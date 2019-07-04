<?php
// Copyright (C) 2008-2017 Rod Roark <rod@sunsetsystems.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.



include_once("../../globals.php");
require_once("$srcdir/transactions.inc");
require_once("$srcdir/options.inc.php");
include_once("$srcdir/patient.inc");

$template_file = $GLOBALS['OE_SITE_DIR'] . "/referral_template.html";

$TEMPLATE_LABELS = array(
  'label_webpage_title'         => htmlspecialchars(xl('Referral Form')),
  'label_subhead_clinic'        => htmlspecialchars(xl('Clinic Copy')),
  'label_name'                  => htmlspecialchars(xl('Name')),
  'label_gender'                => htmlspecialchars(xl('Gender')),
  'label_address'               => htmlspecialchars(xl('Address')),
  'label_postal'                => htmlspecialchars(xl('Postal')),
  'label_phone'                 => htmlspecialchars(xl('Phone')),
  'label_diagnosis'             => htmlspecialchars(xl('Diagnosis')),
  'label_form1_title'           => htmlspecialchars(xl('Referral Form'))
);

if (!is_file($template_file)) {
    die("$template_file does not exist!");
}

$transid = empty($_REQUEST['transid']) ? 0 : $_REQUEST['transid'] + 0;
$PDF_OUTPUT = empty($_REQUEST['pdf']) ? 0 : $_REQUEST['pdf'] + 0;
// if (!$transid) die("Transaction ID is missing!");

if ($transid) {
    $trow = getTransById($transid);
    $patient_id = $trow['pid'];
    $refer_date = empty($trow['refer_date']) ? date('Y-m-d') : $trow['refer_date'];
} else {
    if (empty($_REQUEST['patient_id'])) {
        // If no transaction ID or patient ID, this will be a totally blank form.
        $patient_id = 0;
        $refer_date = '';
    } else {
        $patient_id = $_REQUEST['patient_id'] + 0;
        $refer_date = date('Y-m-d');
    }

    $trow = array('id' => '', 'pid' => $patient_id, 'refer_date' => $refer_date);
}

if ($patient_id) {
    $patdata = getPatientData($patient_id);
    $patient_age = getPatientAge(str_replace('-', '', $patdata['DOB']));
} else {
    $patdata = array('DOB' => '');
    $patient_age = '';
}

$userQuery = sqlQuery("select * from users where username = ?", array($_SESSION['authUser']));

$s = '';
$fh = fopen($template_file, 'r');
while (!feof($fh)) {
    $s .= fread($fh, 8192);
}

fclose($fh);

$s = str_replace("{header1}", referralGenFacilityTitle($TEMPLATE_LABELS['label_form1_title'], $trow['refer_facilities']), $s);

$fres = sqlStatement("SELECT * FROM layout_options " .
  "WHERE form_id = 'LBTref' ORDER BY group_id, seq");
while ($frow = sqlFetchArray($fres)) {
    $data_type = $frow['data_type'];
    $field_id  = $frow['field_id'];
    $currvalue = '';
    if (isset($trow[$field_id])) {
        $currvalue = $trow[$field_id];
    }

	$newValue = generate_display_field($frow, $currvalue);

    $s = str_replace(
        "{ref_$field_id}",
        $newValue,
        $s
    );
}

foreach ($patdata as $key => $value) {
    if ($key == "sex") {
        $s = str_replace("{pt_$key}", generate_display_field(array('data_type'=>'1','list_id'=>'sex'), $value), $s);
    } else if ($key == "lawyer") {
        $s = str_replace("{pt_$key}", generate_display_field(array('data_type'=>'14'), $value), $s);
    } else {
        $s = str_replace("{pt_$key}", $value, $s);
    }
}

foreach ($TEMPLATE_LABELS as $key => $value) {
    $s = str_replace("{".$key."}", $value, $s);
}

$s = str_replace("{current_doctors_name}", $userQuery['fname'] . " " . $userQuery['lname'] . "&nbsp;&nbsp;&nbsp;&nbsp; NPI: " .$userQuery['npi'], $s);

$signature_image = $GLOBALS['OE_SITE_DIR'] . "/signatures/" . $userQuery['username'] . ".png";
if (is_file($signature_image)) {
    $encoded_image = base64_encode(file_get_contents($signature_image));
    $s = str_replace("{user_signature}", "<img class='signature' src='data:image/png;base64," . $encoded_image . "'>", $s);
}

// A final pass to clear any unmatched variables:
$s = preg_replace('/\{\S+\}/', '', $s);

if ($PDF_OUTPUT) {
    $pdf = new mPDF(
        $GLOBALS['pdf_language'],
        $GLOBALS['pdf_size'],
        '9', // default font size (pt)
        '', // default_font.
        $GLOBALS['pdf_left_margin'],
        $GLOBALS['pdf_right_margin'],
        $GLOBALS['pdf_top_margin'],
        $GLOBALS['pdf_bottom_margin'],
        '', // default header margin
        '', // default footer margin
        $GLOBALS['pdf_layout']
    );

    $pdf->shrink_tables_to_fit = 1;
    $pdf->use_kwt = true;

    $pdf->setDefaultFont('dejavusans');
    $pdf->autoScriptToLang = true;
    if ($_SESSION['language_direction'] == 'rtl') {
        $pdf->SetDirectionality('rtl');
    }

    ob_start();
}

echo $s;

if ($PDF_OUTPUT) {
    $content = getContent();
    $pdf->SetTitle('Referral Report');

    try {
        $pdf->writeHTML($content, false);
        $content = $pdf->Output($fn, 'S');
    } catch (MpdfException $exception) {
        die($exception);
    }

    require_once ($GLOBALS['srcdir'] . "/classes/postmaster.php");
    $mail = new MyMailer();
    $mail->From = $GLOBALS['patient_reminder_sender_email'];
    $mail->FromName = $GLOBALS['patient_reminder_sender_name'];
    $mail->Body = "New patient referral for patient " . $patdata['fname'] . " ". $patdata['lname'];
    $mail->Subject = "CIC: New Patient Referral - Ready to send - " . $patdata['fname'] . " " . $patdata['lname'] . " - " . $patdata['DOB'];
    $mail->addStringAttachment($content, 'New Referral ' . $patdata['fname'] . " " . $patdata['lname'] . " - " . $patdata['DOB'] .'.pdf');
    $mail->AddAddress($GLOBALS['practice_return_email_path'], "Marzban, Farzad");
    $mail->AddAddress("schedule@cic.clinic", "CIC Schedule");
    if(!$mail->Send()) {
        error_log("There has been a mail error sending to " . $mail->ErrorInfo);
    }

    echo "<body onload=\"javascript:location.href='transactions.php';\"></body>";
}

function getContent()
{
    global $web_root, $webserver_root;
    $content = ob_get_clean();
  // Fix a nasty mPDF bug - it ignores document root!
    $i = 0;
    $wrlen = strlen($web_root);
    $wsrlen = strlen($webserver_root);
    while (true) {
        $i = stripos($content, " src='/", $i + 1);
        if ($i === false) {
            break;
        }

        if (substr($content, $i+6, $wrlen) === $web_root &&
        substr($content, $i+6, $wsrlen) !== $webserver_root) {
            $content = substr($content, 0, $i + 6) . $webserver_root . substr($content, $i + 6 + $wrlen);
        }
    }

    return $content;
}

function referralGenFacilityTitle($repname = '', $facid = 0)
{
    $s = '';
    $s .= "<table class='ftitletable'>\n";
    $s .= " <tr>\n";
    if (empty($logo)) {
        $s .= "  <td class='ftitlecell1'>$repname</td>\n";
    } else {
        $s .= "  <td class='ftitlecell1'>$logo</td>\n";
        $s .= "  <td class='ftitlecellm'>$repname</td>\n";
    }
    $s .= "  <td class='ftitlecell2'>\n";
    $r = getFacility($facid);
    if (!empty($r)) {
        $s .= "<b>COMPLETE INJURY CENTERS</b>\n";
        if ($r['street']) {
            $s .= "<br />" . htmlspecialchars($r['street'], ENT_NOQUOTES) . "\n";
        }

        if ($r['city'] || $r['state'] || $r['postal_code']) {
            $s .= "<br />";
            if ($r['city']) {
                $s .= htmlspecialchars($r['city'], ENT_NOQUOTES);
            }

            if ($r['state']) {
                if ($r['city']) {
                    $s .= ", \n";
                }

                $s .= htmlspecialchars($r['state'], ENT_NOQUOTES);
            }

            if ($r['postal_code']) {
                $s .= " " . htmlspecialchars($r['postal_code'], ENT_NOQUOTES);
            }

            $s .= "\n";
        }

        if ($r['country_code']) {
            $s .= "<br />" . htmlspecialchars($r['country_code'], ENT_NOQUOTES) . "\n";
        }

        $s .= "<br />214-666-6651\n";
    }

    $s .= "  </td>\n";
    $s .= " </tr>\n";
    $s .= "</table>\n";
    return $s;
}
