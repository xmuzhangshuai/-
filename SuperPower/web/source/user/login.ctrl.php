<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
/**
 * @author lwq
 * 二次开发：
 * 1、总店管理员（超级管理员）和门店管理员登录后跳转的界面不一样，在forward那边
 * 2、记录当前会话的role 角色
 */
//记录当前的role角色，用于权限的一些控制
session_start();
//////
defined('IN_IA') or exit('Access Denied');
define('IN_GW', true);
if(checksubmit()) {
	_login($_GPC['referer']);
}
cache_load('setting');
template('user/login');

function _login($forward = '') {
	global $_GPC, $_W;
	load()->model('user');
	$member = array();
	$username = trim($_GPC['username']);
	if(empty($username)) {
		message('请输入要登录的用户名');
	}
	$member['username'] = $username;
	$member['password'] = $_GPC['password'];
	if(empty($member['password'])) {
		message('请输入密码');
	}
	$record = user_single($member);
	if(!empty($record)) {
		if($record['status'] == 1) {
			message('您的账号正在审核或是已经被系统禁止，请联系网站管理员解决！');
		}
		$founders = explode(',', $_W['config']['setting']['founder']);
		$_W['isfounder'] = in_array($record['uid'], $founders);
		if ($_W['siteclose'] && !$_W['isfounder']) {
			$settings = setting_load('copyright');
			message('站点已关闭，关闭原因：' . $settings['copyright']['reason']);
		}
		$cookie = array();
		$cookie['uid'] = $record['uid'];
		$cookie['lastvisit'] = $record['lastvisit'];
		$cookie['lastip'] = $record['lastip'];
		$cookie['hash'] = md5($record['password'] . $record['salt']);
		$session = base64_encode(json_encode($cookie));
		isetcookie('__session', $session, !empty($_GPC['rember']) ? 7 * 86400 : 0);
		$status = array();
		$status['uid'] = $record['uid'];
		$status['lastvisit'] = TIMESTAMP;
		$status['lastip'] = CLIENT_IP;
		user_update($status);
		if(empty($forward)) {
			$forward = $_GPC['forward'];
		}
		if(empty($forward)) {
			$forward = './index.php?c=account&a=display';
		}
		//二次开发，获取该用户是超级管理员还是门店管理员 记录用户id，用于门店获取等操作
		$_SESSION['uid'] = $record['uid'];
		$params[':uid'] = $record['uid'];
		$sql = "SELECT role,uniacid FROM ". tablename('uni_account_users') . " WHERE uid=:uid LIMIT 1";
		$uni_account_user = pdo_fetch( $sql , $params);
		if(!empty($uni_account_user)){
			$_SESSION['role'] = $uni_account_user['role'];
			if($uni_account_user['role'] == 'manager'){//总店管理员
				message("欢迎回来，{$record['username']}。", $forward);
			}
			else if($uni_account_user['role'] == 'operator'){//门店管理员
				$forward = './index.php?c=account&a=switch&uniacid=' . $uni_account_user['uniacid'];
				message("欢迎回来，{$record['username']}。", $forward);
			}
		}
		
	} else {
		message('登录失败，请检查您输入的用户名和密码！');
	}
}
