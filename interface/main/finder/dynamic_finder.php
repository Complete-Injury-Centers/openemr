<?php
// Copyright (C) 2012, 2016 Rod Roark <rod@sunsetsystems.com>
// Sponsored by David Eschelbacher, MD
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

require_once("../../globals.php");

$popup = empty($_REQUEST['popup']) ? 0 : 1;

// Generate some code based on the list of columns.
//
$colcount = 0;
$header0 = "";
$header  = "";
$coljson = "";
$res = sqlStatement("SELECT option_id, title FROM list_options WHERE " .
  "list_id = 'ptlistcols' AND activity = 1 ORDER BY seq, title");
while ($row = sqlFetchArray($res)) {
    $colname = $row['option_id'];
    $title = xl_list_label($row['title']);
    $header .= "   <th>";
    $header .= text($title);
    $header .= "</th>\n";
    $header0 .= "   <td align='center'><input type='text' size='10' ";
    $header0 .= "value='' class='search_init' /></td>\n";
    if ($coljson) {
        $coljson .= ", ";
    }

    $coljson .= "{\"sName\": \"" . addcslashes($colname, "\t\r\n\"\\") . "\"}";
    ++$colcount;
}

$header .= "   <th>Visits</th>\n   <th>Scheduled</th>\n   <th>Compliance</th>\n   <th>Referrals (Sent/Received)</th>\n   <th>Last Visit</th>\n";
for ($i=0; $i < 5; $i++) {
	$header0 .= "   <td align='center'><input type='text' size='10' value='' class='search_init' /></td>\n";
}
$coljson .= ", {\"sName\": \"visits\"}, {\"sName\": \"scheduled\"}, {\"sName\": \"compliance\"}, {\"sName\": \"referrals\"}, {\"sName\": \"lastVisit\"}";
?>
<html>
<head>
<?php html_header_show(); ?>
    <title><?php echo xlt("Patient Finder"); ?></title>
<link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">

<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-dt-1-10-13/css/jquery.dataTables.min.css" type="text/css">
<link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-dt-1-3-2/css/colReorder.dataTables.min.css" type="text/css">

<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-10-2/index.js"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-1-10-13/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/datatables.net-colreorder-1-3-2/js/dataTables.colReorder.min.js"></script>

<script language="JavaScript">

$(document).ready(function() {
 // Initializing the DataTable.
 //
 var oTable = $('#pt_table').dataTable( {
  "processing": true,
  // next 2 lines invoke server side processing
  "serverSide": true,
  // NOTE kept the legacy command 'sAjaxSource' here for now since was unable to get
  // the new 'ajax' command to work.
  "sAjaxSource": "dynamic_finder_ajax.php",
  // dom invokes ColReorderWithResize and allows inclusion of a custom div
  "dom"       : 'Rlfrt<"mytopdiv">ip',
  // These column names come over as $_GET['sColumns'], a comma-separated list of the names.
  // See: http://datatables.net/usage/columns and
  // http://datatables.net/release-datatables/extras/ColReorder/server_side.html
  "columns": [ <?php echo $coljson; ?> ],
  initComplete: function () {
  	this.api().columns().every( function () {
  	    var column = this;

  	    var select = $('<td><select><option value=""></option></select></td>')
  	        .appendTo( $(".filters") );

        select.find('select').on( 'change', function () {
          column
              .search( $(this).val() )
              .draw();
        } );

  	    column.data().unique().sort().each( function ( d, j ) {
  	    	if (d) {
	  	        select.find('select').append( '<option value="'+d+'">'+d+'</option>' );
  	    	}
  	    } );

  	    if ( column.index() === 4 ) {
          setTimeout(function() {
	          select.find('select').val('Active');
	          column
	              .search( 'Active' )
	              .draw();
	      }, 1);
  	    }
  	} );
  },
  "lengthMenu": [ 10, 25, 50, 100, 1000 ],
  "pageLength": <?php echo empty($GLOBALS['gbl_pt_list_page_size']) ? '10' : $GLOBALS['gbl_pt_list_page_size']; ?>,
    <?php // Bring in the translations ?>
    <?php $translationsDatatablesOverride = array('search'=>(xla('Search all columns') . ':')) ; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/datatables-net.js.php'); ?>
 } );

 // This puts our custom HTML into the table header.
 $("div.mytopdiv").html("<form name='myform'><input type='checkbox' name='form_new_window' value='1'<?php
    if (!empty($GLOBALS['gbl_pt_list_new_window'])) {
        echo ' checked';
    } ?> /><?php
  echo xlt('Open in New Window'); ?></form>");

 // This is to support column-specific search fields.
 // Borrowed from the multi_filter.html example.
 $("thead input").keyup(function () {
  // Filter on the column (the index) of this element
    oTable.fnFilter( this.value, $("thead input").index(this) );
 });

 // OnClick handler for the rows
 $('#pt_table').on('click', 'tbody tr', function () {
  // ID of a row element is pid_{value}
  var newpid = this.id.substring(4);
  // If the pid is invalid, then don't attempt to set
  // The row display for "No matching records found" has no valid ID, but is
  // otherwise clickable. (Matches this CSS selector).  This prevents an invalid
  // state for the PID to be set.
  if (newpid.length===0)
  {
      return;
  }
  if (document.myform.form_new_window.checked) {
   openNewTopWindow(newpid);
  }
  else {
   top.restoreSession();
   top.RTop.location = "../../patient_file/summary/demographics.php?set_pid=" + newpid;
  }
 } );

});

function openNewTopWindow(pid) {
 document.fnew.patientID.value = pid;
 top.restoreSession();
 document.fnew.submit();
}

</script>

</head>
<body class="body_top">

<div id="dynamic"><!-- TBD: id seems unused, is this div required? -->

<!-- Class "display" is defined in demo_table.css -->
<table cellpadding="0" cellspacing="0" border="0" class="display" id="pt_table">
 <thead>
  <tr class="filters">
  </tr>
  <tr class = "head">
<?php echo $header; ?>
  </tr>
 </thead>
 <tbody>
  <tr>
   <!-- Class "dataTables_empty" is defined in jquery.dataTables.css -->
   <td colspan="<?php echo $colcount; ?>" class="dataTables_empty">...</td>
  </tr>
 </tbody>
</table>

</div>

<!-- form used to open a new top level window when a patient row is clicked -->
<form name='fnew' method='post' target='_blank' action='../main_screen.php?auth=login&site=<?php echo attr($_SESSION['site_id']); ?>'>
<input type='hidden' name='patientID'      value='0' />
</form>

</body>
</html>

