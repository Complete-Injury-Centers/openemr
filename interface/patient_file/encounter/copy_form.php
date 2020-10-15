<?php
	require_once('../../globals.php');

	$id = $_GET['id'];

	$note = sqlQuery("SELECT `form_name`, `pid`, `authorized`, `formdir`, `issue_id` from `forms` where form_id=".$id);

	$newNoteId = sqlInsert("INSERT INTO lbf_data ( field_id, field_value ) VALUES ( '', '' )");
	sqlQuery("DELETE FROM lbf_data WHERE form_id = '".$newNoteId."' AND field_id = ''");

	sqlInsert("INSERT INTO `forms` (`date`, `encounter`, `form_name`, `form_id`, `pid`, `user`, `groupname`, `authorized`, `deleted`, `formdir`, `issue_id`, `provider_id`) VALUES (NOW(), ".$GLOBALS['encounter'].", '".$note['form_name']."', ".$newNoteId.", ".$note['pid'].", '".$_SESSION["authUser"]."', '".$_SESSION['authGroup']."', ".$note['authorized'].", 0, '".$note['formdir']."', ".$note['issue_id'].", ".$_SESSION['authUserID'].")");

	$formValues = '';
	$res = sqlStatement("SELECT field_id, field_value FROM lbf_data WHERE form_id = ".$id);
	while ($row = sqlFetchArray($res)) {
		$formValues .= '(' . $newNoteId . ', "' . str_replace('"', '\'', $row['field_id']) . '", "' . str_replace('"', '\'', $row['field_value']) . '"), ';
	}
 	if ($formValues != '') {
		$formValues = rtrim($formValues, ', ');
		sqlInsert("INSERT INTO `lbf_data` (form_id, field_id, field_value) VALUES ".$formValues);
	}
	
	//Add price of the treatments to the fee sheet
	function addValues($value, $list_id, $encounter, $pid) {
		$values = explode('|', $value);
		foreach($values as $val) {
			$val = explode(":", $val);
			if($val[1] == "1") {
				$cres = sqlStatement("SELECT codes FROM list_options WHERE option_id=? AND list_id=?",array($val[0],$list_id));
				if($crow = sqlFetchArray($cres)) {
					$crow = explode(":", $crow['codes']);
					$drow = sqlStatement("SELECT code,code_text,modifier,pr_price FROM prices RIGHT JOIN codes ON prices.pr_id=codes.id WHERE codes.code=?",array($crow[1]));
					if($dres = sqlFetchArray($drow)) {
						//$userauthorized = $_SESSION['userauthorized']; // providerID it's always equal to 0, in case is needed uncomment this
						//$providerID  =  findProvider($pid, $encounter); // make sure findProvider() works
						//if ($providerID == '0') {
						//    $providerID = $userauthorized;//who is the default provider?
						//}
						addBilling2($encounter, $crow[0], $dres['code'], $dres['code_text'], $pid, '1', "0", $dres['modifier'], '1', $dres['pr_price']);
					}
				}
			}
		}
	}

	// addBilling() with support for this PHP file
	function addBilling2(
		$encounter_id,
		$code_type,
		$code,
		$code_text,
		$pid,
		$authorized = "0",
		$provider,
		$modifier = "",
		$units = "",
		$fee = "0.00",
		$ndc_info = '',
		$justify = '',
		$billed = 0,
		$notecodes = ''
	) {

		$sql = "insert into billing (date, encounter, code_type, code, code_text, " .
		"pid, authorized, user, groupname, activity, billed, provider_id, " .
		"modifier, units, fee, ndc_info, justify, notecodes) values (" .
		"NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)";
		return sqlStatement($sql, array( $encounter_id,$code_type,$code,$code_text,$pid,$authorized,$_SESSION['authId'],$_SESSION['authProvider'],$billed,$provider,$modifier,$units,$fee,$ndc_info,$justify,$notecodes));
	}

	$resq = sqlStatement("SELECT d.field_value,opt.list_id FROM lbf_data AS d LEFT JOIN forms AS f ON d.form_id=f.form_id LEFT JOIN layout_options AS opt ON f.formdir=opt.form_id WHERE opt.uor > 0 AND opt.field_id != '' AND opt.edit_options != 'H' AND opt.edit_options NOT LIKE '%0%' AND opt.data_type='25' AND d.form_id=? AND opt.field_id=d.field_id", array($newNoteId));
	while($rowq = sqlFetchArray($resq)) {
		addValues($rowq['field_value'], $rowq['list_id'], $GLOBALS['encounter'], $note['pid']);
	}
?>
