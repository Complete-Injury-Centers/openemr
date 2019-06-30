<?php
// Copyright (C) 2012 Rod Roark <rod@sunsetsystems.com>
// Sponsored by David Eschelbacher, MD
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

require_once("../../globals.php");
require_once($GLOBALS['srcdir']."/options.inc.php");
require_once("../../../library/appointments.inc.php");
include_once("$srcdir/transactions.inc");

$popup = empty($_REQUEST['popup']) ? 0 : 1;

$specialItems = array("visits", "scheduled", "compliance", "referrals", "lastVisit");

// With the ColReorder or ColReorderWithResize plug-in, the expected column
// ordering may have been changed by the user.  So we cannot depend on
// list_options to provide that.
//
$aColumns = explode(',', $_GET['sColumns']);

// Paging parameters.  -1 means not applicable.
//
$iDisplayStart  = isset($_GET['iDisplayStart' ]) ? 0 + $_GET['iDisplayStart' ] : -1;
$iDisplayLength = isset($_GET['iDisplayLength']) ? 0 + $_GET['iDisplayLength'] : -1;
$limit = '';
if ($iDisplayStart >= 0 && $iDisplayLength >= 0) {
    $limit = "LIMIT " . escape_limit($iDisplayStart) . ", " . escape_limit($iDisplayLength);
}

// Column sorting parameters.
//
$orderby = '';
if (isset($_GET['iSortCol_0'])) {
    for ($i = 0; $i < intval($_GET['iSortingCols']); ++$i) {
        $iSortCol = intval($_GET["iSortCol_$i"]);
        if ($_GET["bSortable_$iSortCol"] == "true") {
            $sSortDir = escape_sort_order($_GET["sSortDir_$i"]); // ASC or DESC
      // We are to sort on column # $iSortCol in direction $sSortDir.
            $orderby .= $orderby ? ', ' : 'ORDER BY ';
      //
            if (!in_array($aColumns[$iSortCol], $specialItems)) {
                $orderby .= "`" . escape_sql_column_name($aColumns[$iSortCol], array('patient_data')) . "` $sSortDir";
            }
        }
    }
}

// Global filtering.
//
$where = '';
if (isset($_GET['sSearch']) && $_GET['sSearch'] !== "") {
    $sSearch = add_escape_custom(trim($_GET['sSearch']));
    foreach ($aColumns as $colname) {
	    if (!in_array($colname, $specialItems)) {
	        $where .= $where ? "OR " : "WHERE ( ";
            $where .= appendWhere($colname, $sSearch);
        }
    }

    if ($where) {
        $where .= ")";
    }
}

// Column-specific filtering.
//
for ($i = 0; $i < count($aColumns); ++$i) {
    $colname = $aColumns[$i];
    if (!in_array($colname, $specialItems) && isset($_GET["bSearchable_$i"]) && $_GET["bSearchable_$i"] == "true" && $_GET["sSearch_$i"] != '') {
        $where .= $where ? ' AND' : 'WHERE';
        $sSearch = add_escape_custom($_GET["sSearch_$i"]);
        $where .= appendWhere($colname, $sSearch);
    }
}

// If no filtering is being done on the facility,
// check if the user has permissions for all facilities.
if (!isset($facilityId) && !acl_check('admin', 'super')) {
	$facilities = array();
    $facilityres = sqlStatement("SELECT facility_id as id FROM users_facility WHERE table_id = '".$_SESSION['authUserID']."'");
	while ($row = sqlFetchArray($facilityres)) {
		$facilities[] = $row['id'];
	}

	$where .= $where ? ' AND' : 'WHERE';
	$where .= " `refer_facilities` IN (" . implode($facilities, ",") . ")";
}

// Compute list of column names for SELECT clause.
// Always includes pid because we need it for row identification.
//
$sellist = 'pid';
foreach ($aColumns as $colname) {
    if ($colname == 'pid' || in_array($colname, $specialItems)) {
        continue;
    }

    $sellist .= ", `" . escape_sql_column_name($colname, array('patient_data')) . "`";
}

// Get total number of rows in the table.
//
$row = sqlQuery("SELECT COUNT(id) AS count FROM patient_data");
$iTotal = $row['count'];

