<?php
/* These configuration settings are common to all parts of the
 * DOMjudge system and included by the specific configuration files.
 */

// Is verification of judgings by jury required before publication?
define('VERIFICATION_REQUIRED', false);

// Maximum allowed size in KB of source submissions.
define('SOURCESIZE', 256);

// Penalty time in minutes per wrong submission (if finally solved).
define('PENALTY_TIME', 20);

// Internal and output character set used, don't change.
define('DJ_CHARACTER_SET', 'utf-8');

// Possible exitcodes from test_solution.sh and their meaning
$EXITCODES = array (
	0   => 'correct',
	101 => 'compiler-error',
	102 => 'timelimit',
	103 => 'run-error',
	104 => 'no-output',
	105 => 'wrong-answer',
	106 => 'presentation-error',
	107 => 'memory-limit',
	108 => 'output-limit',
	127 => 'internal-error'
	);

/** Loglevels and debugging */

// Log to syslog facility; leave empty to disable.
define('SYSLOG', LOG_LOCAL0);

// Set DEBUG as a bitmask of the following settings.
// Of course never to be used on live systems!

define('DEBUG_PHP_NOTICE', 1); // Display PHP notice level warnings
define('DEBUG_TIMINGS',    2); // Display timings for loading webpages
define('DEBUG_SQL',        4); // Display SQL queries on webpages
define('DEBUG_JUDGE',      8); // Display judging scripts debug info

define('DEBUG', 1);

// By default report all PHP errors, except notices.
error_reporting(E_ALL & ~E_NOTICE);

// Set error reporting to all in debugging mode
if ( DEBUG & DEBUG_PHP_NOTICE ) error_reporting(E_ALL);
