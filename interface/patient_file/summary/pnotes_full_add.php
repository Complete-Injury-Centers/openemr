<?php
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.



require_once("../../globals.php");
require_once("$srcdir/pnotes.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/acl.inc");
require_once("$srcdir/log.inc");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/gprelations.inc.php");

if ($_GET['set_pid']) {
    require_once("$srcdir/pid.inc");
    setpid($_GET['set_pid']);
}

// form parameter docid can be passed to restrict the display to a document.
$docid = empty($_REQUEST['docid']) ? 0 : intval($_REQUEST['docid']);

// form parameter orderid can be passed to restrict the display to a procedure order.
$orderid = empty($_REQUEST['orderid']) ? 0 : intval($_REQUEST['orderid']);

$patient_id = $pid;
if ($docid) {
    $row = sqlQuery("SELECT foreign_id FROM documents WHERE id = ?", array($docid));
    $patient_id = intval($row['foreign_id']);
} else if ($orderid) {
    $row = sqlQuery("SELECT patient_id FROM procedure_order WHERE procedure_order_id = ?", array($orderid));
    $patient_id = intval($row['patient_id']);
}

// Check authorization.
if (!acl_check('patients', 'notes', '', array('write','addonly'))) {
    die(htmlspecialchars(xl('Not authorized'), ENT_NOQUOTES));
}

$tmp = getPatientData($patient_id, "squad");
if ($tmp['squad'] && ! acl_check('squads', $tmp['squad'])) {
    die(htmlspecialchars(xl('Not authorized for this squad.'), ENT_NOQUOTES));
}

//the number of records to display per screen
$N = 25;

$mode   = $_REQUEST['mode'];
$offset = $_REQUEST['offset'];
$form_active = $_REQUEST['form_active'];
$form_inactive = $_REQUEST['form_inactive'];
$noteid = $_REQUEST['noteid'];
$form_doc_only = isset($_POST['mode']) ? (empty($_POST['form_doc_only']) ? 0 : 1) : 1;

if (!isset($offset)) {
    $offset = 0;
}

// if (!isset($active)) $active = "all";

$active = 'all';
if ($form_active) {
    if (!$form_inactive) {
        $active = '1';
    }
} else {
    if ($form_inactive) {
        $active = '0';
    } else {
        $form_active = $form_inactive = '1';
    }
}

// this code handles changing the state of activity tags when the user updates
// them through the interface
if (isset($mode)) {
    if ($mode == "update") {
        foreach ($_POST as $var => $val) {
            if (strncmp($var, 'act', 3) == 0) {
                $id = str_replace("act", "", $var);
                if ($_POST["chk$id"]) {
                    reappearPnote($id);
                } else {
                    disappearPnote($id);
                }

                if ($docid) {
                    setGpRelation(1, $docid, 6, $id, !empty($_POST["lnk$id"]));
                }

                if ($orderid) {
                    setGpRelation(2, $orderid, 6, $id, !empty($_POST["lnk$id"]));
                }
            }
        }
    } elseif ($mode == "new") {
        $note = $_POST['note'];
        if ($noteid) {
            updatePnote($noteid, $note, $_POST['form_note_type'], $_POST['assigned_to']);
            $noteid = '';
        } else {
            $noteid = addPnote(
                $patient_id,
                $note,
                $userauthorized,
                '1',
                $_POST['form_note_type'],
                $_POST['assigned_to']
            );
        }

        if ($docid) {
            setGpRelation(1, $docid, 6, $noteid);
        }

        if ($orderid) {
            setGpRelation(2, $orderid, 6, $noteid);
        }

        $noteid = '';
    } elseif ($mode == "delete") {
        if ($noteid) {
            deletePnote($noteid);
            newEvent("delete", $_SESSION['authUser'], $_SESSION['authProvider'], "pnotes: id ".$noteid);
        }

        $noteid = '';
    }
}

$title = '';
$assigned_to = $_SESSION['authUser'];
if ($noteid) {
    $prow = getPnoteById($noteid, 'title,assigned_to,body');
    $title = $prow['title'];
    $assigned_to = $prow['assigned_to'];
}

// Get the users list.  The "Inactive" test is a kludge, we should create
// a separate column for this.
$ures = sqlStatement("SELECT username, fname, lname FROM users " .
 "WHERE username != '' AND active = 1 AND " .
 "( info IS NULL OR info NOT LIKE '%Inactive%' ) " .
 "ORDER BY lname, fname");

$pres = getPatientData($patient_id, "lname, fname");
$patientname = $pres['lname'] . ", " . $pres['fname'];

//retrieve all notes
$result = getPnotesByDate(
    "",
    $active,
    'id,date,body,user,activity,title,assigned_to',
    $patient_id,
    $N,
    $offset
);

