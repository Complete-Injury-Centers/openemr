<?php
/**
 *
 * Review Tools
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

?>

<html>
<head>
    <?php Header::setupHeader(['opener']); ?>
    <title><?php echo xlt('Review Tools'); ?></title>

    <script language="javascript">
        function submit_esign() {
            dlgopen('all_esign_form.php','_blanck',440,200);
        }

        function submit_duplicates() {
            dlgopen('duplicate_notes.php','_blanck',500,145);
        }
    </script>
</head>

<body class="body_top">
    <form method='post' name="signNotes" action=''>
        <p class="lead">&nbsp;<br><?php echo xlt('Please choose one option:'); ?>
        <div class="btn-group">
            <span style="display:inline-block; width:100%; padding: 5px;">
                <a href="#" onclick="submit_esign()" class="btn btn-lg btn-save btn-default"><?php echo xlt('eSign all notes'); ?></a>
            </span>
            <span style="display:inline-block; width:100%; padding: 5px;">
                <a href="#" onclick="submit_duplicates()" class="btn btn-lg btn-save btn-default"><?php echo xlt('Check duplicate notes'); ?></a>
            </span>
            <span style="display:inline-block; width:100%; padding: 5px;">
                <a href='#' onclick="dlgclose();" class="btn btn-lg btn-link btn-cancel"><?php echo xlt('Close'); ?></a>
            </span>
        </div>
    </form>
</body>
</html>
