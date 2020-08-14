<?php
// Copyright (C) 2006-2010, 2016 Rod Roark <rod@sunsetsystems.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

include_once("../globals.php");
include_once("$srcdir/acl.inc");
require_once("$srcdir/options.inc.php");

// Collect user id if editing entry
$userid = $_REQUEST['userid'];

function invalue($name) {
    if (!$_POST[$name]) {
        return "''";
    }

    $fld = add_escape_custom(trim($_POST[$name]));
    return "'$fld'";
}
?>

<html>
<head>
<title><?php echo $userid ? xlt('Edit') : xlt('Add New') ?> <?php echo xlt('Person'); ?></title>
<script type="text/javascript" src="<?php echo $webroot ?>/interface/main/tabs/js/include_opener.js"></script>
<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-9-1/index.js"></script>

<style>
td { font-size:10pt; }

.inputtext {
 padding-left:2px;
 padding-right:2px;
}

.button {
 font-family:sans-serif;
 font-size:9pt;
 font-weight:bold;
 cursor: pointer;
}

.title {
  background-color: white;
  padding: 1px 4px;
}

</style>
</head>

<body class="body_top">
<?php
 // If we are saving, then save and close the window.
 //
if ($_POST['form_save']) {
  if($userid) {
      $query = "UPDATE users SET " .
      "mkt_info = "   . invalue('form_mkt_info')       . ", " .
      "last_contact = '". date('Y-m-d')           . "' " .
      "WHERE id = '" . add_escape_custom($userid) . "'";
      sqlStatement($query);
  }
}

if ($_POST['form_save']) {
    echo "<script language='JavaScript'>\n";
    echo " window.close();\n";
    echo " if (opener.refreshme) opener.refreshme();\n";
    echo "</script></body></html>\n";
    exit();
}

if ($userid) {
    $row = sqlQuery("SELECT last_contact, mkt_info FROM users WHERE id = ?", array($userid));
}
?>

<div class="title">
  <h3>Marketing To Do</h3>
</div>
<br />
<form method='post' name='theform' id="theform" action='addrbook_info_edit.php?userid=<?php echo attr($userid) ?>'>
<center>
<table border='0' width='100%'>
  <tr>
    <td nowrap><b><?php echo xlt('Last Contact'); ?>:</b></td>
    <td>
      <input type='text' size='11' name='form_last_date' value='<?php echo attr($row['last_contact']); ?>'
        maxlength='30' class='inputtext' readonly />
    </td>
  </tr>
  <tr>
    <td nowrap><b><?php echo xlt('Marketing Notes'); ?>:</b></td>
    <td>
    <textarea rows='8' cols='40' name='form_mkt_info' style='width:100%'
      wrap='virtual' class='inputtext'><?php echo text($row['mkt_info']) ?></textarea>
    </td>
  </tr>
</table>

<br />

<input type='submit' class='button' name='form_save' value='<?php echo xla('Save'); ?>' />
&nbsp;
<input type='button' class='button' value='<?php echo xla('Cancel'); ?>' onclick='window.close()' />
</center>
</form>
<?php    $use_validate_js = 1;?>
<?php validateUsingPageRules($_SERVER['PHP_SELF']);?>
</body>
</html>
