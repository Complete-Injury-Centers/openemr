<?php
/**
 *
 * Check for duplicate notes
 *
 * Copyright (C) 2021 angeling <angel-na@hotmail.es>
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
 * @author  angeling <angel-na@hotmail.es>
 * @link    http://www.open-emr.org
 */

use OpenEMR\Core\Header;

require_once('../globals.php');
require_once($GLOBALS['srcdir'].'/log.inc');
require_once($GLOBALS['srcdir'].'/acl.inc');
require_once($GLOBALS['srcdir'].'/sl_eob.inc.php');

// Select objectives and subjectives of each patient's encounter
function checkNotes() {
    $duplicated_fields = array();
    
    // Check all repeated patient's notes
    $query = "SELECT field_id, field_value, COUNT(*) FROM lbf_data
        WHERE form_id IN (SELECT form_id FROM forms WHERE pid = ? AND deleted = 0 and authorized = 1
            AND form_name IN (SELECT DISTINCT grp_title FROM layout_group_properties WHERE grp_title != ''))
        AND (field_id = 'objective' OR field_id = 'subjective') GROUP BY field_value, field_id HAVING COUNT(*) > 1";

    $res = sqlStatement($query, $_SESSION['pid']);
    while($row = sqlFetchArray($res)) {
        $field = $row['field_id'];
        $values = array();

        // Select values id and date of the repeated
        $q = "SELECT form_encounter.date, forms.encounter FROM forms INNER JOIN lbf_data ON forms.form_id = lbf_data.form_id
            AND forms.deleted = 0 AND forms.authorized = 1 AND lbf_data.field_id = ? AND forms.pid = ? AND field_value = ?
            LEFT JOIN form_encounter ON form_encounter.encounter = forms.encounter";

        $rs = sqlStatement($q, array($row['field_id'], $_SESSION['pid'], $row['field_value']));
        while($rw = sqlFetchArray($rs)) {
            $values[] = [explode(" ",$rw['date'])[0], $rw['encounter']];
        }

        $duplicated_fields[] = [$field, $values];
    }

    if(sqlNumRows($res) == 0) {
        echo "alert('No encounter has notes.');";
    }
    if(count($duplicated_fields) > 0) {
        echo "alert('The following encounters have duplicated:";
        foreach($duplicated_fields as $fields) {
            echo "\\n - Same ".$fields[0].":";
            foreach($fields[1] as $value) {
                echo "\\r\\n   * ".$value[0]." (".$value[1].")";
            }
        }
        echo "\\r\\nPlease check and try again.');";
    } else {
        echo "alert('No encounter has duplicated notes.');";
    }
    
    echo "dlgclose();";
}
?>

<html>
<head>
    <?php Header::setupHeader(['opener']); ?>
    <title><?php echo xlt('No Duplicate Notes'); ?></title>
</head>

<body class="body_top">
    <form method='post' name="checkNotes" action=''>
        <p class="lead">&nbsp;<br><?php echo xlt('Do you want to check for duplicated notes?'); ?></p>
        <div class="btn-group">
            <a href="#" onclick="<?php checkNotes()?>" class="btn btn-lg btn-save btn-default"><?php echo xlt('Go');?></a>
            <a href='#' class="btn btn-lg btn-link btn-cancel" onclick="dlgclose();"><?php echo xlt('Cancel');?></a>
        </div>
    </form>
</body>
</html>
