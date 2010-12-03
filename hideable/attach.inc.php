<?php
/* 
 * $Id: attach.inc.php,v 1.2 2005/06/27 18:24:27 youka Exp $
 */


/**
 * 添付ファイルを管理するクラス。
 * 
 * Pageごとにシングルトンのようにふるまう。
 */
class Attach
{
	protected $page;
	protected static $notifier;
	
	/** ページを取得する。 */
	function getpage(){ return $this->page; }
	
	static function attach($obj){ self::initNotifier(); self::$notifier->attach($obj); }
	static function detach($obj){ self::initNotifier(); self::$notifier->detach($obj); }
	protected function notify($arg = null){ self::$notifier->notify($this, $arg); }
	protected static function initNotifier()
	{
		if(empty(self::$notifier)){
			self::$notifier = new NotifierImpl();
		}
	}
	
	
	/**
	 * インスタンスを取得する。
	 * 
	 * @param	Page	$page	添付されているページ。
	 */
	static function getinstance($page)
	{
		self::initNotifier();
		return new self($page);
	}
	
	
	/**
	 * コンストラクタ。
	 */
	protected function __construct($page)
	{
		$this->page = $page;
	}
	
	
	/**
	 * ページに添付されているファイルを列挙する。
	 * 
	 * @return	array(string)	ファイル名の配列。
	 */
	function getlist()
	{
        $db = KinoWiki::getDatabase();

        $stmt = $db->prepare('SELECT filename FROM attach WHERE pagename=? ORDER BY filename ASC');
        $stmt->execute(array($this->page->getpagename()));
        $ret = array();
        while ($row = $stmt->fetch()) {
            $ret[] = $row['filename'];
        }
        $stmt = null;
        return $ret;
	}
	
	
	/**
	 * 添付ファイルのファイル名を変更する。
	 * 
	 * @param	string	$old	元のファイル名
	 * @param	string	$new	新しいファイル名
	 * @return	bool	変更が成功すればtrue、失敗すればfalse。
	 */
	function rename($old, $new)
	{
        $db = KinoWiki::getDatabase();

        $stmt = $db->prepare('UPDATE OR IGNORE attach SET filename=? WHERE pagename=? AND filename=?');
        $status = (bool) $stmt->execute(array($new, $this->page->getpagename(), $old));
        if ($status) {
            $this->notify(array('rename', $old, $new));
        }

        return $status;
	}
	
	
	/**
	 * ファイルが存在するかどうかを確認する。
	 * 
	 * @param	string	$filename
	 * @return	bool
	 */
	function isexist($filename)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT count(*) c FROM attach WHERE parename=? AND filename=?');
        $stmt->execute($this->page->getpagename(), $filename);
        $row = $stmt->fetch();

        return $row['c'] == 1;
	}
	
	
	/**
	 * ファイルを別ページに移動させる。
	 * 
	 * 移動先ページに同名のファイルが存在するときはDBExpectionを投げる。
	 * 
	 * @param	Page	$newpage
	 */
	function move($newpage)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('UPDATE attach SET pagename=? WHERE pagename=?');
        $stmt->execute(array($newpage, $this->page->getpagename()));
		$this->notify(array('move', $from, $to));
	}
}



/**
 * 添付ファイルを表すクラス。
 * 
 * ファイルごとにシングルトンのようにふるまう。
 */
class AttachedFile
{
	protected $filename;
	protected $page;
	protected static $notifier;
	
	
	function getfilename(){ return $this->filename; }
	function getpage(){ return $this->page; }
	
	static function attach($obj){ self::initNotifier(); self::$notifier->attach($obj); }
	static function detach($obj){ self::initNotifier(); self::$notifier->detach($obj); }
	protected function notify($arg = null){ self::$notifier->notify($this, $arg); }
	protected static function initNotifier()
	{
		if(empty(self::$notifier)){
			self::$notifier = new NotifierImpl();
		}
	}
	
	
	/**
	 * インスタンスを取得する。
	 */
	static function getinstance($filename, $page)
	{
		self::initNotifier();
		return new AttachedFile($filename, $page);
	}
	
	
	/**
	 * コンストラクタ。
	 */
	protected function __construct($filename, $page)
	{
		if(empty(self::$notifier)){
			self::$notifier = new NotifierImpl();
		}
		
		$this->filename = $filename;
		$this->page = $page;
	}
	
	
	/**
	 * ファイルを保存する。
	 * 
	 * @param	const string	$bin	ファイルの内容。
	 * @return	bool	成功すればtrue、すでにファイルがあればfalse。
	 */
	function set($bin)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO attach (pagename, filename, binary, size, timestamp, count)'
            . ' VALUES(?, ?, ?, ?, ?, 0)'
        );
        if ($stmt->execute($this->page->getpagename(), $this->filename, $bin, strlen($bin), time())) {
			$this->notify(array('attach'));
            return true;
        }
        return false;
	}
	
	
	/**
	 * ファイルを削除する。
	 */
	function delete()
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('DELETE FROM attach WHERE pagename=? AND filename=?');
        $stmt->execute(array($this->page->getpagename(), $this->filename));
		$this->notify(array('delete', $this->count()));
	}
	
	
	/**
	 * ファイル内容を取得する。
	 * 
	 * @param	bool	$count	取得時にカウンタを回すときはtrue
	 * @return	string	ファイルがないときはnull。
	 */
	function getdata($count = false)
	{
        $db = KinoWiki::getDatabase();

        $stmt = $db->prepare('SELECT binary FROM attach WHERE pagename=? AND filename=?');
        $stmt->execute(array($this->page->getpagename(), $this->filename));
        $row = $stmt->fetch();

        if ($count) {
            $stmt = $db->prepare('UPDATE attach SET count = count + 1 WHERE pagename=? AND filename=?');
            $stmt->execute(array($this->page->getpagename(), $this->filename));
        }

		return isset($row['binary'])? $row['binary']: null;
	}
	
	
	/**
	 * ファイルサイズを取得する。
	 */
	function getsize()
	{
		$ret = $this->getcol('size');
		return $ret !== false ? $ret : 0;
	}
	
	
	/**
	 * タイムスタンプを取得する。
	 */
	function gettimestamp()
	{
		$ret = $this->getcol('timestamp');
		return $ret !== false ? $ret : null;
	}
	
	
	/**
	 * ダウンロード数を取得する。
	 */
	function getcount()
	{
		$ret = $this->getcol('count');
		return $ret !== false ? $ret : 0;
	}
	
	
	protected function getcol($col)
	{
        $db = KinoWiki::getDatabase();
        $stmt->prepare("SELECT $col FROM attach WHERE pagename=? AND filename=?");
        $stmt->execute(array($this->page->getpagename(), $this->filename));
        $row = $stmt->fetch();
        return isset($row[$col])? $row[$col]: false;
	}
}

