<?php
/* 
 * $Id: page.inc.php,v 1.6 2005/12/01 06:47:15 youka Exp $
 */


/**
 * ページ１つを表すクラス。
 * 
 * ページごとにシングルトンのように振る舞う。
 * 存在しないページの場合は本文が空文字列のPageになる。
 */
class Page
{
	protected $pagename;	//ページ名(string)
	protected static $notifier;
	
	//Notifier関連
	static function attach($obj){ self::initNotifier(); self::$notifier->attach($obj); }
	static function detach($obj){ self::initNotifier(); self::$notifier->detach($obj); }
	protected function notify(){ self::$notifier->notify($this); }
	protected static function initNotifier()
	{
		if(empty(self::$notifier)){
			self::$notifier = new NotifierImpl();
		}
	}
	
	
	/** ページ名を取得する。@return string */
	function getpagename(){ return $this->pagename; }
	/** 存在するページならtrueを返す。@return bool */
	function isnull(){ return $this->pagename == ''; }
	
	
	/**
	 * インスタンスを取得する。
	 *
	 * @param string	$pagename	ページ名。
	 * @return 	Page
	 */
	static function getinstance($pagename)
	{
		self::initNotifier();
		return new self($pagename);
	}
	
	
	/**
	 * 番号からインスタンスを取得する。
	 *
	 * @param 	int	$num	ページ番号。
	 * @return 	Page	ない場合は例外FatalExceptionを投げる。
	 */
	static function getinstancebynum($num)
	{
        $db = KinoWiki::getDatabase();
		
		$_num = (int)$num;
        $stmt = $db->prepare('SELECT pagename FROM purepage WHERE num=?');
        $stmt->execute(array($num));
        $row = $stmt->fetch();
        if ($row === false) {
			throw new FatalException("番号 $num のページはありません。");
        }
        return self::getinstance($row['pagename']);
	}
	
	
	/**
	 * コンストラクタ。
	 * 
	 * @param	string	$pagename	ページ名。
	 */
	protected function __construct($pagename)
	{
		$this->pagename = resolvepath($pagename);
	}
	
	
	/**
	 * 番号を取得する。
	 * 
	 * @return	int	ページ番号。ない場合はnullを返す。
	 */
	function getnum()
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT num FROM purepare WHERE pagename=?');
        $stmt->execute(array($this->pagename));
        $row = $stmt->fetch();
        return $row === false? null: $row['num'];
	}
	
	
	/**
	 * 本文を取得する。
	 * 
	 * @param	int	$num	0の時は現在のソースを、１以上の場合はバックアップを取得する。
	 * @return	string	ソース。無い場合は空文字列。
	 */
	function getsource($num = 0)
	{
		$ret = $this->getresult('source', $num);
		return $ret !== null ? $ret : '';
	}
	
	
	/**
	 * タイムスタンプを取得する。
	 * 
	 * @param	int	$num	0の時は現在のものを、1以上の時はバックアップのものを取得する。
	 * @return	int	タイムスタンプ。無い場合は0を返す。
	 */
	function gettimestamp($num = 0)
	{
		$ret = $this->getresult('timestamp', $num);
		return $ret !== null ? $ret : 0;
	}
	
	
	/**
	 * 実更新時刻を取得する。
	 * 
	 * @param	int	$num	0の時は現在のものを、1以上の時はバックアップのものを取得する。
	 * @return	int	タイムスタンプ。無い場合は0を返す。
	 */
	function getrealtimestamp($num = 0)
	{
		$ret = $this->getresult('realtimestamp', $num);
		return $ret !== null ? $ret : 0;
	}
	
	
	/**
	 * ページの存在を確認する
	 * 
	 * @param	int	$num	0の時は現在のものを、1以上の時はバックアップのものを取得する。
	 * @return	bool	存在する場合はtrue、しない場合はfalse。
	 */
	function isexist($num = 0)
	{
		return $this->getsource($num) != '' ? true : false;
	}
	
	
	/**
	 * 本文を保存する。
	 * 
	 * @param	string	$source	空文字列を渡した場合は削除になる。
	 * @param	bool	$notimestamp	trueの時、タイムスタンプを更新しない。
	 */
	function write($source, $notimestamp = false)
	{
        $db = KinoWiki::getDatabase();

        $time = time();
        $source = preg_replace("/\r?\n/", "\n", $source);

        $stmt = $db->prepare('SELECT timestamp FROM purepage WHERE pagename=?');
        $stmt->execute(array($this->pagename));
        if ($stmt->fetch() === false) {
            $stmt = $db->prepare('INSERT INTO purepage (pagename, num, source, timestamp, realtimestamp) VALUES (?, NULL, ?, ?, ?)');
            $stmt->execute(array($this->pagename, $source, $time, $time));
        } else {
            $stmt = $db->prepare('INSERT INTO pagebackup SELECT NULL, pagename, sourcem timestamp, realtimestamp FROM purepage WHERE pagename=?');
            $stmt->execute(array($this->pagename));

            $params = array($source);
            $sql = 'UPDATE purepage SET source=?';
            if (!$notimestamp) {
                $sql .= ' timestamp=?';
                $params[] = $time;
            }
            $sql .= ' realtimestamp=? WHERE pagename=?';
            $params[] = $time;
            $params[] = $this->pagename;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
		
		$this->notify();
	}
	
	
	/**
	 * バックアップを削除する。
	 */
	function deletebackup()
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('DELETE FROM pagebackup WHERE pagename=?');
        $stmt->execute(array($this->pagename));
	}
	
	
	/**
	 * バックアップの数を取得する。
	 * 
	 * @return	int
	 */
	function getbackupamount()
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT count(*) c FROM pagebackup WHERE pagename=?');
        $stmt->execute(array($this->pagename));
        $row = $stmt->fetch();

        return isset($row['c'])? $row['c']: 0;
	}
	
	
	/**
	 * バックアップをすべて取得する。
	 * 
	 * @return	array(mixed)	realtimestampの新しい順にソート済み。
	 */
	function getbackup()
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT * FROM pagebackup WHERE pagename=? ORDER BY realtimestamp DESC');
        $stmt->execute(array($this->pagename));
        return $stmt->fetchAll();
	}
	
	
	/**
	 * 該当のレコードから値を取得する。
	 * 
	 * @param	string	$result	結果のカラム。
	 * @param	int	$num	0の時は現在のものを、1以上の時はバックアップのものを取得する。
	 * @return	mixed	結果のカラムの値。無い場合はnullを返す。
	 */
	protected function getresult($result, $num = 0)
	{
        $db = KinoWiki::getDatabase();
        if ($num > 0) {
            $num = $num - 1;
            $stmt = $db->prepare("SELECT $result FROM pagebackup WHERE pagename=? ORDER BY number DESC LIMIT 1 OFFSET ?");
            $stmt->execute(array($this->pagename, $num));
        } else {
            $stmt = $db->prepare("SELECT $result FROM allpage WHERE pagename=?");
            $stmt->execute(array($this->pagename));
        }
        $row = $stmt->fetch();
        return isset($row[$result])? $row[$result]: null;
	}
	
	
	/**
	 * ページが同じか比較する。
	 *
	 * @param Page	比較対象のPage
	 * @return	bool	同じページの場合Trueを返す。
	 */
	function equals($page)
	{
		return $this->getpagename() == $page->getpagename();
	}
	
	
	/**
	 * 隠しページかどうかを調べる。
	 * 
	 * @return	bool	隠しページの場合はtrue
	 */
	function ishidden()
	{
		return mb_substr($this->pagename, 0, 1) == ':' || mb_strpos($this->pagename, '/:') !== false;
	}
}



/**
 * Page関連の例外クラス。
 */
class PageException extends MyException 
{
	/**
	 * コンストラクタ。
	 *
	 * @param string	$mes	エラーメッセージ
	 * @param string	$pagename	ページ名
	 */
	public function __construct($mes = '', $pagename)
	{
		parent::__construct($mes . "($pagename)");
	}
}

