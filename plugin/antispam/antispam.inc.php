<?php
/* 
 * $Id: antispam.inc.php,v 1.2 2005/12/25 15:57:17 youka Exp $
 */



class Plugin_antispam extends Plugin
{
	function doing()
	{
		//cookieがあればspam判定しない
		if(isset(Vars::$cookie['plugin_antispam']) && Vars::$cookie['plugin_antispam'] == 'true'){
			setcookie('plugin_antispam', 'true', time()+60*60*24*30);
			return;
		}
		
		//cookie発行時以外のとき、判定する
		if(KinoWiki::getinstance()->getController() != $this){	
			require_once('Net/DNSBL.php');
			
			$dnsbl = new Net_DNSBL();
			$dnsbl->setBlacklists(array('list.dsbl.org', 'xbl.spamhaus.org', 'sbl.spamhaus.org'));
			if($dnsbl->isListed($_SERVER['REMOTE_ADDR'])){
				$this->getSmarty()->display('spam.tpl.htm');
				exit;
			}
		}
	}
	
	
	function do_url()
	{
		setcookie('plugin_antispam', 'true', time()+60*60*24*30);
		$ret['title'] = 'antispamプラグイン';
		$ret['body'] = $this->getSmarty()->fetch('setcookie.tpl.htm');
		return $ret;
	}
}

?>