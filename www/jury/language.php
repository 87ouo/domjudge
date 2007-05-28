<?php
/**
 * View a language
 *
 * $Id$
 */

$pagename = basename($_SERVER['PHP_SELF']);

$id = @$_REQUEST['id'];

require('init.php');
$title = 'Language '.htmlspecialchars(@$id);

if ( isset($_POST['cmd']) ) {
	$pcmd = $_POST['cmd'];
} elseif ( isset($_GET['cmd'] ) ) {
	$cmd = $_GET['cmd'];
}

if ( !empty($pcmd) ) {

	if ( ! $id ) error("Missing or invalid language id");

	if ( isset($pcmd['toggle_submit']) ) {
		$DB->q('UPDATE language SET allow_submit = %i WHERE langid = %s',
		       $_POST['val']['toggle_submit'], $id);
	}

	if ( isset($pcmd['toggle_judge']) ) {
		$DB->q('UPDATE language SET allow_judge = %i WHERE langid = %s',
		       $_POST['val']['toggle_judge'], $id);
	}
}

require('../header.php');
require('../forms.php');

if ( IS_ADMIN && !empty($cmd) ):
	
	echo "<h2>" . ucfirst($cmd) . " language</h2>\n\n";

	echo addForm('edit.php');

	echo "<table>\n";

	if ( $cmd == 'edit' ) {
		echo "<tr><td>Language ID:</td><td>";
		$row = $DB->q('TUPLE SELECT * FROM language WHERE langid = %s',
			$_GET['id']);
		echo addHidden('keydata[0][langid]', $row['langid']);
		echo htmlspecialchars($row['langid']);
	} else {
		echo "<tr><td><label for=\"data_0__langid_\">Language ID:</label></td><td>";
		echo addInput('data[0][langid]', null, 8, 8);
	}
	echo "</td></tr>\n";

?>
<tr><td><label for="data_0__name_">Language name:</label></td>
<td><?=addInput('data[0][name]', @$row['name'], 20, 255)?></td></tr>

<tr><td><label for="data_0__extension_">Extension:</label></td>
<td class="filename">.<?=addInput('data[0][extension]', @$row['extension'], 5, 5)?></td></tr>

<tr><td>Allow submit:</td>
<td><?=addRadioBox('data[0][allow_submit]', (!isset($row['allow_submit']) || $row['allow_submit']), 1)?> <label for="data_0__allow_submit_1">yes</label>
<?=addRadioBox('data[0][allow_submit]', (isset($row['allow_submit']) && !$row['allow_submit']), 0)?> <label for="data_0__allow_submit_0">no</label></td></tr>

<tr><td>Allow judge:</td>
<td><?=addRadioBox('data[0][allow_judge]', (!isset($row['allow_judge']) || $row['allow_judge']), 1)?> <label for="data_0__allow_judge_1">yes</label>
<?=addRadioBox('data[0][allow_judge]', (isset($row['allow_judge']) && !$row['allow_judge']), 0)?> <label for="data_0__allow_judge_0">no</label></td></tr>

<tr><td><label for="data_0__time_factor_">Time factor:</label></td>
<td><?=addInput('data[0][time_factor]', @$row['time_factor'], 5, 5)?> x</td></tr>
</table>

<?php
echo addHidden('cmd', $cmd) .
	addHidden('table','language') .
	addSubmit('Save') .
	addEndForm();

require('../footer.php');
exit;

endif;

if ( ! $id ) error("Missing or invalid language id");


echo "<h1>Language ".htmlspecialchars($id)."</h1>\n\n";

$data = $DB->q('TUPLE SELECT * FROM language WHERE langid = %s', $id);

echo addForm($pagename) . "<p>\n" .
	addHidden('id', $id) .
	addHidden('val[toggle_judge]',  !$data['allow_judge']) .
	addHidden('val[toggle_submit]', !$data['allow_submit']).
	"</p>\n";

?>
<table>
<tr><td>ID:          </td><td><?=htmlspecialchars($data['langid'])?></td></tr>
<tr><td>Name:        </td><td><?=htmlentities($data['name'])?></td></tr>
<tr><td>Extension:   </td><td class="filename">.<?=htmlspecialchars($data['extension'])?></td></tr>
<tr><td>Allow submit:</td><td><?=printyn($data['allow_submit']) . ' '.
	addSubmit('toggle', 'cmd[toggle_submit]',
		"return confirm('" . ($data['allow_submit'] ? 'Disallow' : 'Allow') .
		" submissions for this language?')"); ?>
</td></tr>
<tr><td>Allow judge: </td><td><?=printyn($data['allow_judge']) . ' ' .
	addSubmit('toggle', 'cmd[toggle_judge]',
		"return confirm('" . ($data['allow_judge'] ? 'Disallow' : 'Allow') .
		" judging for this language?')"); ?>
</td></tr>
<tr><td>Time factor:  </td><td><?=htmlspecialchars($data['time_factor'])?> x</td></tr>
</table>

<?php
echo addEndForm();

echo "<p>" . rejudgeForm('language',$data['langid']) . "</p>\n\n";

if ( IS_ADMIN ) {
	echo "<p>" . 
		editLink('language', $data['langid']) . " " .
		delLink('language','langid',$data['langid']) . "</p>\n\n";
}
echo "<h2>Submissions in " . htmlspecialchars($id) . "</h2>\n\n";

$restrictions = array( 'langid' => $id );
putSubmissions($restrictions, TRUE);

require('../footer.php');
