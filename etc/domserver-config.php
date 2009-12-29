<?php

require_once("common-config.php");

// Base URI where the DOMjudge webinterface is located.
// This is the full URL to the root of the 'www' dir.
// Examples:
//    https://domjudge.example.com/
//    http://www.example.edu/contest/domjudge/
// Always end this URI with a slash!!!
define('WEBBASEURI', 'http://example.com/domjudge/');

// Show compile output in team webinterface.
// Note that this might give teams the possibility to gain information
// about the judging system; e.g. which functions are usable or
// possibly system information through compiler directives.
// 0 = Never
// 1 = Only on compilation error(s)
// 2 = Always
define('SHOW_COMPILE', 2);

// Strict checking of team's IP addresses.
// The commandline submitdaemon can optionally check for correct source
// IP of teams (additionally to the security of "callback" via scp, see
// README on security).
// The 'false' setting allows automatic updating during submission of IP
// addresses of teams that have their address unset. Otherwise these
// addresses have to be configured beforehand.
define('STRICTIPCHECK', false);

define('LANG_EXTS', 'C,c C++,cpp,cc,c++ Java,java Pascal,pas,p Haskell,hs,lhs Perl,pl Bash,sh');

// Specify here which of the users in htpasswd-jury should have admin 
// rights on top of their jury rights
$DOMJUDGE_ADMINS = array('domjudge_jury', 'admin');

