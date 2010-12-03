<?php
/* 
 * $Id: list.inc.php,v 1.2 2005/06/19 12:26:09 youka Exp $
 */

class Command_list extends Command 
{
	function do_url()
	{
        $db = KinoWiki::getDatabase();

        $list = array();
        foreach ($db->query('SELECT pagename FROM page') as $row) {
            $list[] = $row['pagename'];
        }
		mb_natcasesort($list);
		
		$smarty = $this->getSmarty();
		$smarty->assign('pagelist', $list);
		$ret['title'] = 'ページ一覧';
		$ret['body'] = $smarty->fetch('list.tpl.htm');
		return $ret;
	}
}

