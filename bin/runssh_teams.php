#!/usr/bin/php -q
<?php
/**
 * Program to run a specific command on all team accounts using ssh.
 * 
 * Usage: $0 <program>
 *
 * $Id$
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require ('../etc/config.php');

define ('SCRIPT_ID', 'runssh_teams');
define ('LOGFILE', LOGDIR.'/check.log');

require (SYSTEM_ROOT . '/lib/init.php');

$program = @$_SERVER['argv'][1];

if ( ! $program ) error("No program specified");
$program = escapeshellarg($program);

logmsg(LOG_DEBUG, "running program $program");

$teams = $DB->q('COLUMN SELECT login FROM team ORDER BY login');

foreach($teams as $team) {
	$team = escapeshellarg($team);
	logmsg(LOG_DEBUG, "running on account $team");
	system("ssh -l $team localhost $program",$exitcode);
	if ( $exitcode != 0 ) {
		logmsg(LOG_NOTICE, "on $team: exitcode $exitcode");
	}
}

logmsg(LOG_NOTICE, "finished");

exit;
