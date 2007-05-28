<?php
/**
 * Start of functionality to delete data from this interface.
 *
 * $Id$
 */
require('init.php');
requireAdmin();

require(SYSTEM_ROOT . '/lib/relations.php');

$t = @$_REQUEST['table'];

if(!$t)	error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$k = array();
foreach($KEYS[$t] as $key) {
	$k[$key] = @$_REQUEST[$key];
	if ( !$k[$key] ) error ("I can't find my keys.");
}

if ( isset($_POST['tochmaarniet']) ) {

	// this probably is not generic enough for the future, but
	// works for all our current tables.
	header('Location: '.getBaseURI().'jury/'.$t.'.php?id=' .
		urlencode(array_shift($k)));
	exit;
}

// Send headers here, because we need to be able to redirect above this point.

$title = 'Delete from ' . $t;
require('../header.php');

// Check if we can really delete this.
foreach($k as $key => $val) {
	if ( $errtable = fk_check ( "$t.$key", $val ) ) {
		error ( "$t.$key \"$val\" is still referenced in $errtable, cannot delete." );
	}
}

if (isset($_POST['zekerweten'] ) ) {

	// LIMIT 1 is a security measure to prevent our bugs from
	// wiping a table by accident.
	$DB->q("DELETE FROM $t WHERE %S LIMIT 1", $k);

	echo "<p>" . ucfirst($t) . " <strong>" . htmlspecialchars(implode(", ", $k)) . 
		"</strong> has been deleted.</p>\n\n";
	echo "<p><a href=\"" . $t . "s.php\">back to ${t}s</a></p>";

} else {
	echo "<form action=\"delete.php\" method=\"post\">\n" .
		"<input type=\"hidden\" name=\"table\" value=\"$t\" />\n";
	foreach ( $k as $key => $val ) {
		echo "<input type=\"hidden\" name=\"$key\" value=\"" . htmlspecialchars($val) ."\" />\n";
	}

	echo msgbox ( 
		"Really delete?",
		"You're about to delete $t <strong>" .
		htmlspecialchars(join(", ", array_values($k))) . "</strong>.<br /><br />\n\n" .
		"Are you sure?<br /><br />\n\n" .
		"<input type=\"submit\" name=\"tochmaarniet\" value=\" Never mind... \" />\n" .
		"<input type=\"submit\" name=\"zekerweten\" value=\" Yes I'm sure! \" />\n");

	echo "</form>\n\n";
}


require('../footer.php');