function permission() {
    $res = sqlStatement("SELECT externalUser FROM users WHERE id=?", array($_SESSION['authUserID']));
    if($row = sqlFetchArray($res)) {
        return $row['externalUser'] == '1' ? false : true; // If it's an external user, permision set to false
    }
    else {
        return true;
    }
}
?>

<html>
<head>
<?php html_header_show();?>

<link rel='stylesheet' href="<?php echo $css_header;?>" type="text/css">

<!-- supporting javascript code -->
<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-3-1-1/index.js"></script>
<script type="text/javascript" src="<?php echo $webroot ?>/interface/main/tabs/js/include_opener.js"></script>
<!--<script type="text/javascript" src="../../../library/dialog.js?v=<?php /*echo $v_js_includes; */?>"></script>-->
<script type="text/javascript" src="../../../library/js/common.js"></script>

<script type="text/javascript">
function submitform(attr) {
if (attr="newnote")
    document.forms[0].submit();
}
</script>
</head>
<body class="body_top">

<div id="pnotes"> <!-- large outer DIV -->

<?php
$title_docname = "";
if ($docid) {
    $title_docname .= " " . xl("linked to document") . " ";
    $d = new Document($docid);
    $title_docname .= $d->get_url_file();
}

if ($orderid) {
    $title_docname .= " " . xl("linked to procedure order") . " $orderid";
}

$urlparms = "docid=$docid&orderid=$orderid";
?>

<form border='0' method='post' name='new_note' id="new_note" action='pnotes_full.php?<?php echo $urlparms; ?>'>

    <div style="margin: 5px 0; border:solid 2px; border-radius: 5px; padding: 5px; max-width: 1000px;">
        <div style='display: inline-block; margin-right: 5px;'>
            <span class="title"><?php echo xlt('ADD PROGRESS NOTES - UPDATES HERE') ?></span> <br />
            <span><b><?php echo xlt('(back office adds updates here as well)') . $title_docname; ?></b></span>
        </div>
        <div style='display: inline-block;'>
            <?php if ($noteid) { ?>
            <!-- existing note -->
            <a href="#" class="css_button" id="printnote"><span><?php echo xlt('View Printable Version'); ?></span></a>
            <?php } ?>
            <?php
                if($_GET['clean'] == 1) {
                    echo "";
                } else {
                    echo "<a class='css_button large_button' id='cancel' href='javascript:;'>";
                    echo "<span class='css_button_span large_button_span'>".htmlspecialchars(xl('Cancel'), ENT_NOQUOTES)."</span>";
                }
            ?>
            </a>
        </div>
        <div style='display: block;'>
            <span class='text'>
                <?php
                if ($noteid) {
                // Modified 6/2009 by BM to incorporate the patient notes into the list_options listings
                    echo htmlspecialchars(xl('Amend Existing Note'), ENT_NOQUOTES) .
                    "<b> &quot;" . generate_display_field(array('data_type'=>'1','list_id'=>'note_type'), $title) . "&quot;</b>\n";
                } else {
                    echo htmlspecialchars(xl('Ex. reason patient missed, patient not answering calls, update on case, etc.'), ENT_NOQUOTES) . "\n";
                }
                ?>
            </span>
        </div>
    </div>

    <!-- <br/> -->

<input type='hidden' name='mode' id="mode" value="new">
<input type='hidden' name='trigger' id="trigger" value="add">
<input type='hidden' name='offset' id="offset" value="<?php echo $offset ?>">
<input type='hidden' name='form_active' id="form_active" value="<?php echo htmlspecialchars($form_active, ENT_QUOTES) ?>">
<input type='hidden' name='form_inactive' id="form_inactive" value="<?php echo htmlspecialchars($form_inactive, ENT_QUOTES) ?>">
<input type='hidden' name='noteid' id="noteid" value="<?php echo htmlspecialchars($noteid, ENT_QUOTES) ?>">
<input type='hidden' name='form_doc_only' id="form_doc_only" value="<?php echo htmlspecialchars($form_doc_only, ENT_QUOTES) ?>">
<table border='0' cellspacing='8' style="width:100%;">
 <tr style="display:none">
  <td class='text'>
    <br/>

   <b><?php echo htmlspecialchars(xl('Type'), ENT_NOQUOTES); ?>:</b>
    <?php
   // Added 6/2009 by BM to incorporate the patient notes into the list_options listings
    generate_form_field(array('data_type'=>1,'field_id'=>'note_type','list_id'=>'note_type','empty_title'=>'SKIP'), $title);
    ?>
   &nbsp; &nbsp;
   <b><?php echo htmlspecialchars(xl('To'), ENT_NOQUOTES); ?>:</b>
   <select name='assigned_to'>
