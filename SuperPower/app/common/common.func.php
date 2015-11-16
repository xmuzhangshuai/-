<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');


function url($segment, $params = array(), $noredirect = false) {
	return murl($segment, $params, $noredirect);
}


function message($msg, $redirect = '', $type = '') {
	global $_W;
	if($redirect == 'refresh') {
		$redirect = $_W['script_name'] . '?' . $_SERVER['QUERY_STRING'];
	}
	if($redirect == 'referer') {
		$redirect = referer();
	}
	if($redirect == '') {
		$type = in_array($type, array('success', 'error', 'info', 'warning', 'ajax', 'sql')) ? $type : 'info';
	} else {
		$type = in_array($type, array('success', 'error', 'info', 'warning', 'ajax', 'sql')) ? $type : 'success';
	}
	if($_W['isajax'] || $type == 'ajax') {
		$vars = array();
		$vars['message'] = $msg;
		$vars['redirect'] = $redirect;
		$vars['type'] = $type;
		exit(json_encode($vars));
	}
	if (empty($msg) && !empty($redirect)) {
		header('location: '.$redirect);
	}
	$label = $type;
	if($type == 'error') {
		$label = 'danger';
	}
	if($type == 'ajax' || $type == 'sql') {
		$label = 'warning';
	}
	include template('common/message', TEMPLATE_INCLUDEPATH);
	exit();
}


function checkauth() {
	global $_W, $engine;
	load()->model('mc');
	
	/**
	二次开发：更新用户头像信息等
	*/
	if(!empty($_W['openid'])) {
		$sql = 'SELECT `fanid`,`openid`,`uid` FROM ' . tablename('mc_mapping_fans') . ' WHERE `uniacid`=:uniacid AND `openid`=:openid';
		$pars = array();
		$pars[':uniacid'] = $_W['uniacid'];
		$pars[':openid'] = $_W['openid'];
		if (defined('IN_API')) {
			$sql .= ' AND `acid`=:acid';
			$pars[':acid'] = $_W['acid'];
		}
		$fan = pdo_fetch($sql, $pars);
		if(!empty($fan) && !empty($fan['uid'])) {
			//获取用户头像，信息等
			//第一步获取access_token
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=wx532b192c33eb81c8&secret=065e72721eb8673a92e781a1619be4ad";
			$result = file_get_contents($url);
			$ret = json_decode($result,true);
			$access_token = $ret['access_token'];
			//第二步，获取用户信息
			$infourl = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$_W['openid']}&lang=zh_CN";
			$userinfo = json_decode(file_get_contents($infourl),true);
			$name = $userinfo['nickname'];
			//第三步，信息入库
			$user = mc_fetch($fan['uid'], array('nickname', 'gender', 'residecity', 'resideprovince', 'nationality', 'avatar'));
			$rec = array();
			if(empty($user['nickname']) && !empty($userinfo['nickname'])) {
				$rec['nickname'] = stripslashes($userinfo['nickname']);
			}
			if(empty($user['gender']) && !empty($userinfo['sex'])) {
				$rec['gender'] = $userinfo['sex'];
			}
			if(empty($user['residecity']) && !empty($userinfo['city'])) {
				$rec['residecity'] = $userinfo['city'] . '市';
			}
			if(empty($user['resideprovince']) && !empty($userinfo['province'])) {
				$rec['resideprovince'] = $userinfo['province'] . '省';
			}
			if(empty($user['nationality']) && !empty($userinfo['country'])) {
				$rec['nationality'] = $userinfo['country'];
			}
			if(empty($user['avatar']) && !empty($userinfo['headimgurl'])) {
				$rec['avatar'] = rtrim($userinfo['headimgurl'], '0') . 132;
			}
			if(!empty($rec)) {
				pdo_update('mc_members', $rec, array('uid' => $user['uid']));
			}
			
		}
	}
	
	
	/*更新结束*/
	
	if(!empty($_W['member']) && (!empty($_W['member']['mobile']) || !empty($_W['member']['email']))) {
		return true;
	}
	if(!empty($_W['openid'])) {
		$sql = 'SELECT `fanid`,`openid`,`uid` FROM ' . tablename('mc_mapping_fans') . ' WHERE `uniacid`=:uniacid AND `openid`=:openid';
		$pars = array();
		$pars[':uniacid'] = $_W['uniacid'];
		$pars[':openid'] = $_W['openid'];
		if (defined('IN_API')) {
			$sql .= ' AND `acid`=:acid';
			$pars[':acid'] = $_W['acid'];
		}
		$fan = pdo_fetch($sql, $pars);
		if(!empty($fan) && !empty($fan['uid'])) {
			if(_mc_login(array('uid' => $fan['uid']))) {
				return true;
			} else {
				$rec = array();
				$rec['uid'] = $fan['uid'] = 0;
				pdo_update('mc_mapping_fans', $rec, array('fanid' => $fan['fanid']));
			}
		}
		if (defined('IN_API')) {
			$GLOBALS['engine']->died("抱歉，您需要先登录才能使用此功能，点击此处 <a href='".__buildSiteUrl(url('auth/login')) ."'>【登录】</a>");
		}
	}
	
	$forward = base64_encode($_SERVER['QUERY_STRING']);
	if($_W['isajax']) {
		$result = array();
		$result['url'] = url('auth/login', array('forward' => $forward), true);
		$result['act'] = 'redirect';
		exit(json_encode($result));
	} else {
		header("location: " . url('auth/login', array('forward' => $forward)), true);
	}
	exit;
}

