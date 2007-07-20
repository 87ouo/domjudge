#!/usr/bin/php -q
<?php
/**
 * Program to run a specific command on all judgehosts using ssh.
 * 
 * Usage: $0 <program>
 *
 * $Id$
 */
if ( isset($_SERVER['REMOTE_ADDR']) ) die ("Commandline use only");

require ('../etc/config.php');

define ('SCRIPT_ID', 'runssh_judgehosts');
define ('LOGFILE', LOGDIR.'/check.log');

require (SYSTEM_ROOT . '/lib/init.php');

$program = @$_SERVER['argv'][1];

if ( ! $program ) error("No program specified");
$program = escapeshellarg($program);

logmsg(LOG_DEBUG, "running program $program");

$judgehosts = $DB->q('COLUMN SELECT hostname FROM judgehost ORDER BY hostname');

foreach($judgehosts as $host) {
	$host = escapeshellarg($host);
	logmsg(LOG_DEBUG, "running on judgehost $host");
	system("ssh $host $program",$exitcode);
	if ( $exitcode != 0 ) {
		logmsg(LOG_NOTICE, "on $host: exitcode $exitcode");
	}
}

logmsg(LOG_NOTICE, "finished");

exit;
