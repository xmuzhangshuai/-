<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
/**
 * @author lwq
 * 二次开发 1、为管理员设置门店 2、新增加管理员时，默认给了微商城权限
 *  3、新增用户选项卡增加了操作员列表选项，需要获取uniacid;
 */
session_start();
//二次开发 为新增操作员列表获取uniacid
$uniacid = $_SESSION['uniacid'];
//
defined('IN_IA') or exit('Access Denied');
$_W['page']['title'] = '添加用户 - 用户管理 - 用户管理';
if(checksubmit()) {
	load()->model('user');
	$user = array();
	$user['username'] = trim($_GPC['username']);
	if(!preg_match(REGULAR_USERNAME, $user['username'])) {
		message('必须输入用户名，格式为 3-15 位字符，可以包括汉字、字母（不区分大小写）、数字、下划线和句点。');
	}
	if(user_check(array('username' => $user['username']))) {
		message('非常抱歉，此用户名已经被注册，你需要更换注册名称！');
	}
	$user['password'] = $_GPC['password'];
	if(istrlen($user['password']) < 8) {
		message('必须输入密码，且密码长度不得低于8位。');
	}
	$user['remark'] = $_GPC['remark'];
	$user['groupid'] = intval($_GPC['groupid']) ? intval($_GPC['groupid']) : message('请选择所属用户组');
	//二次开发 设置 门店id
	$user['storeid'] = intval($_GPC['storeid']) ? intval($_GPC['storeid']) : message('请设置管理员管理的门店');
	$uid = user_register($user);
	if($uid > 0) {
		//二次开发，为门店管理员设置微商城权限
		$url = "c=home&a=welcome&do=ext&m=str_takeout";
		$uniacid = $_SESSION['uniacid'];
		pdo_insert('users_permission', array(
				'uid' => $uid,
				'uniacid' => $uniacid,
				'url' => $url,
		));
		//二次开发，将门店管理员自动分配给改账号的管理员，并默认为operator
		pdo_insert('uni_account_users',array(
				'uid' => $uid,
				'uniacid' => $uniacid,
				'role' => 'operator')
		);
		unset($user['password']);
		message('用户增加成功！', url('user/edit', array('uid' => $uid)));
	}
	message('增加用户失败，请稍候重试或联系网站管理员解决！');
}
$groups = pdo_fetchall("SELECT id, name FROM ".tablename('users_group')." ORDER BY id ASC");
$stores = pdo_fetchall("SELECT id, title FROM ".tablename('str_store')." ORDER BY id ASC");
template('user/create');
