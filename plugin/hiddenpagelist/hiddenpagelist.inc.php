<?php
/* 
 * $Id: hiddenpagelist.inc.php,v 1.1 2005/07/18 09:24:01 youka Exp $
 */

class Plugin_hiddenpagelist extends Plugin
{
	function do_block($page, $param1, $param2)
	{
        $db = KinoWiki::getDatabase();
		$query  = "SELECT pagename FROM allpage";
		$query .= " WHERE pagename LIKE ':%' OR pagename LIKE '%/:%";
		$query .= " ORDER BY pagename ASC";

        $list = array();
        foreach ($db->query($query) as $row) {
            $list[] = $row['pagename'];
        }

		if (empty($list)) {
			return '';
		}
		natsort($list);
		
		foreach($list as $pagename){
			$link[] = '<li>' . makelink($pagename) . '</li>';
		}
		return "<ul>\n" . join("\n", $link) . "\n</ul>\n";
	}
}

