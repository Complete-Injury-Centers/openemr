<?php
	require_once('../../globals.php');

	$id = $_GET['id'];

	$note = sqlQuery("SELECT `form_name`, `pid`, `user`, `groupname`, `authorized`, `formdir`, `issue_id`, `provider_id` from `forms` where form_id=".$id);

	$newNoteId = sqlInsert("INSERT INTO lbf_data ( field_id, field_value ) VALUES ( '', '' )");
	sqlQuery("DELETE FROM lbf_data WHERE form_id = '".$newNoteId."' AND field_id = ''");

	sqlQuery("INSERT INTO `forms` (`date`, `encounter`, `form_name`, `form_id`, `pid`, `user`, `groupname`, `authorized`, `deleted`, `formdir`, `issue_id`, `provider_id`) VALUES (NOW(), ".$GLOBALS['encounter'].", '".$note['form_name']."', ".$newNoteId.", ".$note['pid'].", '".$note['user']."', '".$note['groupname']."', ".$note['authorized'].", 0, '".$note['formdir']."', ".$note['issue_id'].", ".$note['provider_id'].")");

	$formValues = '';
	$res = sqlStatement("SELECT field_id, field_value FROM lbf_data WHERE form_id = ".$id);
	while ($row = sqlFetchArray($res)) {
		$formValues .= "(" . $newNoteId . ", '" . $row['field_id'] . "', '" . $row['field_value'] . "'), ";
 	}
 	if ($formValues != '') {
 		$formValues = rtrim($formValues, ', ');
	 	sqlInsert("INSERT INTO `lbf_data`(form_id, field_id, field_value) VALUES ".$formValues);
 	}
?>
