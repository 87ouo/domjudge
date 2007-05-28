<?php
/**
 * Functionality to edit data from this interface.
 *
 * TODO:
 *  - Does not support checkboxes yet, since these
 *    return no value when not checked.
 *
 * $Id$
 */
require('init.php');
requireAdmin();

$cmd = @$_POST['cmd'];
if ( $cmd != 'add' && $cmd != 'edit' ) error ("Unknown action.");

require(SYSTEM_ROOT . '/lib/relations.php');

$t = @$_POST['table'];
if(!$t)	error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$data          = $_POST['data'];
$keydata       = @$_POST['keydata'];
$skipwhenempty = @$_POST['skipwhenempty'];

if ( empty($data) ) error ("No data.");

require('checkers.php');

foreach ($data as $i => $itemdata ) {
	if ( !empty($skipwhenempty) && empty($itemdata[$skipwhenempty]) ) {
		continue;
	}

	$fn = "check_$t";
	if ( function_exists($fn) ) {
		$itemdata = $fn($itemdata);
	}
	check_sane_keys($itemdata);

	if ( $cmd == 'add' ) {
		$newid = $DB->q("RETURNID INSERT INTO $t SET %S", $itemdata);
		foreach($KEYS[$t] as $tablekey) {
			if ( isset($itemdata[$tablekey]) ) {
				$newid = $itemdata[$tablekey];
			}
		}
	} elseif ( $cmd == 'edit' ) {
		foreach($KEYS[$t] as $tablekey) {
				$prikey[$tablekey] = $keydata[$i][$tablekey];
		}
		check_sane_keys($prikey);

		$DB->q("UPDATE $t SET %S WHERE %S", $itemdata, $prikey);
	}
}

// when inserting/updating multiple rows, throw the user
// back to the overview for that data, otherwise to the
// page pertaining to the one item they added/edited.
if ( count($data) > 1 ) {
	$tablemulti = ($t == 'team_category' ? 'team_categories' : $t.'s');
	header('Location: '.getBaseURI().'jury/'.$tablemulti.'.php');

} else {
	if ( $cmd == 'add' ) {
		header('Location: '.getBaseURI().'jury/'.$t.'.php?id=' .
			urlencode($newid));
	} else {
		header('Location: '.getBaseUri().'jury/'.$t.'.php?id=' .
			urlencode(array_shift($prikey)));
	}	
}

function check_sane_keys($itemdata) {
	foreach(array_keys($itemdata) as $key) {
		if ( ! preg_match ('/^\w+$/', $key ) ) error ("Invalid characters in field name \"$key\".");
	}
}
