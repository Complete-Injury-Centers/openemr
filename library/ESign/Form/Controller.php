<?php

namespace ESign;

/**
 * Form controller implementation
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

require_once $GLOBALS['srcdir'].'/ESign/Abstract/Controller.php';
require_once $GLOBALS['srcdir'].'/ESign/Form/Configuration.php';
require_once $GLOBALS['srcdir'].'/ESign/Form/Factory.php';
require_once $GLOBALS['srcdir'].'/ESign/Form/Log.php';
require_once $GLOBALS['srcdir'].'/authentication/login_operations.php';

class Form_Controller extends Abstract_Controller
{
    /**
     *
     */
    public function esign_form_view()
    {
        $form = new \stdClass();
        $form->table = 'forms';
        $form->formDir = $this->getRequest()->getParam('formdir', '');
        $form->formId = $this->getRequest()->getParam('formid', 0);
        $form->encounterId = $this->getRequest()->getParam('encounterid', 0);
        $form->userId = $GLOBALS['authUserID'];
        $form->action = '#';
        $signable = new Form_Signable($form->formId, $form->formDir, $form->encounterId);
        $form->showLock = false;
        if ($signable->isLocked() === false &&
            $GLOBALS['lock_esign_individual'] &&
            $GLOBALS['esign_lock_toggle'] ) {
            $form->showLock = true;
        }

        $this->_view->form = $form;

        $res = sqlStatement("SELECT date FROM form_encounter WHERE encounter=?", array($form->encounterId));
        $row = sqlFetchArray($res);
        $enc_date = date('Y-m-d', strtotime($row['date']));
        $today = date('Y-m-d');

        $mins = (new \DateTime())->getOffset() / 60;
        $sgn = ($mins < 0 ? "-" : "+");
        $mins = abs($mins);
        $hrs = floor($mins / 60);
        $mins -= $hrs * 60;
        $offset = sprintf('%s%02d:%02d', $sgn, $hrs, $mins);
        $signInNote = sqlQuery("SELECT DATE_FORMAT(CONVERT_TZ(`date`, '+00:00', '". $offset ."'),'%I:%i %p') as `time` FROM forms WHERE formdir = 'LBFsignin' AND encounter = ". $GLOBALS['encounter'] . " ORDER BY id LIMIT 1");

        if($signInNote and ($today > $enc_date or ($today == $enc_date and (time() - strtotime($enc_date . $signInNote['time']) > 3600 or time() - strtotime($enc_date . " 19:00:00") > 0)))) {
            $this->setViewScript('form/esign_form.php');
        } else {
            $this->setViewScript('form/esign_error.php');
        }

        $this->render();
    }
    
    public function esign_log_view()
    {
        $formId = $this->getRequest()->getParam('formId', '');
        $formDir = $this->getRequest()->getParam('formDir', '');
        $encounterId = $this->getRequest()->getParam('encounterId', '');
        $factory = new Form_Factory($formId, $formDir, $encounterId);
        $signable = $factory->createSignable(); // Contains features that make object signable
        $log = $factory->createLog(); // Make the log behavior
        $html = $log->getHtml($signable);
        echo $html;
        exit;
    }
    
    /**
     *
     * @return multitype:string
     */
    public function esign_form_submit()
    {
        $message = '';
        $status = self::STATUS_FAILURE;
        $password = $this->getRequest()->getParam('password', '');
        $formId = $this->getRequest()->getParam('formId', '');
        $formDir = $this->getRequest()->getParam('formDir', '');
        $encounterId = $this->getRequest()->getParam('encounterId', '');
        $date = $this->getRequest()->getParam('date', date("Y-m-d"));
        $hour = $this->getRequest()->getParam('hour', date("H"));
        $minute = $this->getRequest()->getParam('minute', date("i"));
        $second = $this->getRequest()->getParam('second', date("s"));

        $date = $date . " " . $hour . ":" . $minute . ":" . $second;

        // Always lock, unless esign_lock_toggle option is enable in globals
        $lock = true;
        if ($GLOBALS['esign_lock_toggle']) {
            $lock = ( $this->getRequest()->getParam('lock', '') == 'on' ) ? true : false;
        }

        $amendment = $this->getRequest()->getParam('amendment', '');

        if ($GLOBALS['use_active_directory']) {
            $valid = active_directory_validation($_SESSION['authUser'], $password);
        } else {
            $valid = confirm_user_password($_SESSION['authUser'], $password);
        }

        if ($valid) {
            $factory = new Form_Factory($formId, $formDir, $encounterId);
            $signable = $factory->createSignable();
            if ($signable->sign($_SESSION['authUserID'], $lock, $amendment, $date)) {
                $message = xlt("Form signed successfully");
                $status = self::STATUS_SUCCESS;
            } else {
                $message = xlt("An error occured signing the form");
            }
        } else {
            $message = xlt("The password you entered is invalid");
        }

        $response = new Response($status, $message);
        $response->formId = $formId;
        $response->formDir = $formDir;
        $response->encounterId = $encounterId;
        $response->locked = $lock;
        $response->editButtonHtml = "";
        if ($lock) {
            // If we're locking the form, replace the edit button with a "disabled" lock button
            $response->editButtonHtml = "<a href=# class='css_button_small form-edit-button-locked' id='form-edit-button-'".attr($formDir)."-".attr($formId)."><span>".xlt('Locked')."</span></a>";
        }

        echo json_encode($response);
        exit;
    }
}
