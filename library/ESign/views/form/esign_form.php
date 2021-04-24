<?php
/**
 * Signature form view script for form module
 *
 * Copyright (C) 2013 OEMR 501c3 www.oemr.org
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
 * @author  Ken Chapple <ken@mi-squared.com>
 * @author  Medical Information Integration, LLC
 * @link    http://www.open-emr.org
 **/

$res = sqlStatement("SELECT date FROM form_encounter WHERE encounter=?", array($this->form->encounterId));
$row = sqlFetchArray($res);
$enc_date = date('Y-m-d', strtotime($row['date']));

?>

<div id='esign-form-container'>
    <form id='esign-signature-form' method='post' action='<?php echo attr($this->form->action); ?>'>
        
        <div class="esign-signature-form-element">
              <span id='esign-signature-form-prompt'><?php echo xlt("Your password is your signature"); ?></span> 
        </div>

        <div class="esign-signature-form-element">
              <label for='password'><?php echo xlt('Password');?></label> 
              <input type='password' id='password' name='password' size='10' />
        </div>
        
        <?php if ($this->form->showLock) { ?>
        <div class="esign-signature-form-element">
              <label for='lock'><?php echo xlt('Lock?');?></label> 
              <input type="checkbox" id="lock" name="lock" />
        </div>
        <?php } ?>
        
        <div class="esign-signature-form-element">
              <textarea name='amendment' id='amendment' placeholder='<?php echo xlt("Enter an amendment..."); ?>'></textarea> 
        </div>
        
        <div class="esign-signature-form-element">
			<table>
			    <tr>
			        <td nowrap="">
			            <b>Date:</b>
			        </td>
			        <td nowrap="">
			            <input type="date" size="12" class="datepicker input-sm" name="date" id="date" value="<?php echo $enc_date; ?>" title="Signature date">
			        </td>
			        <td width="1%" nowrap="" id="tdallday2" style="color: rgb(0, 0, 0);">
			            Time
			        </td>
			        <td width="1%" nowrap="" id="tdallday3" style="color: rgb(0, 0, 0);">
			            <span>
			                <input class="input-sm" type="text" size="2" name="hour" value="<?php echo rand(21, 23); ?>" title="Signature hour"> :
			                <input class="input-sm" type="text" size="2" name="minute" value="<?php echo str_pad(rand(0, 59), 2, "0", STR_PAD_LEFT); ?>" title="Signature minute"> :
			                <input class="input-sm" type="text" size="2" name="second" value="<?php echo str_pad(rand(0, 59), 2, "0", STR_PAD_LEFT); ?>" title="Signature second">
			            </span>
			        </td>
			    </tr>
			</table>
        </div>

        <div class="esign-signature-form-element">
              <input type='submit' value='<?php echo xla('Back'); ?>' id='esign-back-button' /> 
              <input type='button' value='<?php echo xla('Sign'); ?>' id='esign-sign-button-form' />
        </div>
        
        <input type='hidden' id='formId' name='formId' value='<?php echo attr($this->form->formId); ?>' /> 
        <input type='hidden' id='table' name='table' value='<?php echo attr($this->form->table); ?>' /> 
        <input type='hidden' id='formDir' name='formDir' value='<?php echo attr($this->form->formDir); ?>' />
        <input type='hidden' id='encounterId' name='encounterId' value='<?php echo attr($this->form->encounterId); ?>' />
        <input type='hidden' id='userId' name='userId' value='<?php echo attr($this->form->userId); ?>' />
    </form> 
</div>
