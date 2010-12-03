<?php
/* 
 * $Id: referrer.inc.php,v 1.2 2005/08/02 14:46:57 youka Exp $
 */



class Plugin_referrer extends Plugin
{
	function init()
	{
        $db = KinoWiki::getDatabase();
        $db->exec(file_get_contents(PLUGIN_DIR . 'referrer/referrer.sql'));
		
		Command::getCommand('show')->attach($this);
	}
	
	
	function update($show, $arg)
	{
		if($arg == 'doing'){
			$this->record();
		}
		else if($arg == 'done'){
			$page = $this->getcurrentPage();

			$smarty = $this->getSmarty();
			$smarty->assign('pagename', $page->getpagename());
			$smarty->assign('referrer', $this->getlist($page));
			$this->setbody($smarty->fetch('list.tpl.htm'));
		}
	}
	
	
	protected function record()
	{
		if(isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != ''){
			if(!mb_ereg('^' . mb_ereg_quote(SCRIPTDIR), $_SERVER['HTTP_REFERER']) && !mb_ereg('^(?:ftp|https?)://[^.]+?/', $_SERVER['HTTP_REFERER'])){
                $db = KinoWiki::getDatabase();
                $stmt = $db->prepare('UPDATE plugin_referrer SET count = count + 1 WHERE pagename=? AND url=?');
                if (!$stmt->execute(array($this->getcurrentPage()->getpagename(), $_SERVER['HTTP_REFERER']))) {
                    $stmt = $db->prepare('INSERT INTO plugin_referrer VALUES (?, ?, 1)');
                    $stmt->execute(array($this->getcurrentPage()->getpagename(), $_SERVER['HTTP_REFERER']));
				}
			}
		}
	}
	
	
	function do_url()
	{
		if(!isset(Vars::$get['page'])){
			throw new PluginException('パラメータが足りません。', $this);
		}
		$page = Page::getinstance(Vars::$get['page']);
		
		if(isset(Vars::$post['url']) && count(Vars::$post['url']) > 0 && isset(Vars::$post['password'])){
			if(md5(Vars::$post['password']) == ADMINPASS){
				return $this->delete($page, Vars::$post['url']);
			}
			else{
				return $this->show($page, Vars::$post['url']);
			}
		}
		return $this->show($page);
	}
	
	
	protected function delete($page, $url)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('DELETE FROM plugin_referrer WHERE pagename=? AND url IN ('. implode(',', array_fill(0, count($url), '?')). ')');
        $stmt->execute(array_merge(array($page->getpagename()), $url));
		
		$ret['title'] = $page->getpagename() . ' のReferrer';
		$smarty = $this->getSmarty();
		$smarty->assign('pagename', $page->getpagename());
		$smarty->assign('url', $url);
		$ret['body'] = $smarty->fetch('deleted.tpl.htm');
		return $ret;
	}
		
	
	protected function show($page, $checkedurl = array())
	{
		$ret['title'] = $page->getpagename() . ' のReferrer';
		$smarty = $this->getSmarty();
		$smarty->assign('pagename', $page->getpagename());
		$smarty->assign('referrer', $this->getlist($page));
		$smarty->assign('checkedurl', $checkedurl);
		$ret['body'] = $smarty->fetch('show.tpl.htm');
		return $ret;
	}
	
	
	/**
	 * 受信済みReferrerを取得する。
	 */
	protected function getlist($page)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT url, count FROM plugin_referrer WHERE pagename=? ORDER BY count DESC');
        $stmt->execute(array($page->getpagename()));
        return $stmt->fetchAll();
	}
}

