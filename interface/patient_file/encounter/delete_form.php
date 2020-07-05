<?php
/**
 * This script delete an Encounter form.
 *
 * Copyright (C) 2015 Roberto Vasquez <robertogagliotta@gmail.com>
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
 * @author  Roberto Vasquez <robertogagliotta@gmail.com>
 * @link    http://www.open-emr.org
 */




include_once("../../globals.php");

// allow a custom 'delete' form
$deleteform = $incdir . "/forms/" . $_REQUEST["formname"]."/delete.php";

check_file_dir_name($_REQUEST["formname"]);

if (file_exists($deleteform)) {
    include_once($deleteform);
    exit;
}

function removeBill($formname, $id, $encounter, $pid) {
    // activity = 0 to treatments
    $q = "SELECT opt.list_id,lbf.field_value FROM layout_options AS opt JOIN lbf_data as lbf ON lbf.field_id=opt.field_id WHERE opt.form_id=? AND opt.uor>0 AND opt.data_type='25' AND lbf.form_id=?";
    $res = sqlStatement($q, array($formname, $id));
    while($row = sqlFetchArray($res)) {
        $values = explode('|', $row['field_value']);
        foreach($values as $val) {
            $val = explode(":", $val);
            if($val[1] == "1") {
                $cres = sqlStatement("SELECT codes FROM list_options WHERE option_id=? AND list_id=?",array($val[0],$row['list_id']));
                if($crow = sqlFetchArray($cres)) {
                    $crow = explode(":", $crow['codes']);
                    $drow = sqlStatement("SELECT id FROM billing WHERE code_type=? AND code=? AND pid=? AND encounter=? AND units='1' ORDER BY id DESC LIMIT 1",array($crow[0], $crow[1], $pid, $encounter));
                    if($dres = sqlFetchArray($drow)) {
                        sqlStatement("UPDATE billing SET activity=0 WHERE id=?", array($dres['id']));
                    }
                }
            }
        }
    }

    // activity = 0 to exam's bills
    $res = sqlStatement("SELECT grp_title FROM layout_group_properties WHERE grp_form_id=? AND grp_group_id = '' AND grp_activity = 1",array($formname));
    if($row = sqlFetchArray($res)) {
        $grp_title = $row['grp_title'] != "Initial Visit" ? $row['grp_title'] : "Comprehensive New Patient";
        $sqlS = "SELECT fs_codes FROM fee_sheet_options WHERE fs_option LIKE '%".$grp_title."'";
        $cres = sqlStatement($sqlS);
        if($crow = sqlFetchArray($cres)) {
            $codes = explode("|", $crow['fs_codes']);
            $drow = sqlStatement("SELECT id FROM billing WHERE code_type=? AND code=? AND pid=? AND encounter=? AND units='1' ORDER BY id DESC LIMIT 1",array($codes[0], $codes[1], $pid, $encounter));
            if($dres = sqlFetchArray($drow)) {
                sqlStatement("UPDATE billing SET activity=0 WHERE id=?", array($dres['id']));
            }
        }
    }
}

// if no custom 'delete' form, then use a generic one

// when the Cancel button is pressed, where do we go?
$returnurl = 'forms.php';

if ($_POST['confirm']) {
    if ($_POST['id'] != "*" && $_POST['id'] != '') {
      // set the deleted flag of the indicated form
        $sql = "update forms set deleted=1 where id=?";
        sqlInsert($sql, array($_POST['id']));
      // Delete the visit's "source=visit" attributes that are not used by any other form.
        sqlStatement(
            "DELETE FROM shared_attributes WHERE " .
            "pid = ? AND encounter = ? AND field_id NOT IN (" .
            "SELECT lo.field_id FROM forms AS f, layout_options AS lo WHERE " .
            "f.pid = ? AND f.encounter = ? AND f.formdir LIKE 'LBF%' AND " .
            "f.deleted = 0 AND " .
            "lo.form_id = f.formdir AND lo.source = 'E' AND lo.uor > 0)",
            array($pid, $encounter, $pid, $encounter)
        );
      // Remove billings asociated with the form
        removeBill($_POST['formname'], $_POST['formid'], $_POST['encounter'], $_POST['pid']);
    }
    // log the event
    newEvent("delete", $_SESSION['authUser'], $_SESSION['authProvider'], 1, "Form ".$_POST['formname']." deleted from Encounter ".$_POST['encounter']);

    // redirect back to the encounter
    $address = "{$GLOBALS['rootdir']}/patient_file/encounter/$returnurl";
    echo "\n<script language='Javascript'>top.restoreSession();window.location='$address';</script>\n";
    exit;
}
?>
<html>

<head>
<?php html_header_show();?>
<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">

<!-- supporting javascript code -->
<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-2-2/index.js"></script>

</head>

<body class="body_top">

<span class="title"><?php echo xlt('Delete Encounter Form'); ?></span>

<form method="post" action="<?php echo $rootdir;?>/patient_file/encounter/delete_form.php" name="my_form" id="my_form">
<?php
// output each GET variable as a hidden form input
foreach ($_GET as $key => $value) {
    echo '<input type="hidden" id="'.attr($key).'" name="'.attr($key).'" value="'.attr($value).'"/>'."\n";
}
?>
<input type="hidden" id="confirm" name="confirm" value="1"/>
<p>
<?php echo xlt('You are about to delete the following form from this encounter') . ': ' . text(xl_form_title($_GET['formname'])); ?>
</p>
<input type="button" id="confirmbtn" name="confirmbtn" value='<?php echo xla('Yes, Delete this form'); ?>'>
<input type="button" id="cancel" name="cancel" value='<?php echo xla('Cancel'); ?>'>
</form>

</body>

<script language="javascript">
// jQuery stuff to make the page a little easier to use

$(document).ready(function(){
    $("#confirmbtn").click(function() { return ConfirmDelete(); });
    $("#cancel").click(function() { location.href='<?php echo "$rootdir/patient_file/encounter/$returnurl";?>'; });
});

function ConfirmDelete() {
    if (confirm('<?php echo xls('This action cannot be undone. Are you sure you wish to delete this form?'); ?>')) {
        top.restoreSession();
        $("#my_form").submit();
        return true;
    }
    return false;
}

</script>

</html>
