<?php
/* 
 * $Id: trackback.inc.php,v 1.2 2005/06/27 22:54:44 youka Exp $
 */



class Plugin_trackback extends Plugin
{
	function init()
	{
        $db = KinoWiki::getDatabase();
        $db->exec(file_get_contents(PLUGIN_DIR . 'trackback/trackback.sql'));
		
		Command::getCommand('show')->attach($this);
	}
	
	
	function update($show, $arg)
	{
		if($arg == 'done'){
			$page = $this->getcurrentPage();
			Renderer::getinstance()->setoption('plugin_trackback_pingurlrdf' , $this->getpingurlrdf($page));
			
			$list = $this->getlist($page);
			if(count($list) > 0){
				$smarty = $this->getSmarty();
				$smarty->assign('pagename', $page->getpagename());
				$smarty->assign('trackback', $list);
				$this->setbody($smarty->fetch('list.tpl.htm'));
			}
		}
	}
	
	
	function do_inline($page, $param1, $param2)
	{
		$num = $this->countreceived($page);
		$path = SCRIPTURL;
		$pagename = rawurlencode($page->getpagename());
		return "<a href=\"{$path}?plugin=trackback&amp;param=show&amp;page={$pagename}\">TrackBack({$num})</a>";
	}
	
	
	function do_url()
	{
		if(isset(Vars::$get['param']) && Vars::$get['param'] == 'ping'){
			return $this->receive();
		}
		else{
			return $this->show();
		}
	}
	
	
	protected function show()
	{
		if(!isset(Vars::$get['page'])){
			throw new PluginException('パラメータが足りません。', $this);
		}
		$page = Page::getinstance(Vars::$get['page']);
		
		$ret['title'] = $page->getpagename() . ' へのTrackBack';
		$smarty = $this->getSmarty();
		$smarty->assign('pagename', $page->getpagename());
		$smarty->assign('pingurl', $this->getpingurl($page));
		$smarty->assign('trackback', $this->getlist($page));
		$ret['body'] = $smarty->fetch('show.tpl.htm');
		return $ret;
	}
	
	
	protected function receive()
	{
		if(!isset(Vars::$get['page']) || !Page::getinstance(Vars::$get['page'])->isexist()){
			$smarty = $this->getSmarty();
			$smarty->assign('errormes', 'unreceivable page');
			$smarty->display('receive_fail.tpl.htm');
			exit;
		}
		
		if(isset(Vars::$post['url'])){
			$data =& Vars::$post;
		}
		else if(isset(Vars::$get['url'])){
			$data =& Vars::$get;
		}
		else{
			$smarty = $this->getSmarty();
			$smarty->assign('errormes', 'no url');
			$smarty->display('receive_fail.tpl.htm');
			exit;
		}

		if(!mb_ereg('^' . EXP_URL . '$', $data['url'])){
			$smarty = $this->getSmarty();
			$smarty->assign('errormes', 'invalid url');
			$smarty->display('receive_fail.tpl.htm');
			exit;
		}

		$page = Page::getinstance(Vars::$get['page']);
		$title = isset($data['title']) ? $data['title'] : '';
		$excerpt = isset($data['excerpt']) ? $data['excerpt'] : '';
		$blog_name = isset($data['blog_name']) ? $data['blog_name'] : '';
		$url = $data['url'];

		$title = mb_strlen($title) >= 64 ? mb_substr($title, 0, 60) . '...' : $title;
		$excerpt = mb_strlen($excerpt) >= 256 ? mb_substr($excerpt, 0, 252) . '...' : $excerpt;

	    $db = KinoWiki::getDatabase();	

        $stmt = $db->prepare('INSERT INTO plugin_trackback (num, pagename, title, excerpt, url, blog_name, timestamp) VALUES (null, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($page->getpagename(), $title, $excerpt, $url, $blog_name, time()));

		$smarty = $this->getSmarty();
		$smarty->display('receive_success.tpl.htm');
		exit;
	}
	
	
	/**
	 * 受信済みトラックバックを取得する。
	 */
	protected function getlist($page)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT num, title, excerpt, url, blog_name, timestamp FROM plugin_trackback WHERE pagename=? ORDER BY timestamp DESC');
        $stmt->execute(array($page->getpagename()));
        return $stmt->fetchAll();
	}

	
	/**
	 * 受信済みTrackBackの数を取得する。
	 */
	protected function countreceived($page)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT count(*) c FROM plugin_trackback WHERE pagename=?');
        $stmt->execute(array($page->getpagename()));
        $row = $stmt->fetch();
        return $row['c'];
	}
	
	
	/**
	 * TrackBack Ping URLを取得する。
	 */
	protected function getpingurl($page)
	{
		return SCRIPTURL . '?plugin=trackback&amp;param=ping&amp;page=' . rawurlencode($page->getpagename());
	}
	
	
	/**
	 * TrackBack Ping URL自動検知用RDFを取得する。
	 */
	function getpingurlrdf($page)
	{
		$smarty = $this->getSmarty();
		$smarty->assign('pagename', $page->getpagename());
		return $smarty->fetch('rdf.tpl');
	}
}

