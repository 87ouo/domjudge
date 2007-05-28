<?php
/**
 * View the teams
 *
 * $Id$
 */

require('init.php');
$title = 'Teams';

$cid = getCurContest();

$teams = $DB->q('SELECT t.*,c.name AS catname,a.name AS affname
                 FROM team t
                 LEFT JOIN team_category c USING (categoryid)
                 LEFT JOIN team_affiliation a ON (t.affilid = a.affilid)
                 ORDER BY c.sortorder, t.name');

$nsubmits = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, COUNT(teamid) AS cnt
                    FROM submission s
                    WHERE cid = %i GROUP BY teamid', $cid);

$ncorrect = $DB->q('KEYTABLE SELECT teamid AS ARRAYKEY, COUNT(teamid) AS cnt
                    FROM submission s
                    LEFT JOIN judging j USING (submitid)
                    WHERE j.valid = 1 AND j.result = "correct" AND s.cid = %i
                    GROUP BY teamid', $cid);

require('../header.php');

echo "<h1>Teams</h1>\n\n";

if( $teams->count() == 0 ) {
	echo "<p><em>No teams defined</em></p>\n\n";
} else {
	echo "<table class=\"list\">\n" .
		"<tr><th>login</th><th>teamname</th><th>category</th>" .
		"<th>affiliation</th><th>host</th><th>room</th><th colspan=\"2\">status</th></tr>\n";

	while( $row = $teams->next() ) {

		$status = $numsub = $numcor = 0;
		if ( isset($row['teampage_first_visited']) ) $status = 1;
		if ( isset($nsubmits[$row['login']]) &&
			 $nsubmits[$row['login']]['cnt']>0 ) {
			$status = 2;
			$numsub = (int)$nsubmits[$row['login']]['cnt'];
		}
		if ( isset($ncorrect[$row['login']]) &&
			 $ncorrect[$row['login']]['cnt']>0 ) {
			$status = 3;
			$numcor = (int)$ncorrect[$row['login']]['cnt'];
		}
		
		echo "<tr class=\"category" . (int)$row['categoryid'] . "\">".
			"<td class=\"teamid\"><a href=\"team.php?id=".urlencode($row['login'])."\">".
				htmlspecialchars($row['login'])."</a></td>".
			"<td><a href=\"team.php?id=".htmlspecialchars($row['login'])."\">".
				htmlentities($row['name'])."</a></td>".
			"<td title=\"catid ".(int)$row['categoryid']."\">".
				htmlentities($row['catname'])."</td>".
			"<td title=\"affilid ".htmlspecialchars($row['affilid'])."\">".
				htmlentities($row['affname'])."</td><td title=\"";
		
		if ( @$row['ipaddress'] ) {
			$host = htmlspecialchars(gethostbyaddr($row['ipaddress']));
			echo htmlspecialchars($row['ipaddress']);
			if ( $host == $row['ipaddress'] ) {
				echo "\">" . printhost($host, TRUE);
			} else {
				echo " - $host\">" . printhost($host);
			}
		} else {
			echo "\">-";
		}
		echo "</td><td>".htmlentities($row['room'])."</td>";
		echo "<td class=\"teamstatus\"><img ";
		switch ( $status ) {
		case 0: echo 'src="../images/gray.png"   alt="gray"' .
				' title="no connections made"';
			break;
		case 1: echo 'src="../images/red.png"    alt="red"' .
				' title="teampage viewed, no submissions"';
			break;
		case 2: echo 'src="../images/yellow.png" alt="yellow"' .
				' title="submitted, none correct"';
			break;
		case 3: echo 'src="../images/green.png"  alt="green"' .
				' title="correct submission(s)"';
			break;
		}
		echo " width=\"16\" height=\"16\" /></td>";
		echo "<td align=\"right\" title=\"$numcor correct / $numsub submitted\">$numcor / $numsub</td>";
		if ( IS_ADMIN ) {
			echo "<td>" .
				editLink('team', $row['login']) . " " .
				delLink('team','login',$row['login']) . "</td>";
		}
		echo "</tr>\n";
	}
	echo "</table>\n\n";
}

if ( IS_ADMIN ) {
	echo "<p>" .addLink('team') . "</p>\n";
}

require('../footer.php');
