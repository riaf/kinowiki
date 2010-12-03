<?php
/*
 * $Id: autolink.inc.php,v 1.2 2005/06/27 18:08:07 youka Exp $
 */


/**
 * オートリンクのための正規表現を管理するクラス。シングルトン。
 */
class AutoLink
{
	/** オートリンクの正規表現 */
	protected $expression = array();
	/** オートリンク対象外のページ */
	protected $ignorelist = null;
	/** オートリンク対象外を列挙するページの名前 */
	const ignorelistpage = ':config/AutoLink/ignore';
	
	
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
		$ins = self::getinstance();
		$ins->makeignorelist();
		Page::attach($ins);
	}
	
	
	/**
	 * ignoreリストを構築する。
	 */
	protected function makeignorelist()
	{
		$this->ignorelist = array();
		$page = Page::getinstance(self::ignorelistpage);
		$lines = explode("\n", $page->getsource());
		foreach($lines as $str){
			if(mb_ereg('^-\[\[(.+)\]\]', $str, $m)){
				$this->ignorelist[] = $m[1];
			}
		}
	}
	
	
	/**
	 * オートリンク用正規表現を取得する。
	 * 
	 * @param	string	$dir	起点となるディレクトリ名。
	 * @return	string	正規表現。
	 */
	function getexpression($dir = '')
	{
		if(!isset($this->expression[$dir])){
            $db = KinoWiki::getDatabase();

            $stmt = $db->prepare('SELECT exp FROM autolink WHERE dir=?');
            $stmt->execute(array($dir));
            $row = $stmt->fetch();
            if ($row === false) {
                $list = $this->listup($dir);
                $exp = makelinkexp($list);
                $stmt = $db->prepare('INSERT INTO autolink(dir, exp) VALUES(?, ?)');
                $stmt->execute(array($dir, $exp));
                $this->expression[$dir] = $exp;
            } else {
                $this->expression[$dir] = $row['exp'];
            }
            $stmt = null;
		}
		return $this->expression[$dir];
	}
	
	
	/**
	 * オートリンクの対象となるページを列挙する。
	 * 
	 * @param	string	$dir	起点となるディレクトリ名。
	 * @return	array(string)	相対パス。
	 */
	protected function listup($dir)
	{
        $db = KinoWiki::getDatabase();
		
		$query = "SELECT pagename FROM page";
		if($dir != ''){
			$query .= " WHERE pagename like ?";
		}

        $stmt = $db->prepare($query);
        $stmt->execute(array("$dir/%"));

		$list = array();
		if($dir == ''){
			while($row = $stmt->fetch()){
				if(!$this->isignored($row['pagename'])){
					$list[] = $row['pagename'];
				}
			}
		}
		else{
			$len = strlen("{$dir}/");
			while($row = $stmt->fetch()){
				if(!$this->isignored($row['pagename'])){
					$list[] = substr($row['pagename'], $len);
				}
			}
		}
		return $list;
	}
	
	
	/**
	 * 無視ページかどうかを確認する。
	 * 
	 * @param	string	$pagename	ページ名。
	 * @return	bool	無視ページの場合true。
	 */
	protected function isignored($pagename)
	{
		return in_array($pagename, $this->ignorelist);
	}
	
	
	/**
	 * ページ更新と同時にオートリンク用正規表現を更新する。
	 */
	function update($page, $arg)
	{
		if($page->getpagename() == self::ignorelistpage){
			$this->makeignorelist();
		}
		$this->refresh();
	}
	
	
	/**
	 * オートリンク用正規表現を作り直す。
	 */
	function refresh()
	{
        $db = KinoWiki::getDatabase();
		$db->query("DELETE FROM autolink");
		$this->expression = array();
	}
}

