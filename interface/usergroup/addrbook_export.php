<?php
// Copyright (C) 2020 angeling
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

include_once("../globals.php");
include_once("$srcdir/acl.inc");
require_once("$srcdir/options.inc.php");

function exportCSV() {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="addrbook.csv"');

    // clean output buffer
    ob_end_clean();

    $addrCSV[0] = array("Email Address", "Organization", "First Name", "Last Name", "Phone", "Address", "Zip code", "Website");

    $query = "SELECT u.email, u.street, u.city, u.state, u.zip, u.phonew1, u.organization, u.url, u.lname, u.fname, ".
        "lo.option_id AS ab_name, lo.option_value as ab_option FROM users AS u " .
        "LEFT JOIN list_options AS lo ".
        "ON list_id = 'abook_type' AND option_id = u.abook_type AND activity = 1 ".
        "WHERE u.active = 1 AND ( u.authorized = 1 OR u.username = '' ) AND u.abook_type LIKE 'lawyer_firm'";
    $res = sqlStatement($query);

    $c = 1;
    while($row = sqlFetchArray($res)) {
        $emails = preg_replace('/\s+/', '', $row['email']);
        $organization = $row['organization'];
        $fname = $row['fname'];
        $lname = $row['lname'];
        $phone = $row['phonew1'];
        $addr = $row['street'].($row['street']?", ":"").$row['city'].($row['city']?", ":"").$row['state'];
        $zip = $row['zip'];
        $url = $row['url'];

        foreach(explode(",", $emails) as $email) {
            $addrCSV[$c] = array($email,$organization,$fname,$lname,$phone,$addr,$zip,$url);
            $c++;  
        }
    }

    $fp = fopen('php://output', 'wb');
    foreach($addrCSV as $line) {
        fputcsv($fp, $line, ',');
    }
    fclose($fp);

    exit();
}

if($_GET['download']) {
    exportCSV();
}
?>

<html>
<head>
    <title><?php echo xlt('Export CSV'); ?></title>
    <script type="text/javascript" src="<?php echo $webroot ?>/interface/main/tabs/js/include_opener.js"></script>
    <link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
    <script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-9-1/index.js"></script>

    <style>
    .title {
        background-color: white;
        padding: 1px 6px;
    }
    </style>

    <script>
    function exportCSV() {
        window.location.href = "addrbook_export.php?download=1";
        setTimeout(function () { window.close();}, 1200);
    }
    </script>
</head>
<body class="body_top">
    <div class="title">
        <h2>Download CSV</h2>
    </div>
    <br />
    <center>
    <input type='button' class='btn btn-primary' value='<?php echo xla("Save CSV");?>'
            onclick='exportCSV()'/>&nbsp;&nbsp;
    <input type='button' class='btn btn-primary' value='<?php echo xla("Cancel");?>'
            onclick='window.close();'/>
    </center>
    <?php $use_validate_js = 1;?>
    <?php validateUsingPageRules($_SERVER['PHP_SELF']);?>
</body>
</html>
