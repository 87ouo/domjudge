<?php
/**
 * View the languages
 *
 * $Id$
 */

require('init.php');
$title = 'Languages';

require('../header.php');

echo "<h1>Languages</h1>\n\n";

$res = $DB->q('SELECT * FROM language ORDER BY name');

if( $res->count() == 0 ) {
	echo "<p><em>No languages defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n" .
		"<tr><th>ID</th><th>name</th><th>extension</th>" .
		"<th>allow<br />submit</th><th>allow<br />judge</th>" .
		"<th>timefactor</th></tr>\n";
	while($row = $res->next()) {
		echo "<tr".
			( $row['allow_judge'] && $row['allow_submit'] ? '': ' class="disabled"').
			"><td><a href=\"language.php?id=".urlencode($row['langid'])."\">".
				htmlspecialchars($row['langid'])."</a>".
			"</td><td><a href=\"language.php?id=".urlencode($row['langid'])."\">".
				htmlentities($row['name'])."</a>".
			"</td><td class=\"filename\">.".htmlspecialchars($row['extension']).
			"</td><td align=\"center\">".printyn($row['allow_submit']).
			"</td><td align=\"center\">".printyn($row['allow_judge']).
			"</td><td>".htmlspecialchars($row['time_factor']);
			if ( IS_ADMIN ) {
				echo "</td><td>" . 
					editLink('language', $row['langid']) . " " .
					delLink('language','langid',$row['langid']);
			}
			"</td></tr>\n";
	}
	echo "</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" . addLink('language') . "</p>\n\n";
}


require('../footer.php');
