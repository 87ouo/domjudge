<?php
/**
 * Show source code from the database.
 *
 * $Id$
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');

$id = (int)$_GET['id'];

$source = $DB->q('MAYBETUPLE SELECT * FROM submission
                  LEFT JOIN language USING(langid)
                  WHERE submitid = %i',$id);
if ( empty($source) ) error ("Submission $id not found");

$sourcefile = getSourceFilename($source['cid'],$id,$source['teamid'],
	$source['probid'],$source['extension']);

// Download was requested
if ( isset($_GET['fetch']) ) {
	header("Content-Type: text/plain; name=\"$sourcefile\"; charset=" . DJ_CHARACTER_SET);
	header("Content-Disposition: inline; filename=\"$sourcefile\"");
	header("Content-Length: " . strlen($source['sourcecode']));

	echo $source['sourcecode'];
	exit;
}

$oldsource = $DB->q('MAYBETUPLE SELECT * FROM submission
                     LEFT JOIN language USING(langid)
                     WHERE teamid = %s AND probid = %s AND langid = %s AND
                     submittime < %s ORDER BY submittime DESC LIMIT 1',
                    $source['teamid'],$source['probid'],$source['langid'],
                    $source['submittime']);

// Use PEAR Text::Highlighter class if available
if ( include_highlighter() ) {
	switch (strtolower($source['langid'])) {
		case 'c':
		case 'cpp':
			$lang = 'cpp';
			break;
		case 'java';
		case 'perl':
		case 'ruby':
		case 'php':
		case 'python':
			$lang = $source['langid'];
	}
	if ( isset($lang) ) {
		include('Text/Highlighter/Renderer/Html.php');
		$renderer = new Text_Highlighter_Renderer_Html(
			array("numbers" => HL_NUMBERS_TABLE, "tabsize" => 4));
		$hl =& Text_Highlighter::factory($lang);
	}
}


$title = 'Source: ' . htmlspecialchars($sourcefile);
require(LIBWWWDIR . '/header.php');

if ( $oldsource ) {
	echo "<p><a href=\"#diff\">Go to diff to previous submission</a></p>\n\n";
}

echo '<h2 class="filename"><a name="source"></a>Submission ' .
	"<a href=\"submission.php?id=$id\">s$id</a> source: " .
	htmlspecialchars($sourcefile) . " (<a " .
	"href=\"show_source.php?id=$id&amp;fetch=1\">download</a>)</h2>\n\n";

if ( strlen($source['sourcecode'])==0 ) {
	// Someone submitted an empty file. Cope gracefully.
	echo "<p><em>empty file</em></p>\n\n";
} elseif ( isset($hl) && strlen($source['sourcecode']) < 5 * 1024 ) {
	// Highlighter available and source < 5Kb (for longer source code,
	// Highlighter tends to take very long time or timeout)
	$hl->setRenderer($renderer);
	echo $hl->highlight($source['sourcecode']);
} else {
	// else display it ourselves
	$sourcelines = explode("\n", $source['sourcecode']);
	echo '<pre class="output_text">';
	$i = 1;
	$lnlen = strlen(count($sourcelines));
	foreach ($sourcelines as $line ) {
		echo "<span class=\"lineno\">" . str_pad($i, $lnlen, ' ', STR_PAD_LEFT) .
			"</span>  " . htmlspecialchars($line) . "\n";
		$i++;
	}
	echo "</pre>\n\n";
}


// show diff to old source
if ( $oldsource ) {

	$oldsourcefile = getSourceFilename($oldsource['cid'],$oldsource['submitid'],
	                                   $oldsource['teamid'],$oldsource['probid'],
	                                   $oldsource['extension']);

	$oldfile = SUBMITDIR.'/'.$oldsourcefile;
	$newfile = SUBMITDIR.'/'.$sourcefile;
	$oldid = (int)$oldsource['submitid'];

	// Try different ways of diffing, in order of preference.
	if ( function_exists('xdiff_string_diff') ) {
		// The PECL xdiff PHP-extension.

		$difftext = xdiff_string_diff($oldsource['sourcecode'],
		                              $source['sourcecode'],2);

	} elseif ( !(bool) ini_get('safe_mode') ||
		       strtolower(ini_get('safe_mode'))=='off' ) {
		// Only try executing diff when safe_mode is off, otherwise
		// the shell_exec will fail.

		if ( is_readable($oldfile) && is_readable($newfile) ) {
			// A direct diff on the sources in the SUBMITDIR.

			$difftext = `diff -bBt -U 2 $oldfile $newfile 2>&1`;

		} else {
			// Try generating temporary files for executing diff.

			$oldfile = mkstemps(TMPDIR."/source-old-s$oldid-XXXXXX",0);
			$newfile = mkstemps(TMPDIR."/source-new-s$id-XXXXXX",0);

			if( ! $oldfile || ! $newfile ) {
				$difftext = "DOMjudge: error generating temporary files for diff.";
			} else {
				$oldhandle = fopen($oldfile,'w');
				$newhandle = fopen($newfile,'w');

				if( ! $oldhandle || ! $newhandle ) {
					$difftext = "DOMjudge: error opening temporary files for diff.";
				} else {
					if ( (fwrite($oldhandle,$oldsource['sourcecode'])===FALSE) ||
					     (fwrite($newhandle,   $source['sourcecode'])===FALSE) ) {
						$difftext = "DOMjudge: error writing temporary files for diff.";
					} else {
						$difftext = `diff -bBt -U 2 $oldfile $newfile 2>&1`;
					}
				}
				if ( $oldhandle ) fclose($oldhandle);
				if ( $newhandle ) fclose($newhandle);
			}

			if ( $oldfile ) unlink($oldfile);
			if ( $newfile ) unlink($newfile);
		}
	} else {
		$difftext = "DOMjudge: diff functionality not available in PHP or via shell_exec.";
	}

	echo '<h2 class="filename"><a name="diff"></a>Diff to submission ' .
		"<a href=\"submission.php?id=$oldid\">s$oldid</a> source: " .
		"<a href=\"show_source.php?id=$oldid\">" .
		htmlspecialchars($oldsourcefile) . "</a></h2>\n\n";

	echo '<pre class="output_text">' .
		htmlspecialchars($difftext) . "</pre>\n\n";
}

require(LIBWWWDIR . '/footer.php');
