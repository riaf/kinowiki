<?php
/* 
 * $Id: search.inc.php,v 1.1.1.1 2005/06/12 15:38:36 youka Exp $
 */


/**
 * 検索機能のクラス。
 * 
 * シングルトンのように振舞う。
 */
class Search
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
	 * ページ検索する。
	 * 
	 * @param	array(string)	$word	検索語句。
	 * @param	bool	$andsearch	trueの場合はAND検索、falseの場合はOR検索。
	 * @return	array(string)	ページ名。アルファベット順にソート済み。
	 */
	function normalsearch($word, $andsearch = true)
	{
        $db = KinoWiki::getDatabase();

		$andor = $andsearch ? 'AND' : 'OR';
        $sql = 'SELECT pagename FROM page WHERE (pagename like ?'. implode(" $andor pagename like ?"). ')';
        $sql .= ' OR (source like ?'. implode(" $andor pagename like ?"). ') ORDER BY pagename ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute(array_fill(0, count($word) * 2, "%$word%"));

        $ret = array();
        while ($row = $stmt->fetch()) {
            $ret[] = $row['pagename'];
        }
        $stmt = null;
        return $ret;
	}
	
	
	/**
	 * あいまい検索する。
	 * 
	 * @param	array(string)	$word	検索語句。
	 * @param	bool	$andsearch	trueの場合はAND検索、falseの場合はOR検索。
	 * @return	array(string)	ページ名。アルファベット順にソート済み。
	 */
	function fuzzysearch($word, $andsearch = true)
	{
		$exp = array();
		foreach($word as $w){
			$exp[] = FuzzyFunc::makefuzzyexp($w);
		}
		return $this->eregsearch($exp, $andsearch);
	}
	
	
	/**
	 * 正規表現検索する。
	 * 
	 * @param	array(string)	$word	検索語句（正規表現）。
	 * @param	bool	$andsearch	trueの場合はAND検索、falseの場合はOR検索。
	 * @return	array(string)	ページ名。アルファベット順にソート済み。
	 */
	function eregsearch($word, $andsearch = true)
	{
        return array();

        // TODO: SQLite に依存しない形にする
        /**
		$db = DataBase::getinstance();
		
		for($i = 0; $i < count($word); $i++){
			$_word[] = $db->escape($word[$i]);
		}
		
		$andor = $andsearch ? 'AND' : 'OR';
		$query  = "SELECT pagename FROM page";
		$query .= " WHERE";
		$query .= "  (php('mb_ereg', '" . join("', pagename) $andor php('mb_ereg', '", $_word) . "', pagename))";
		$query .= "  OR";
		$query .= "  (php('mb_ereg', '" . join("', source) $andor php('mb_ereg', '", $_word) . "', source))";
		$query .= " ORDER BY pagename ASC";
		return $this->_search($query);
        */
	}
	
	
	/**
	 * 更新日時で検索する。
	 * 
	 * @param	int	$from	開始日時のタイムスタンプ
	 * @param	int	$to	終了日時のタイムスタンプ
	 * @return	array(string)	ページ名。新しい順にソート済み。
	 */
	function timesearch($from, $to)
	{
        $db = KinoWiki::getDatabase();
        $stmt = $db->prepare('SELECT pagename FROM page WHERE ? <= timestamp AND timestamp <= ? ORDER BY timestamp DESC');
        $stmt->execute(array($from, $to));

        $ret = array();
        while ($row = $stmt->fetch()) {
            $ret[] = $row['pagename'];
        }
        return $ret;
	}
	
	
	/**
	 * 検索クエリ実行。
	 * 
	 * @return	array(string)
	 */
	protected function _search($query)
	{
        $db = KinoWiki::getDatabase();
        $ret = array();
        foreach ($db->query($query) as $row) {
            $ret[] = $row['pagename'];
        }
        return $ret;
	}
	
	
	/**
	 * 検索語にタグをつける。
	 * 
	 * @param	string	$text	タグをつける対象（HTML形式）
	 * @param	array(string)	$word	検索語
	 * @param	string	$type	検索の種類
	 */
	function mark($text, $word, $type)
	{
		switch($type){
			case 'fuzzy':
				$call = '_markword_fuzzy';
				break;
			case 'ereg':
				$call = '_markword_ereg';
				break;
			default:
				$call = '_markword_normal';
				break;
		}
		
		$count = 1;
		foreach($word as $w){
			$s = $this->$call($w);
			$pattern = "((?:\G|>)[^<]*?)($s)";
			$replace = "\\1<span class=\"search word$count\">\\2</span>";
			$text = mb_ereg_replace($pattern, $replace, $text, 'm');
			$count++;
		}
		return $text;
	}
	
	
	protected function _markword_normal($w)
	{
		return mb_ereg_quote($w);
	}
	
	
	protected function _markword_fuzzy($w)
	{
		return FuzzyFunc::makefuzzyexp($w);
	}
	
	
	protected function _markword_ereg($w)
	{
		return $w;
	}
}

