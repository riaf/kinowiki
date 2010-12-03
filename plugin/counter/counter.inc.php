<?php
/* 
 * $Id: counter.inc.php,v 1.1.1.1 2005/06/12 15:38:46 youka Exp $
 */

class Plugin_counter extends Plugin
{
	protected static $count;
	
	
	function do_inline($page, $param1, $param2)
	{
		switch(trim($param1)){
			case 'today':
				return self::$count['today'];
			case 'yesterday':
				return self::$count['yesterday'];
			default:
				return self::$count['total'];
		}
	}
	
	
	function doing()
	{
        $db = KinoWiki::getDatabase();
        $db->exec(file_get_contents(PLUGIN_DIR . 'counter/counter.sql'));

        $stmt = $db->prepare('SELECT total, today, yesterday, date FROM plugin_counter WHERE pagename=?');
        $stmt->execute(array($this->getcurrentPage()->getpagename()));

        $count = $stmt->fetch();

		$time = time();
		$date = date('Y-m-d', $time);
		if ($count == null || $date != $count['date']) {
			$yesterday = date('Y-m-d', $time - 24*60*60);
			$count['total'] = isset($count['total']) ? $count['total'] + 1 : 1;
			$count['yesterday'] = isset($count['date']) && $count['date'] == $yesterday ? $count['today'] : 0;
			$count['today'] = 1;

            $stmt = $db->prepare('INSERT OR REPLACE INTO plugin_counter (pagename, total, today, yesterday, date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(array($this->getcurrentPage()->getpagename(), $count['total'], $count['today'], $count['yesterday'], $date));
		} else {
			$count['total']++;
			$count['today']++;

            $stmt = $db->prepare('UPDATE plugin_counter SET total = total + 1, today = today + 1 WHERE pagename=?');
            $stmt->execute(array($this->getcurrentPage()->getpagename()));
		}
		
		self::$count = $count;
	}
}

