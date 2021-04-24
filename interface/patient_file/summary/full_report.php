<?php
/**
 * Patient's Full Report.
 *
 * Copyright (C) 2021 Angel Navarro <angel-na@hotmail.es>
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
 * @author  Angel Navarro <angel-na@hotmail.es>
 * @link    http://www.open-emr.org
 */

require_once("../../globals.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/acl.inc");
require_once("$srcdir/options.inc.php");
require_once("../history/history.inc.php");
require_once("$srcdir/edi.inc");
require_once("$srcdir/invoice_summary.inc.php");
require_once("$srcdir/clinical_rules.php");
require_once("$srcdir/options.js.php");
require_once("$srcdir/group.inc");
require_once(dirname(__FILE__)."/../../../library/appointments.inc.php");

function fullReport() {
    $full_link = "../report/custom_report.php?printable=1&include_demographics=demographics&pdf=0&";
    $pres = sqlStatement("SELECT * FROM lists WHERE pid =". $GLOBALS['pid']);
    while ($prow = sqlFetchArray($pres)) {
        $full_link .= "&issue_".$prow['id'];
    }

    $res = sqlStatement("SELECT forms.encounter, forms.form_id, " .
        "forms.formdir, forms.date AS fdate, form_encounter.date " .
        "FROM forms, form_encounter WHERE " .
        "forms.pid = ".$GLOBALS['pid']." AND form_encounter.pid = ".$GLOBALS['pid']." AND " .
        "form_encounter.encounter = forms.encounter " .
        " AND forms.deleted = 0 ".
        "ORDER BY form_encounter.encounter ASC, form_encounter.date ASC, fdate ASC");
    while($row = sqlFetchArray($res)) {
        if($row['formdir'] != "newpatient"){
            $full_link .= "&".$row['formdir']."_".$row['form_id']."=".$row['encounter'];
        }
    }

    return $full_link;
}
?>

<html>
<head>
    <title>Full Report</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.0.943/pdf.min.js"></script>

    <link rel="stylesheet" href="/interface/themes/style_cobalt_blue.css?v=41" type="text/css">
    <link rel="stylesheet" type="text/css" href="/library/ESign/css/esign_report.css">
    <link rel="stylesheet" href="/interface/forms/track_anything/style.css" type="text/css">

    <style>
        .signature {
            max-width: 240px !important;
        }

        @media print {
            #print_btn_div {
                visibility: hidden;
                display: none;
            }

            .pagebreak {
                page-break-before: always;
            }

            #report_results {
                margin-top: 30px;
            }

            #report_header {
                visibility: visible;
                display: inline;
            }
        }

        @media screen {
            #pdf_renderer {
                visibility: hidden;
                display: none;
                margin: 0;
                padding: 0;
            }
            
            #report_header {
                visibility: hidden;
                display: none;
            }
        }
    </style>
</head>
<body>
    <div id="print_btn_div">
        <button id="print_btn" style="float:right; margin:10px; visibility:hidden; display:none;" class="btn btn-default btn-print" onclick="printFile()">
            <span><?php echo xlt('Print Full Report');?></span>
        </button>
    </div>

    <div id="pdf_renderer"></div>

    <div id="full_report">
        <div id="report"></div>

        <div class="pagebreak" id="ledger">
        </div>
    </div>

    <script>
        $(document).ready(function () {
            //POST/GET request for encounter information
            $.ajax({
                type: "POST",
                dataType: "text",
                url: "<?php echo fullReport()?>",
                async: false,
                data: {
                    pdf: true
                },
                success: function(data){
                    var code = new DOMParser();
                    var htmlDOM = code.parseFromString(data, "text/html");

                    document.getElementById("report").appendChild(htmlDOM.getElementById("report_custom"));
                }
            });

            //POST/GET request for ledger information
            $.ajax({
                type: "POST",
                dataType: "text",
                url: "../../reports/pat_ledger.php?form=1&patient_id=<?php echo $GLOBALS['pid']?>",
                async: false,
                data: {
                    form_from_date: "01/01/2000", //"< ?php echo date("m/d/").(date("Y") - 1);?>",
                    form_to_date: "<?php echo date('m/d/Y');?>",
                    form_patient: <?php echo $GLOBALS['pid']?>,
                    form_pid: <?php echo $GLOBALS['pid']?>,
                    patientID: 0,
                    form_refresh: true,
                },
                success: function(data){
                    var code = new DOMParser();
                    var htmlDOM = code.parseFromString(data,"text/html");

                    document.getElementById("ledger").appendChild(htmlDOM.getElementById("report_header"));
                    document.getElementById("ledger").appendChild(htmlDOM.getElementById("report_results"));
                }
            });
        });

        var myState = {
            pdf: null,
            pages: null,
            zoom: 1.8
        }
    
        pdfjsLib.getDocument("./cover.pdf")
            .then((pdf) => {
                myState.pdf = pdf;
                myState.pages = pdf.numPages;
                render();
            });

        function render() {
            var renderer = document.getElementById("pdf_renderer");

            for(let i = 1; i <= myState.pages; i++) {
                myState.pdf.getPage(i).then((page) => {
                    var canvas = document.createElement('canvas');
                    var ctx = canvas.getContext('2d');
                    var viewport = page.getViewport(myState.zoom);

                    canvas.width = viewport.width;
                    canvas.height = viewport.height;

                    page.render({
                        canvasContext: ctx,
                        viewport: viewport
                    });

                    renderer.appendChild(canvas);
                });
            }

            let btn = document.getElementById("print_btn");
            btn.style.visibility = "visible";
            btn.style.display = "inline";
        }

        function printFile() {
            <?php
                $patient = sqlQuery("SELECT fname,lname,refer_facilities from patient_data WHERE pid=?", array($GLOBALS['pid']));
        
                $q_fac = "SELECT name FROM facility WHERE id=? AND id IN (SELECT id FROM facility WHERE name='PI Medical Solutions' OR name='Prime Integrated Health Clinics')";
                $r_fac = sqlStatement($q_fac, array($patient['refer_facilities']));
            
                $filename = $patient['fname'] . " " . $patient['lname'] . " - ";
                if(sqlNumRows($r_fac)) {
                    $facility = sqlFetchArray($r_fac)['name'];
                    if($facility == 'PI Medical Solutions') {
                        $filename .= "PMS";
                    }
                    if($facility == 'Prime Integrated Health Clinics') {
                        $filename .= "Prime";
                    }
                } else {
                    $filename .= "CIC";
                }
                $filename .= " Bill and Records";
            ?>

            let tempTitle = window.parent.document.title;
            window.parent.document.title = '<?php echo $filename; ?>';
            window.print();
            window.parent.document.title = tempTitle;
        }
    </script>
</body>
</html>