// Get total number of rows in the table after filtering.
//
$row = sqlQuery("SELECT COUNT(id) AS count FROM patient_data $where");
$iFilteredTotal = $row['count'];

// Build the output data array.
//
$out = array(
  "sEcho"                => intval($_GET['sEcho']),
  "iTotalRecords"        => $iTotal,
  "iTotalDisplayRecords" => $iFilteredTotal,
  "aaData"               => array()
);

// save into variable data about fields of 'patient_data' from 'layout_options'
$fieldsInfo = array();
$quoteSellist = preg_replace('/(\w+)/i', '"${1}"', str_replace('`', '', $sellist));
$res = sqlStatement('SELECT data_type, field_id, list_id FROM layout_options WHERE form_id = "DEM" AND field_id IN(' . $quoteSellist . ')');
while ($row = sqlFetchArray($res)) {
    $fieldsInfo[$row['field_id']] = $row;
}

$query = "SELECT $sellist FROM patient_data $where $orderby $limit";
$res = sqlStatement($query);
while ($row = sqlFetchArray($res)) {
  // Each <tr> will have an ID identifying the patient.
    $arow = array('DT_RowId' => 'pid_' . $row['pid']);
    foreach ($aColumns as $colname) {
		if (!in_array($colname, $specialItems)) {
            $arow[] = isset($fieldsInfo[$colname]) ? attr(generate_plaintext_field($fieldsInfo[$colname], $row[$colname])) : attr($row[$colname]);
        }
    }

    $encounters = sqlStatement('SELECT date FROM form_encounter WHERE pid = '.$row['pid'].' ORDER BY date desc');
    $visits = sqlNumRows($encounters);

    if ($_GET["sSearch_10"] !== '' && $_GET["sSearch_10"] != $visits) {
        continue;
    }
    $arow[] = $visits;

    $appointments = fetchAppointments("2019-01-01", date("Y-m-d"), $row['pid']);
    $total = count($appointments);

    if ($_GET["sSearch_11"] !== '' && $_GET["sSearch_11"] != $total) {
        continue;
    }
    $arow[] = $total;
    $compliance = $total ? round( ( $visits / $total ) * 100 ) : 0;

    $compliance = $compliance > 100 ? 100 : $compliance;
    $compliance = $compliance . "%";

    if ($_GET["sSearch_12"] !== '' && $_GET["sSearch_12"] != $compliance) {
        continue;
    }
    $arow[] = $compliance;

    $referralData = getTransByPid($row['pid']);
    $refSent = 0;
    $refReceived = 0;
    foreach ($referralData as $item) {
        if ($item['refer_reportreceived'] === 'Yes') {
            $refReceived++;
        }

        if ($item['refer_referralstataus'] === 'sentreferral') {
            $refSent++;
        }
    }

    $referralString = $refSent . " sent / " . $refReceived . " received";
    if ($_GET["sSearch_13"] !== '' && $_GET["sSearch_13"] != $referralString) {
        continue;
    }
    $arow[] = $referralString;

    $lastVisit = $visits ? substr(sqlFetchArray($encounters)['date'], 0, 10) : '';
    if ($_GET["sSearch_14"] !== '' && $_GET["sSearch_14"] != $lastVisit) {
        continue;
    }
    $arow[] = $lastVisit;

    $out['aaData'][] = $arow;
}

// error_log($query); // debugging

// Dump the output array as JSON.
//
// Encoding with options for escaping a special chars - JSON_HEX_TAG (<)(>), JSON_HEX_AMP(&), JSON_HEX_APOS('), JSON_HEX_QUOT(").
echo json_encode($out, 15);

function appendWhere($colname, $sSearch) {
    if ($colname == 'refer_facilities') {
        $facilityId = sqlQuery("SELECT id FROM facility WHERE name LIKE '$sSearch%'");
        return " `" . escape_sql_column_name($colname, array('patient_data')) . "` = '" .$facilityId['id']. "'";
    } else if ($colname == 'lawyer') {
        $lawyerId = sqlQuery("SELECT id FROM users WHERE organization LIKE '$sSearch%'");
        return " `" . escape_sql_column_name($colname, array('patient_data')) . "` = '" .$lawyerId['id']. "'";
    } else {
        return " `" . escape_sql_column_name($colname, array('patient_data')) . "` LIKE '$sSearch%'";
    }
}
