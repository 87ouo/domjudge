<?php
/**
 * Error handling functions
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
if ( ! defined('SCRIPT_ID') ) {
	define('SCRIPT_ID', basename($_SERVER['PHP_SELF'], '.php'));
}

// Default verbosity and loglevels:
$verbose  = LOG_NOTICE;
$loglevel = LOG_DEBUG;

// Is this the webinterface or commandline?
define('IS_WEB', isset($_SERVER['REMOTE_ADDR']));

// Open standard error:
if ( ! IS_WEB && ! defined('STDERR') ) {
	define('STDERR', fopen('php://stderr', 'w'));
}

// Open log file:
if ( defined('LOGFILE') ) {
	if ( $fp = @fopen(LOGFILE, 'a') ) {
		define('STDLOG', $fp);
	} else {
		fwrite(STDERR, "Cannot open log file " . LOGFILE .
			" for appending; continuing without logging.\n");
	}
	unset($fp);
}

// Open syslog connection:
if ( defined('SYSLOG')  ) {
//	echo 'SYSLOG = ' . SYSLOG;
	openlog(SCRIPT_ID, LOG_NDELAY | LOG_PID, SYSLOG);
}

/**
 * Log a message $string on the loglevel $msglevel.
 * Prepends a timestamp and logg to the logfile.
 * If this is the web interface: write to the screen with the right CSS class.
 * If this is the command line: write to Standard Error.
 */
function logmsg($msglevel, $string) {
	global $verbose, $loglevel;

	$stamp = "[" . date('M d H:i:s') . "] " . SCRIPT_ID . "[" . posix_getpid() . "]: ";
	$msg = $string . "\n";
	
	if ( $msglevel <= $verbose  ) {
		// if this is the webinterface, print it to stdout, else to stderr
		if ( IS_WEB ) {
			// string 'ERROR' parsed by submit client, don't modify!
			echo "<fieldset class=\"error\"><legend>ERROR</legend> " .
				htmlspecialchars($msg) . "</fieldset>\n";
			// Add strings for non-interactive parsing:
			if ( $msglevel == LOG_ERR     ) echo "\n<!-- @@@ERROR-$string@@@ -->\n";
			if ( $msglevel == LOG_WARNING ) echo "\n<!-- @@@WARNING-$string@@@ -->\n";
		} else {
			fwrite(STDERR, $stamp . $msg);
			fflush(STDERR);
		}
	}
	if ( $msglevel <= $loglevel ) {
		if ( defined('STDLOG') ) {
			fwrite(STDLOG, $stamp . $msg);
			fflush(STDLOG);
		}
		if ( defined('SYSLOG') ) {
			syslog($msglevel, $msg);
		}
	}
}

/**
 * Log an error at level LOG_ERROR and exit with exitcode 1.
 */
function error($string) {
	logmsg(LOG_ERR, "error: $string");
	exit(1);
}

/**
 * Log a warning at level LOG_WARNING.
 */
function warning($string) {
	logmsg(LOG_WARNING, "warning: $string");
}
