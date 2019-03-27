<?php declare(strict_types=1);
/**
 * Produce a total score. Call with URL parameter 'static' for
 * output suitable for static HTML pages.
 *
 * Part of the DOMjudge Programming Contest Jury System and licensed
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = "Scoreboard";
$isstatic = isset($_REQUEST['static']);

// set auto refresh
$refresh = array(
    "after" => "30",
    "url" => "./",
);
if ($isstatic) {
    $refresh['url'] .= '?static=1';
}

// This reads and sets a cookie, so must be called before headers are sent.
$filter = initScorefilter();

$menu = !$isstatic;
require(LIBWWWDIR . '/header.php');

// TODO: make this banner contest specific? perhaps add it to the DB?
$banner = WEBAPPDIR . '/web/images/banner.png';
if ($isstatic && is_readable($banner)) {
  echo '<img class="banner" src="../images/banner.png" />';
}

if ($isstatic) {
       echo '<div class="alert alert-danger" role="alert">' .
               'This is just a test contest with random submissions, no real data.' .
               '</div>';
}


if ($isstatic && isset($_REQUEST['contest'])) {
    if ($_REQUEST['contest'] === 'auto') {
        $a = null;
        foreach ($cdatas as $c) {
            if (!$c['public'] || !$c['enabled']) {
                continue;
            }
            if (is_null($a) || $a < $c['activatetime']) {
                $a = $c['activatetime'];
                $cdata = $c;
            }
        }
    } else {
        $found = false;
        foreach ($cdatas as $c) {
            if ($c['externalid'] == $_REQUEST['contest'] ||
                $c['cid'] == $_REQUEST['contest']) {
                $cdata = $c;
                $found = true;
                break;
            }
        }
        if (!$found) {
            error("Specified contest not found");
        }
    }
}

// call the general putScoreBoard function from scoreboard.php
putScoreBoard($cdata, null, $isstatic, $filter);

echo "<script>initFavouriteTeams();</script>";

require(LIBWWWDIR . '/footer.php');