<?php
/**
while ($urow = sqlFetchArray($ures)) {
    echo "    <option value='" . htmlspecialchars($urow['username'], ENT_QUOTES) . "'";
    if ($urow['username'] == $assigned_to) {
        echo " selected";
    }

    echo ">" . htmlspecialchars($urow['lname'], ENT_NOQUOTES);
    if ($urow['fname']) {
        echo htmlspecialchars(", ".$urow['fname'], ENT_NOQUOTES);
    }

    echo "</option>\n";
}
*/
?>
   <option value=' '><?php echo htmlspecialchars(xl('Mark Note as Completed'), ENT_NOQUOTES); ?></option>
   </select>
  </td>
 </tr>
 <tr>
  <td>
<?php
if ($noteid) {
    $body = $prow['body'];
    $body = preg_replace(array('/(\sto\s)-patient-(\))/', '/(:\d{2}\s\()' . $patient_id . '(\sto\s)/'), '${1}' . $patientname . '${2}', $body);
    $body = nl2br(htmlspecialchars($body, ENT_NOQUOTES));
    echo "<div class='text'>".$body."</div>";
}
?>
    <!--<br/>-->
    <textarea name='note' id='note' rows='4' style="width:100%;"></textarea>

    <?php if ($noteid) { ?>
    <br />
    <!-- existing note -->
    <a href="#" class="css_button" id="newnote" title="<?php echo htmlspecialchars(xl('Add as a new note'), ENT_QUOTES); ?>" ><span><?php echo htmlspecialchars(xl('Save as new note'), ENT_NOQUOTES); ?></span></a>
    <a href="#" class="css_button" id="appendnote" title="<?php echo htmlspecialchars(xl('Append to the existing note'), ENT_QUOTES); ?>"><span><?php echo htmlspecialchars(xl('Append this note'), ENT_NOQUOTES); ?></span></a>
    <?php } else { ?>
    <div>
    <?php if(permission()) :?>
        <!-- important checkbox -->
        <span style="display: inline-block;"><input type="checkbox" id="important" value='1' name="<?php echo xla('important'); ?>">
        <label for="important" style="font-size:9pt;"><b><?php echo xlt('Important - to clinic'); ?></b></label></span>
    <?php endif ?>
    <?php if(acl_check('admin', 'super') and permission()) :?>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <!-- lop checkbox -->
        <span style="display: inline-block;"><input type="checkbox" id="note_lop_request" value='1' name="<?php echo xla('note_lop_request'); ?>">
        <label for="note_lop_request" style="font-size:9pt;"><b><?php echo xlt('LOP Request'); ?></b></label></span>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <!-- lawyer checkbox -->
        <span style="display: inline-block;"><input type="checkbox" id="notify_lawyer_mail" value='1' name="<?php echo xla('notify_lawyer_mail'); ?>">
        <label for="notify_lawyer_mail" style="font-size:9pt;"><b><?php echo xlt('Notify Lawyer'); ?></b></label></span>
    <?php endif ?>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <!-- office checkbox -->
        <span style="display: inline-block;"><input type="checkbox" id="notify_back" value='1' name="<?php echo xla('notify_back'); ?>">
        <label for="notify_back" style="font-size:9pt;"><b><?php echo xlt('Notify Back Office'); ?></b></label></span>
    <?php if(acl_check('admin', 'super')) :?>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <!-- director checkbox -->
        <span style="display: inline-block;"><input type="checkbox" id="notify_clinic_director" value='1' name="<?php echo xla('notify_clinic_director'); ?>">
        <label for="notify_clinic_director" style="font-size:9pt;"><b><?php echo xlt('Notify Clinic Director'); ?></b></label></span>
    <?php endif ?>
    </div>

    <br />
    <a href="#" class="css_button" id="newnote" title="<?php echo htmlspecialchars(xl('Add as a new note'), ENT_QUOTES); ?>" ><span><?php echo htmlspecialchars(xl('Save as new note'), ENT_NOQUOTES); ?></span></a>
    <?php } ?>

  </td>
 </tr>
</table>
<!-- <br> -->
</form>
<form border='0' method='post' name='update_activity' id='update_activity'
 action="pnotes_full.php?<?php echo $urlparms; ?>">

<!-- start of previous notes DIV -->
<div class=pat_notes>


<input type='hidden' name='mode' value="update">
<input type='hidden' name='offset' id='noteid' value="<?php echo $offset;?>">
<input type='hidden' name='noteid' id='noteid' value="0">
</form>

<table width='400' border='0' cellpadding='0' cellspacing='0'>
 <tr>
  <td>
<?php
if ($offset > ($N-1)) {
    echo "   <a class='link' href='pnotes_full.php" .
    "?$urlparms" .
    "&form_active=" . htmlspecialchars($form_active, ENT_QUOTES) .
    "&form_inactive=" . htmlspecialchars($form_inactive, ENT_QUOTES) .
    "&form_doc_only=" . htmlspecialchars($form_doc_only, ENT_QUOTES) .
    "&offset=" . ($offset-$N) . "' onclick='top.restoreSession()'>[" .
    htmlspecialchars(xl('Previous'), ENT_NOQUOTES) . "]</a>\n";
}
?>
  </td>
  <td align='right'>
