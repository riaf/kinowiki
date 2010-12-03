<?php
/* 
 * $Id: backlink.inc.php,v 1.4 2005/07/14 08:32:22 youka Exp $
 */


/**
 * 逆リンクを管理するクラス。
 * 
 * シングルトン。
 */
class BackLink implements MyObserver
{
	/**
	 * インスタンスを取得する。
	 */
	static function getinstance()
	{
		static $ins;

		if(empty($ins)){
			$ins = new self;
		}
		return $ins;
	}
	
	
	/**
	 * コンストラクタ。
	 */
	protected function __construct()
	{
		//do nothing
	}
	
	
	/**
	 * 本体実行前にクラスを初期化する
	 */
	static function init()
	{
		Page::attach(self::getinstance());
	}
	
	
	/**
	 * 逆リンクのリストを取得する。
	 * 
	 * @param	Page	$page	リンクされている側のページ。
	 * @return	array('pagename' => string, 'times' => int)	リンクしている側のページのリスト（timesはリンクの重複数）。
	 */
	function getlist($page)
	{
        $db = KinoWiki::getDatabase();

        $sql = 'SELECT linker, times FROM linklist WHERE linked=? ORDER BY times DESC, linker ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute(array($page->getpagename()));

		$ret = array();
		while($row = $stmt->fetch()){
			$ret[] = array('pagename' => $row['linker'], 'times' => $row['times']);
		}
		return $ret;
	}
	
	
	/**
	 * ページ更新と同時に逆リンクを更新する。
	 */
	function update($page, $arg)
	{
		$this->refreshlinker($page);
		if($page->isexist() != $page->isexist(1)){	//新規または削除のとき
			$this->refreshlinked($page);
		}
	}
	
	
	/**
	 * リンクする側を軸にして逆リンクを更新する。
	 * 
	 * @param	Page	$linker	リンクする側のページ名。
	 */
	function refreshlinker($linker)
	{
		//隠しページからのリンク情報は出さない。
		if($linker->ishidden()){
			return;
		}

        $db = KinoWiki::getDatabase();

		$body = parse_Page($linker);
		$seeker = new LinkSeeker($linker);
		$body->accept($seeker);
		$list = $seeker->getlist();

        $stmt = $db->prepare('DELETE FROM linklist WHERE linker=?');
        $stmt->execute(array($linker->getpagename()));

        $stmt = $db->prepare('INSERT INTO linklist (linker, linked, times) VALUES (?, ?, ?)');
		foreach($list as $linkedname => $times){
			if($linker->getpagename() != $linkedname){
                $stmt->execute(array($linker->getpagename(), $linkedname, $times));
			}
		}
	}
	
	
	/**
	 * リンクされる側を軸にして逆リンクを更新する。
	 * 
	 * @param	Page	$linked	リンクされる側のページ名。
	 */
	function refreshlinked($linked)
	{
        $db = KinoWiki::getDatabase();

        $stmt = $db->prepare('DELETE FROM linklist WHERE linked=?');
        $stmt->execute(array($linked->getpagename()));

        $stmt = $db->prepare('SELECT pagename FROM page WHERE source like ?');
        $stmt->execute(array('%'. $linked->getpagename(). '%'));
        while ($row = $stmt->fetch()) {
            $this->refreshlinker(Page::getinstance($row['pagename']));
        }
	}
	
	
	/**
	 * 逆リンクを全て更新する。
	 */
	function refreshall()
	{
        $db = KinoWiki::getDatabase();

        $db->query('DELETE FROM linklist');
        foreach ($db->query('SELECT pagename FROM page') as $row) {
            $this->refreshlinker(Page::getinstance($row['pagename']));
        }
	}
}



/**
 * サイト内リンクを探すVisitor。
 */
class LinkSeeker
{
	protected $linklist = array();
	protected $currentpage;
	
	
	/**
	 * コンストラクタ。
	 *
	 * @param	Page	$page	解析するページ。
	 */
	function __construct($page)
	{
		$this->currentpage = $page;
	}
	
	
	/**
	 * サイト内リンクのリストを返す。
	 * 
	 * @return	array(string $linked => int $times)	$linkedはリンク先ページ名、$timesは重複数。
	 */
	function getlist()
	{
		return $this->linklist;
	}
	
	
	/**
	 * visit系関数のデフォルトは内包する要素を呼び出す。
	 */
	function __call($funcname, $params)
	{
		$elements = $params[0]->getelements();
		foreach($elements as $elem){
			$elem->accept($this);
		}
	}
	
	
	/**
	 * リストに加算する。
	 * 
	 * @param	string	$linked	リンクされる側のページ名。
	 */
	protected function add($linked)
	{
		if(isset($this->linklist[$linked])){
			$this->linklist[$linked]++;
		}
		else{
			$this->linklist[$linked] = 1;
		}
	}
	
	
	function visitT_AutoLink($e)
	{
		$this->add($e->getpagename());
	}
	
	
	function visitT_BlacketName($e)
	{
		$pagename = $e->getpagename();
		if(mb_ereg('^' . EXP_URL . '$', $pagename)){
			//URLにヒット。何もしない。
		}
		else if(mb_ereg('^' . EXP_MAIL . '$', $pagename)){
			//Mailにヒット。何もしない。
		}
		else if(mb_ereg('^(.+?):(.+)$', $pagename)){
			//InterWikiNameにヒット。何もしない。
		}
		else{
			//のこるはサイト内リンク。
			$fullname = resolvepath($pagename, $this->currentpage->getpagename());
			$this->add($fullname);
		}
	}
}

