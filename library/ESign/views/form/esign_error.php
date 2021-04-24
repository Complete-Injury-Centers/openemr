<?php
/**
 * Signature error view script for form module
 *
 * Copyright (C) 2021 angeling <angel-na@hotmail.es>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
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
 **/
?>

<div id='esign-form-container'>
    <form id='esign-signature-form' method='post' action='<?php echo attr($this->form->action); ?>'>
        <div class="esign-signature-form-element">
              <span id='esign-signature-form-prompt'><?php echo xlt("Time note generated and time of current e-sign are too close"); ?></span> 
        </div>

        <div class="esign-signature-form-element">
              <input type='submit' value='<?php echo xla('Back'); ?>' id='esign-back-button' />
        </div>
    </form> 
</div>