<?php
if ($result_count == $N) {
    echo "   <a class='link' href='pnotes_full.php" .
    "?$urlparms" .
    "&form_active=" . htmlspecialchars($form_active, ENT_QUOTES) .
    "&form_inactive=" . htmlspecialchars($form_inactive, ENT_QUOTES) .
    "&form_doc_only=" . htmlspecialchars($form_doc_only, ENT_QUOTES) .
    "&offset=" . ($offset+$N) . "' onclick='top.restoreSession()'>[" .
    htmlspecialchars(xl('Next'), ENT_NOQUOTES) . "]</a>\n";
}
?>
  </td>
 </tr>
</table>

</div> <!-- close the previous-notes DIV -->

<script language='JavaScript'>

<?php
if ($_GET['set_pid']) {
    $ndata = getPatientData($patient_id, "fname, lname, pubpid");
?>
 parent.left_nav.setPatient(<?php echo "'" . addslashes($ndata['fname']." ".$ndata['lname']) . "'," . addslashes($patient_id) . ",'" . addslashes($ndata['pubpid']) . "',window.name"; ?>);
<?php
}

// If this note references a new patient document, pop up a display
// of that document.
//
if ($noteid /* && $title == 'New Document' */) {
    $prow = getPnoteById($noteid, 'body');
    if (preg_match('/New scanned document (\d+): [^\n]+\/([^\n]+)/', $prow['body'], $matches)) {
        $docid = $matches[1];
        $docname = $matches[2];
    ?>
     window.open('../../../controller.php?document&retrieve&patient_id=<?php echo htmlspecialchars($patient_id, ENT_QUOTES) ?>&document_id=<?php echo htmlspecialchars($docid, ENT_QUOTES) ?>&<?php echo htmlspecialchars($docname, ENT_QUOTES)?>&as_file=true',
  '_blank', 'resizable=1,scrollbars=1,width=600,height=500');
<?php
    }
}
?>

</script>

</div> <!-- end outer 'pnotes' -->

</body>

<script language="javascript">

// jQuery stuff to make the page a little easier to use

$(document).ready(function(){
    $("#appendnote").click(function() { AppendNote(); });
    $("#newnote").click(function() {
        if($("#note").val() != '') {
            NewNote();
        } else {
            alert("No content in progress note");
        }
    });
    $("#printnote").click(function() { PrintNote(); });

    $(".change_activity").click(function() { top.restoreSession(); $("#update_activity").submit(); });

    $(".deletenote").click(function() { DeleteNote(this); });

    $(".noterow").mouseover(function() { $(this).toggleClass("highlight"); });
    $(".noterow").mouseout(function() { $(this).toggleClass("highlight"); });
    $(".notecell").click(function() { EditNote(this); });

    $("#note").focus();

    var EditNote = function(note) {
        top.restoreSession();
        $("#noteid").val(note.id);
        $("#mode").val("");
        $("#new_note").submit();
    }

    var NewNote = function () {
        <?php
            if($_GET['clean'] == 1) {
                echo "";
            }else {
                echo "top.restoreSession();";
            }
        ?>
        $("#noteid").val('');
        $("#new_note").submit();
    }

    var AppendNote = function () {
        top.restoreSession();
        $("#new_note").submit();
    }

    var PrintNote = function () {
        top.restoreSession();
        window.open('pnotes_print.php?noteid=<?php echo htmlspecialchars($noteid, ENT_QUOTES); ?>', '_blank', 'resizable=1,scrollbars=1,width=600,height=500');
    }

    var DeleteNote = function(note) {
        if (confirm("<?php echo htmlspecialchars(xl('Are you sure you want to delete this note?', '', '', '\n ').xl('This action CANNOT be undone.'), ENT_QUOTES); ?>")) {
            top.restoreSession();
            // strip the 'del' part of the object's ID
            $("#noteid").val(note.id.replace(/del/, ""));
            $("#mode").val("delete");
            $("#new_note").submit();
        }
    }

});
$(document).ready(function(){
    $("#cancel").click(function() {
          dlgclose();
     });

    $("#new_note").submit(function (event) {
        event.preventDefault();
        var post_url = $(this).attr("action");
        var request_method = $(this).attr("method");
        var form_data = $(this).serialize();

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data
        }).done(function (r) { //
            <?php
                if($_GET['clean'] == 1) {
                    echo "top.refreshPatient();";
                } else {
                    echo "dlgclose('refreshme', false);";
                }
            ?>
        });
    });
});
</script>

</html>
