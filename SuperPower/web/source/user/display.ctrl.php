<?php 
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
/**
 * @author  lwq
 * 二次开发  1、增加显示管理员管理的门店名称 2、用户列表 及 添加用户的时候增加uniacid
 */
//二次开发 获取会话中的uniacid
session_start();
$uniacid = $_SESSION['uniacid'];
defined('IN_IA') or exit('Access Denied');
$_W['page']['title'] = '用户列表 - 用户管理 - 用户管理';
$pindex = max(1, intval($_GPC['page']));
$psize = 20;

$where = ' WHERE 1 ';
$params = array();
if (!empty($_GPC['status'])) {
	$where .= " AND status = :status";
	$params[':status'] = intval($_GPC['status']);
}
if (!empty($_GPC['username'])) {
	$where .= " AND username LIKE :username";
	$params[':username'] = "%{$_GPC['username']}%";
}
if (!empty($_GPC['group'])) {
	//由于str_store中也有groupid 所以这里要修改下
	$condition = " AND " . tablename('users') . ".groupid = :groupid";
	$where .= $condition;
	$params[':groupid'] = intval($_GPC['group']);
}
//二次开发 查询管理员管理的门店名称
//$sql = 'SELECT '.tablename('users').'.*,'.tablename('str_store').'.title FROM ' . tablename('users') .',' . tablename('str_store') .$where . " AND storeid=id" . " LIMIT " . ($pindex - 1) * $psize .',' .$psize;
$sql = 'SELECT * FROM '.tablename('users').' LEFT JOIN ' . tablename('str_store') .' ON ' . tablename('users') .'.storeid=' . 
tablename('str_store'). '.id' . $where . " ORDER BY joindate ASC LIMIT " . ($pindex - 1) * $psize .',' .$psize;
$users = pdo_fetchall($sql, $params);
$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('users') . $where, $params);
$pager = pagination($total, $pindex, $psize);

$founders = explode(',', $_W['config']['setting']['founder']);
foreach($users as &$user) {
	$user['founder'] = in_array($user['uid'], $founders);
}
unset($user);

$usergroups = pdo_fetchall("SELECT id, name FROM ".tablename('users_group'), array(), 'id');
$settings = setting_load('register');
$settings = $settings['register'];

template('user/display');