function __buildSiteUrl($url) {
	global $_W, $engine;

	$mapping = array(
			'[from]' => $engine->message['from'],
			'[to]' => $engine->message['to'],
			'[uniacid]' => $_W['uniacid'],
	);
	$url = str_replace(array_keys($mapping), array_values($mapping), $url);

	$pass = array();
	$pass['openid'] = $engine->message['from'];
	$pass['acid'] = $_W['acid'];

	$sql = 'SELECT `fanid`,`salt`,`uid` FROM ' . tablename('mc_mapping_fans') . ' WHERE `acid`=:acid AND `openid`=:openid';
	$pars = array();
	$pars[':acid'] = $_W['acid'];
	$pars[':openid'] = $pass['openid'];
	$fan = pdo_fetch($sql, $pars);
	if(empty($fan) || !is_array($fan) || empty($fan['salt'])) {
		$fan = array('salt' => '');
	}
	$pass['time'] = TIMESTAMP;
	$pass['hash'] = md5("{$pass['openid']}{$pass['time']}{$fan['salt']}{$_W['config']['setting']['authkey']}");
	$auth = base64_encode(json_encode($pass));

	$vars = array();
	$vars['uniacid'] = $_W['uniacid'];
	$vars['__auth'] = $auth;
	$vars['forward'] = base64_encode($url);

	return $_W['siteroot'] . 'app/' . url('auth/forward', $vars);
}


function init_quickmenus($multiid = 0) {
	global $_W, $controller, $action;
	$quickmenu = pdo_fetchcolumn('SELECT quickmenu FROM ' . tablename('site_multi') . ' WHERE uniacid = :uniacid AND id = :id', array(':uniacid' => $_W['uniacid'], ':id' => $multiid));
	$quickmenu = iunserializer($quickmenu);
	if(!empty($quickmenu)) {
		$acl = array();
		$acl['home'] = array(
			'home'
		);
		if((is_array($acl[$controller]) && in_array($action, $acl[$controller])) || in_array(IN_MODULE, $quickmenu['enablemodule'])) {
			$tpl = !empty($quickmenu['template']) ? $quickmenu['template'] : 'default';
			$_W['quickmenu']['menus'] = app_navs('shortcut', $multiid);
			$_W['quickmenu']['template'] = '../quick/' . $tpl;
		}
	}
}

function register_jssdk($debug = false){
	
	global $_W;
	
	if (defined('HEADER')) {
		echo '';
		return;
	}
	
	$sysinfo = array(
		'uniacid' => $_W['uniacid'],
		'siteroot' => $_W['siteroot'],
		'siteurl' => $_W['siteurl'],
		'attachurl' => $_W['attachurl'],
		'cookie' => array('pre'=>$_W['config']['cookie']['pre'])
	);
	if (!empty($_W['acid'])) {
		$sysinfo['acid'] = $_W['acid'];
	}
	if (!empty($_W['openid'])) {
		$sysinfo['openid'] = $_W['openid'];
	}
	if (defined('MODULE_URL')) {
		$sysinfo['MODULE_URL'] = MODULE_URL;
	}
	$sysinfo = json_encode($sysinfo);
	$jssdkconfig = json_encode($_W['account']['jssdkconfig']);
	$debug = $debug ? 'true' : 'false';
	
	$script = <<<EOF

<script src="http://res.wx.qq.com/open/js/jweixin-1.0.0.js"></script>
<script type="text/javascript">
	window.sysinfo = window.sysinfo || $sysinfo || {};
	
	// jssdk config 对象
	jssdkconfig = $jssdkconfig || {};
	
	// 是否启用调试
	jssdkconfig.debug = $debug;
	
	jssdkconfig.jsApiList = [
		'checkJsApi',
		'onMenuShareTimeline',
		'onMenuShareAppMessage',
		'onMenuShareQQ',
		'onMenuShareWeibo',
		'hideMenuItems',
		'showMenuItems',
		'hideAllNonBaseMenuItem',
		'showAllNonBaseMenuItem',
		'translateVoice',
		'startRecord',
		'stopRecord',
		'onRecordEnd',
		'playVoice',
		'pauseVoice',
		'stopVoice',
		'uploadVoice',
		'downloadVoice',
		'chooseImage',
		'previewImage',
		'uploadImage',
		'downloadImage',
		'getNetworkType',
		'openLocation',
		'getLocation',
		'hideOptionMenu',
		'showOptionMenu',
		'closeWindow',
		'scanQRCode',
		'chooseWXPay',
		'openProductSpecificView',
		'addCard',
		'chooseCard',
		'openCard'
	];
	
	wx.config(jssdkconfig);
	
</script>
EOF;
	echo $script;
}
