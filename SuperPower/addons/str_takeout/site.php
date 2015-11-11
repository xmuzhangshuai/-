<?php
/**
 * 微外卖模块微站定义
 * @author strday
 * @url http://bbs.we7.cc/
 */
/**
 * @author lwq
 * 二次开发：1、门店相关 等
 */
//二次开发
session_start();
defined('IN_IA') or exit('Access Denied');
include('model.php');
include 'wprint.class.php';
include 'PHPExcel/PHPExcel.php';
class Str_takeoutModuleSite extends WeModuleSite {
	public function doWebConfig() {
		global $_W, $_GPC;
		$config = get_config();
		if(checksubmit('submit')) {
			$data = array(
				'uniacid' => $_W['uniacid'],
				'version' => intval($_GPC['version'])
			);
			if(!empty($config)) {
				pdo_update('str_config', $data, array('uniacid' => $_W['uniacid']));
			} else {
				pdo_insert('str_config', $data);
			}
			message('设置参数成功', referer(), 'success');
		}
		include $this->template('config');
	}
	//门店相关，超级管理员
	public function doWebStore() {
		global $_W, $_GPC;
		$op = empty($_GPC['op']) ? 'list' : trim($_GPC['op']);
		$config = get_config();
		if($config['num_limit'] > 0) {
			$now_store_num = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_store') . ' WHERE uniacid = :uniacid' , array(':uniacid' => $_W['uniacid']));
		}
		//显示门店列表
		if($op == 'list') {
			$lists = "";
			$condition = ' uniacid = :aid';
			$params[':aid'] = $_W['uniacid'];
			if(!empty($_GPC['keyword'])) {
				$condition .= " AND title LIKE '%{$_GPC['keyword']}%'";
			}
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_store') . ' WHERE ' . $condition, $params);
			//二次开发：超级管理员和门店管理员显示不同的门店
			if($_SESSION['role'] == 'operator'){
				$lists = pdo_fetchall('SELECT ' . tablename('str_store') . '.* FROM ' . tablename('str_store') . ',' . tablename('users') .
						 ' WHERE uid=' . $_SESSION['uid'] . ' AND id=storeid' . 
						' ORDER BY displayorder DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			}else{
				$lists = pdo_fetchall('SELECT * FROM ' . tablename('str_store') . ' WHERE ' . $condition . ' ORDER BY displayorder DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			}		
			$pager = pagination($total, $pindex, $psize);
			if(!empty($lists)) {
				foreach($lists as &$li) {
					$li['address'] = str_replace('+', ' ', $li['district']) . ' ' . $li['address'];
				}
			}
			//方便模板判断当前是门店管理员还是总店管理员
			$nowrole = $_SESSION['role'];
		}
		//新增/编辑门店
		if($op == 'post') {
			load()->func('tpl');
			$id = intval($_GPC['id']);
			$accounts = uni_accounts();
			foreach($accounts as $k => $li) {
				if($li['level'] < 3) {
					unset($li[$k]);
				}
			}
			if($id) {//编辑门店
				$item = pdo_fetch('SELECT * FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $id));
				
				/**
				 * 二次开发，获取其他门店的区域
				 */
				$otherArea = pdo_fetchall('SELECT points FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id <> :id ORDER BY id ASC', array(':aid' => $_W['uniacid'], ':id' => $id));
				if(empty($item)) {
					message('门店信息不存在或已删除', 'referer', 'error');
				} else {
					$item['thumbs'] = iunserializer($item['thumbs']);
					$district_tmp = explode('+', $item['district']);
					if(is_array($district_tmp)) {
						$item['reside'] = array('province' => $district_tmp[0], 'city' => $district_tmp[1], 'district' => $district_tmp[2]);
					}
					$item['map'] = array('lat' => $item['location_x'], 'lng' => $item['location_y']);
					$item['business_hours'] = iunserializer($item['business_hours']);
				}
			} else {//添加门店
				if($config['num_limit'] > 0 && ($config['num_limit'] - $now_store_num <= 0)) {
					message("您的公众号只能添加{$config['num_limit']}个门店，不能再添加门店，请联系管理员", referer(), 'error');
				}
				$item['notice_type'] = 1;
				$item['comment_set'] = 1;
				$item['comment_status'] = 1;
				$item['is_meal'] = 1;
				$item['is_takeout'] = 1;
				$item['dish_style'] = 1;
				$item['business_hours'] = array(array('s' => '8:00', 'e' => '24:00'));
				
				$otherArea = pdo_fetchall('SELECT points FROM ' . tablename('str_store') . ' WHERE uniacid = :aid ORDER BY id ASC', array(':aid' => $_W['uniacid']));
			}
			if(checksubmit('submit')) {
				$data = array(
					'title' => trim($_GPC['title']),
					'logo' => trim($_GPC['logo']),
					'telephone' => trim($_GPC['telephone']),
					'description' => htmlspecialchars_decode($_GPC['description']),
					'send_price' =>intval($_GPC['send_price']),
					'delivery_price' =>intval($_GPC['delivery_price']),
					'delivery_time' =>intval($_GPC['delivery_time']),
					'serve_radius' =>intval($_GPC['serve_radius']),
					'delivery_area' => trim($_GPC['delivery_area']),
					'district' => $_GPC['reside']['province'] . '+' . $_GPC['reside']['city'] . '+' . $_GPC['reside']['district'],
					'address' =>  trim($_GPC['address']),
					'location_x' => $_GPC['map']['lat'],
					'location_y' => $_GPC['map']['lng'],
					'displayorder' => intval($_GPC['displayorder']),
					'status' => intval($_GPC['status']),
					'notice_acid' => intval($_GPC['notice_acid']),
					'notice_type' => intval($_GPC['notice_type']),
					'store_tpl' => trim($_GPC['store_tpl']),
					'member_tpl' => trim($_GPC['member_tpl']),
					'delivery_tpl' => trim($_GPC['delivery_tpl']),
					'dish_style' => intval($_GPC['dish_style']),
					'is_meal' => intval($_GPC['is_meal']),
					'is_takeout' => intval($_GPC['is_takeout']),
					'comment_set' => intval($_GPC['comment_set']),
					'comment_status' => intval($_GPC['comment_status']),
					'print_type' => intval($_GPC['print_type']),
					'notice' => trim($_GPC['notice']),
					'content' => trim($_GPC['content']),
					'points' => addslashes(trim($_GPC['points']))
				);

				if(!empty($_GPC['business_start_hours'])) {
					$hour = array();
					foreach($_GPC['business_start_hours'] as $k => $v) {
						$v = str_replace('：', ':', trim($v));
						if(!strexists($v, ':')) {
							$v .= ':00';
						}
						$end = str_replace('：', ':', trim($_GPC['business_end_hours'][$k]));
						if(!strexists($end, ':')) {
							$end.= ':00';
						}
						$hour[] = array('s' => $v, 'e' => $end);
					}
				}

				$data['business_hours'] = iserializer($hour);
				$data['thumbs'] = iserializer($_GPC['thumbs']);
				if($id) {
					pdo_update('str_store', $data, array('uniacid' => $_W['uniacid'], 'id' => $id));
				} else {
					$data['uniacid'] = $_W['uniacid'];
					pdo_insert('str_store', $data);
				}
				message('编辑门店信息成功', $this->createWebUrl('store', array('op' => 'list')), 'success');
			}
		}
		//删除门店
		if($op == 'del') {
			$id = intval($_GPC['id']);
			pdo_delete('str_dish_category', array('uniacid' => $_W['uniacid'], 'sid' => $id));
			pdo_delete('str_dish', array('uniacid' => $_W['uniacid'], 'sid' => $id));
			pdo_delete('str_order', array('uniacid' => $_W['uniacid'], 'sid' => $id));
			pdo_delete('str_order_print', array('uniacid' => $_W['uniacid'], 'sid' => $id));
			pdo_delete('str_print', array('uniacid' => $_W['uniacid'], 'sid' => $id));
			pdo_delete('str_clerk', array('uniacid' => $_W['uniacid'], 'sid' => $id));
			pdo_delete('str_store', array('uniacid' => $_W['uniacid'], 'id' => $id));
			message('删除门店成功', $this->createWebUrl('store', array('op' => 'list')), 'success');
		}
		/**
		 * @author lwq
		 * 二次开发：超级管理员拥有查看所有门店订单的权限和查看所有用户历史订单
		 */
		 if($op == 'allorder'){
		 	load()->func('tpl');
		 	//只需要微信号的ID，不需要店铺ID
			//$condition = ' WHERE uniacid = :aid AND sid = :sid';
			$condition = ' WHERE ' . tablename('str_store') . '.uniacid = :aid';
			$params[':aid'] = $_W['uniacid'];
			//$params[':sid'] = $sid;

			$status = intval($_GPC['status']);
			if($status) {
				$condition .= ' AND status = :stu';
				$params[':stu'] = $status;
			}
			$keyword = trim($_GPC['keyword']);
			
			$condition .= (" AND " . tablename('str_order'). '.sid=' . tablename('str_store'). '.id');
			if(!empty($keyword)) {
				//二次开发 需要订单共和店铺相联系
				
				$condition .= " AND (sid LIKE '%{$keyword}%' OR title LIKE '%{$keyword}%')";
			}
			if(!empty($_GPC['addtime'])) {
				$starttime = strtotime($_GPC['addtime']['start']);
				$endtime = strtotime($_GPC['addtime']['end']) + 86399;
			} else {
				$starttime = strtotime('-15 day');
				$endtime = TIMESTAMP;
			}
			$condition .= " AND addtime > :start AND addtime < :end";
			$params[':start'] = $starttime;
			$params[':end'] = $endtime;

			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;
			
			
			$total = pdo_fetchcolumn('SELECT COUNT(sid) from '. tablename('str_order') . ',' . tablename('str_store') . $condition, $params);
			//$data = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . $condition . ' ORDER BY addtime DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			//二次开发:data的获取要重新搞过
			$condition .= " GROUP BY sid";
			$data = pdo_fetchall('SELECT title, sid, COUNT(sid) as ordernum, SUM(price) as totalmoney from '. tablename('str_order') . ',' . tablename('str_store') . $condition, $params);
			if(!empty($data)) {
				foreach($data as &$da) {
					$da['is_trash'] = check_trash($da['sid'], $da['uid'], 'fetch');
				}
			}
			$pager = pagination($total, $pindex, $psize);
			//include $this->template('order');
		 }
		 if($op == 'allhisorder'){
		 	/**
			 * @author lwq
			 * 二次开发：用户历史订单，根据消费额度，消费次数排名 ， 超级管理员含有此权限
			 */
			load()->func('tpl');
			$condition = ' WHERE uniacid = :aid GROUP BY uid';
			$type = intval($_GPC['type']);
			if($type == 0){
				$condition.= ' ORDER BY SUM(price) DESC';
			}else if($type == 1){
				$condition.= ' ORDER BY COUNT(uid) DESC';
			}else{
				$condition.= ' ORDER BY SUM(price) DESC';//默认
			}
			$params[':aid'] = $_W['uniacid'];
			
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;
			
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') .  $condition, $params);
			$data = pdo_fetchall('SELECT uid,COUNT(uid) as buynum,SUM(price) as total, MAX(addtime) as latestbuytime, username, mobile FROM ' . 
					tablename('str_order') . $condition . '  LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			if(!empty($data)) {
				//foreach($data as &$da) {
				//	$da['is_trash'] = check_trash($da['sid'], $da['uid'], 'fetch');
				//}
			}
			$pager = pagination($total, $pindex, $psize);
		 }
		 /**
		  * @author Lwq
		  * 二次开发：总管理员维护菜品,该处等价于门店管理员的dish_list
		  */
		 if($op == 'dish_manage'){
		 	$condition = ' uniacid = :aid';
			$params[':aid'] = $_W['uniacid'];
			//$params[':sid'] = $sid;
			if(!empty($_GPC['keyword'])) {
				$condition .= " AND title LIKE '%{$_GPC['keyword']}%'";
			}
			if(!empty($_GPC['cid'])) {
				$condition .= " AND cid = :cid";
				$params[':cid'] = intval($_GPC['cid']);
			}

			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;

			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_dish') . ' WHERE ' . $condition, $params);
			$lists = pdo_fetchall('SELECT * FROM ' . tablename('str_dish') . ' WHERE ' . $condition . ' ORDER BY displayorder DESC,id ASC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			$pager = pagination($total, $pindex, $psize);
			$category = pdo_fetchall('SELECT title, id FROM ' . tablename('str_dish_category') . ' WHERE uniacid = :aid', array(':aid' => $_W['uniacid']), 'id');
		 	
		 }
		 /**
		  * @author lwq
		  * 二次开发：总店管理员添加菜品，该处等价于门店管理员的dish_post
		  */
		  if($op == 'dish_post'){
		  	load()->func('tpl');
			$category = pdo_fetchall('SELECT title, id FROM ' . tablename('str_dish_category') . ' WHERE uniacid = :aid ORDER BY displayorder DESC, id ASC', array(':aid' => $_W['uniacid']));
			$lists = pdo_fetchall('SELECT id,title,cid FROM' . tablename('str_dish') . ' WHERE uniacid = :aid and cid = :cid  ORDER BY displayorder DESC, id ASC', array(':aid' => $_W['uniacid'], ':cid' => 3));
			$id = intval($_GPC['id']);
			if($id) {
				$item = pdo_fetch('SELECT * FROM ' . tablename('str_dish') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $id));
				if($item['thumb'] != ''){
					$item['thumbs'] = explode(';',$item['thumb']);
				}
				if(empty($item)) {
					message('菜品不存在或已删除', $this->createWebUrl('store', array('dish_manage')), 'success');
				}
			} else {
				$item['total'] = -1;
				$item['unitname'] = '份';
			}
			if(checksubmit('submit')) {
				$type = intval($_GPC['cid']) == 3?'DANPIN':'TAOCAN';
				$data = array(
					'sid' => 0,
					'uniacid' => $_W['uniacid'],
					'title' => trim($_GPC['title']),
					'price' => floatval($_GPC['price']),
					'unitname' => trim($_GPC['unitname']),
					'total' => intval($_GPC['total']),
					'sailed' => intval($_GPC['sailed']),
					'grant_credit' => intval($_GPC['grant_credit']),
					'is_display' => intval($_GPC['is_display']),
					'cid' => intval($_GPC['cid']),
					'thumb' => trim($_GPC['imgsPath']),
					'recommend' => intval($_GPC['recommend']),
					'displayorder' => intval($_GPC['displayorder']),
					'description' => trim($_GPC['description']),
					'dish_type' => $type,
					'zuhe' => trim($_GPC['danpinzuhe']),
					'comprise' => trim($_GPC['comprise'])
				);
				if($data['dish_type'] == 'DANPIN')
					$data['zuhe'] = $data['title'];
				if($id) {
					pdo_update('str_dish', $data, array('uniacid' => $_W['uniacid'], 'id' => $id));
				} else {
					pdo_insert('str_dish', $data);
				}
				message('编辑菜品成功', $this->createWebUrl('store', array('op' => 'dish_manage')), 'success');
			}
		  }
		  if($op == 'dish_del'){
		  	$id = intval($_GPC['id']);
		  	/**
		  	 * 二次开发 总店管理员删除菜品：删除套餐其余全部删除
		  	 */
		  	 $dish = pdo_fetch('SELECT title,dish_type FROM ' . tablename('str_dish') . ' WHERE id='.$id);
			pdo_delete('str_dish', array('uniacid' => $_W['uniacid'], 'id' => $id));
			if($dish['dish_type'] == 'DANPIN'){
				pdo_run('DELETE FROM '.tablename('str_dish'). " WHERE zuhe LIKE '%" . $dish['title'] . "%' AND dish_type = 'TAOCAN'");
				pdo_run('DELETE FROM '.tablename('str_store_dish'). " WHERE dish_id IN"."(SELECT id FROM ".tablename('str_dish')." WHERE zuhe LIKE '%" . $dish['title'] . "%' AND dish_type='TAOCAN') AND dtype = 'TAOCAN'");
				pdo_run('DELETE FROM '.tablename('str_store_taocan'). " WHERE dish_id IN"."(SELECT id FROM ".tablename('str_dish')." WHERE zuhe LIKE '%" . $dish['title'] . "%' AND dish_type='TAOCAN')");
			}
			message('删除菜品成功', $this->createWebUrl('store', array('op' => 'dish_manage')), 'success');
		  }

		include $this->template('store');
	}
	//专门处理ajax请求 
	public function doWebAjax() {
		global $_W, $_GPC;
		$op = trim($_GPC['op']);
		if($op == 'status_store') {
			$id = intval($_GPC['id']);
			$value = intval($_GPC['value']);
			$state = pdo_update('str_store', array('status' => $value), array('uniacid' => $_W['uniacid'], 'id' => $id));
			if($state !== false) {
				exit('success');
			}
			exit('error');
		}
		if($op == 'status_dish') {
			$id = intval($_GPC['id']);
			$value = intval($_GPC['value']);
			$state = pdo_update('str_dish', array('is_display' => $value), array('uniacid' => $_W['uniacid'], 'id' => $id));
			if($state !== false) {
				exit('success');
			}
			exit('error');
		}
		if($op == 'recommend_dish') {
			$id = intval($_GPC['id']);
			$value = intval($_GPC['value']);
			$state = pdo_update('str_dish', array('recommend' => $value), array('uniacid' => $_W['uniacid'], 'id' => $id));
			if($state !== false) {
				exit('success');
			}
			exit('error');
		}
		/**
		 * 二次开发，分享菜品
		 * 
		 */
		 if($op == 'share_dish') {
			$id = intval($_GPC['id']);
			$value = intval($_GPC['value']);
			$state = pdo_update('str_dish', array('share' => $value), array('uniacid' => $_W['uniacid'], 'id' => $id));
			if($state !== false) {
				exit('success');
			}
			exit('error');
		}
		//门店开关闭管理 add by  lwq
		if($op == 'open_store'){
			$id = intval($_GPC['id']);
			$open = intval($_GPC['open']);
			//echo $open;
			$state = pdo_update('str_store',array('open' => $open), array('uniacid' => $_W['uniacid'], 'id' => $id));
			if($state !== false){
				exit('success');
			}
			exit('error');
		}
		//进入门店检测是否开启 edit by lwq
		if($op == 'get_open'){
			$id = intval($_GPC['id']);
			$open = pdo_fetchcolumn('SELECT open FROM ' . tablename('str_store') . ' WHERE ' . 'id=:id and uniacid=:uniacid', array(':id'=>id, ':uniacid'=>$_W['uniacid']));
			exit($open);
		}
		if($op == 'close_edit'){
		/**
		 * 二次开发-新增门店关闭信息编辑
		 */
			$data['nextStartTime'] = $_GPC['date'];
			$data['cantype'] = intval($_GPC['cantype']);
			$data['xingqi'] = $_GPC['xingqi'];
			$store_id = intval($_GPC['sid']);
			$state = pdo_update('str_store',$data,array('uniacid' => $_W['uniacid'], 'id' => $store_id));

			if($state !== false){
				exit('success');
			}
			exit('error');
		}
	}

	public function doWebSwitch() {
		global $_W, $_GPC;
		$sid = intval($_GPC['sid']);
		isetcookie('__sid', $sid, 86400 * 7);
		header('location: ' . $this->createWebUrl('manage'));
		exit();
	}
	//二次开发：设置问题订单
	public function doWebManage() {
		global $_W, $_GPC;
		$op = trim($_GPC['op']) ? trim($_GPC['op']) : 'order';
		$sid = intval($_GPC['__sid']);
		//exit($sid);
		$store = pdo_fetch('SELECT id, title, printer_name FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));

		if(empty($store)) {
			message('门店信息不存在或已删除', $this->createWebUrl('store'), 'error');
		}
		$pay_types = array(
			'alipay' => '支付宝支付',
			'wechat' => '微信支付',
			'credit' => '余额支付',
			'delivery' => '餐到付款',
		);
		/**
		 * 二次开发
		 */
		//订单详细统计
		if($op == 'stat_detail') {
			load()->func('tpl');
			$condition = " WHERE uniacid = :aid AND sid = :sid AND pay_type != ''";
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
			$is_print = intval($_GPC['is_print']);
			if(!$is_print) {
				if(!empty($_GPC['addtime'])) {
					$starttime = strtotime($_GPC['addtime']['start']);
					$endtime = strtotime($_GPC['addtime']['end']);
				} else {
					$starttime = strtotime(date('Y-m'));
					$endtime = TIMESTAMP;
				}
			} else {
				$starttime = intval($_GPC['starttime']);
				$endtime = intval($_GPC['endtime']);
				$title = date('Y-m-d H:i', $starttime) . ' ~~ ' . date('Y-m-d H:i', $endtime) . ' 订单统计';
			}
			$condition .= " AND addtime > :start AND addtime < :end";
			$params[':start'] = $starttime;
			$params[':end'] = $endtime;
			
			$count = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') .  $condition, $params);
			$total_price = pdo_fetchcolumn('SELECT SUM(price+delivery_fee) FROM ' . tablename('str_order') .  $condition, $params);

			$data = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . $condition . ' ORDER BY addtime ', $params);
			$total = array();
			if(!empty($data)) {
				foreach($data as &$da) {
					$total_price = $da['price']+$da['delivery_fee'];
					$key = date('Y-m-d', $da['addtime']);
					$return[$key]['price'] += $total_price;
					$return[$key]['count'] += 1;
					$total['total_price'] += $total_price;
					$total['total_count'] += 1;
					if($da['pay_type'] == 'alipay') {
						$return[$key]['alipay'] += $total_price;
						$total['total_alipay'] += $total_price;
					} elseif($da['pay_type'] == 'wechat') {
						$return[$key]['wechat'] += $total_price;
						$total['total_wechat'] += $total_price;
					} elseif($da['pay_type'] == 'credit') {
						$return[$key]['credit'] += $total_price;
						$total['total_credit'] += $total_price;
					} elseif($da['pay_type'] == 'delivery') {
						$return[$key]['delivery'] += $total_price;
						$total['total_delivery'] += $total_price;
					} else {
						$return[$key]['cash'] += $total_price;
						$total['total_cash'] += $total_price;
					}
				}
			}
			include $this->template('stat_detail');
		}

		if($op == 'stat_day') {
			//今日订单统计
			$orderby = trim($_GPC['orderby']) ? trim($_GPC['orderby']) : 'num';
			if($orderby == 'num') {
				$order_by = ' ORDER BY num DESC';
			} else {
				$order_by = ' ORDER BY price DESC';
			}
			$day = trim($_GPC['day']);
			if(empty($day)) {
				$start = intval($_GPC['start']);
				$end = intval($_GPC['end']);
			} else {
				$start = strtotime($day);
				$end =  strtotime($day) + 86399;
			}
			$data = array();
			$orders = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . " WHERE uniacid = :aid AND sid = :sid AND addtime >= :start AND addtime < :end  AND pay_type != '' ORDER BY id ASC", array(':sid' => $sid, ':aid' => $_W['uniacid'], ':start' => $start, ':end' => $end), 'id');
			$count = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') . " WHERE uniacid = :aid AND sid = :sid AND addtime >= :start AND addtime < :end  AND pay_type != '' ORDER BY id ASC", array(':sid' => $sid, ':aid' => $_W['uniacid'], ':start' => $start, ':end' => $end));
			if(!empty($orders)) {
				$str = implode(',', array_keys($orders));
				$data = pdo_fetchall('SELECT *,SUM(dish_num) AS num, SUM(dish_price) AS price FROM ' . tablename('str_stat') . " WHERE uniacid = :aid AND sid = :sid AND oid IN ({$str}) GROUP BY dish_id" . $order_by, array(':aid' => $_W['uniacid'], ':sid' => $sid), 'dish_id');
				$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_stat') . " WHERE uniacid = :aid AND sid = :sid AND oid IN ({$str})", array(':aid' => $_W['uniacid'], ':sid' => $sid));
				$price = pdo_fetchcolumn('SELECT SUM(price+delivery_fee) FROM ' . tablename('str_order') . " WHERE uniacid = :aid AND sid = :sid AND id IN ({$str})", array(':aid' => $_W['uniacid'], ':sid' => $sid));
			}
			if(!empty($orders)) {
				foreach($orders as &$da) {
					$total_price = $da['price']+$da['delivery_fee'];
					if($da['pay_type'] == 'alipay') {
						$return['alipay']['price'] += $total_price;
						$return['alipay']['num'] += 1;
					} elseif($da['pay_type'] == 'wechat') {
						$return['wechat']['price'] += $total_price;
						$return['wechat']['num'] += 1;
					} elseif($da['pay_type'] == 'credit') {
						$return['credit']['price'] += $total_price;
						$return['credit']['num'] += 1;
					} elseif($da['pay_type'] == 'delivery') {
						$return['delivery']['price'] += $total_price;
						$return['delivery']['num'] += 1;
					} else {
						$return['cash']['price'] += $total_price;
						$return['cash']['num'] += 1;
					}
				}
			}
			include $this->template('stat_detail');
		}

		//桌号设置
		if($op == 'table_post') {
			if(checksubmit('submit')) {
				if(!empty($_GPC['tables'])) {
					$tables_temp = explode("\n", $_GPC['tables']);
					$tables= array();
					if(!empty($tables_temp)) {
						foreach($tables_temp as $table) {
							if(empty($table)) continue;
							$tables[] = $table;
						}
					}
				}
				if(!empty($_GPC['rooms'])) {
					$rooms_temp = explode("\n", $_GPC['rooms']);
					$rooms= array();
					if(!empty($rooms_temp)) {
						foreach($rooms_temp as $room) {
							if(empty($room)) continue;
							$rooms[] = $room;
						}
					}
				}
				$data = array(
					'uniacid' => $_W['uniacid'],
					'sid' => $sid,
					'tables' => iserializer($tables),
					'rooms' => iserializer($rooms)
				);
				pdo_delete('str_tables', array('uniacid' => $_W['uniacid'], 'sid' => $sid));
				pdo_insert('str_tables', $data);
				message('设置包房/桌号成功', referer(), 'success');
			}

			$data = pdo_fetch('SELECT * FROM ' . tablename('str_tables') . ' WHERE uniacid = :uniacid AND sid = :sid', array(':uniacid' => $_W['uniacid'], ':sid' => $sid));
			if(!empty($data)) {
				$data['tables'] = implode("\n", iunserializer($data['tables']));
				$data['rooms'] = implode("\n", iunserializer($data['rooms']));
			}
			include $this->template('table');
		}

		if($op == 'cate_list') {
			$condition = ' uniacid = :aid AND sid = :sid';
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
			if(!empty($_GPC['keyword'])) {
				$condition .= " AND title LIKE '%{$_GPC['keyword']}%'";
			}
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;

			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_dish_category') . ' WHERE ' . $condition, $params);
			$lists = pdo_fetchall('SELECT * FROM ' . tablename('str_dish_category') . ' WHERE ' . $condition . ' ORDER BY displayorder DESC,id ASC LIMIT '.($pindex - 1) * $psize.','.$psize, $params, 'id');
			if(!empty($lists)) {
				$ids = implode(',', array_keys($lists));
				$nums = pdo_fetchall('SELECT count(*) AS num,cid FROM ' . tablename('str_dish') . " WHERE uniacid = :aid AND cid IN ({$ids}) GROUP BY cid", array(':aid' => $_W['uniacid']), 'cid');
			}
			$pager = pagination($total, $pindex, $psize);
			if(checksubmit('submit')) {
				if(!empty($_GPC['ids'])) {
					foreach($_GPC['ids'] as $k => $v) {
						$data = array(
							'title' => trim($_GPC['title'][$k]),
							'displayorder' => intval($_GPC['displayorder'][$k])
						);
						pdo_update('str_dish_category', $data, array('uniacid' => $_W['uniacid'], 'id' => intval($v)));
					}
					message('编辑成功', $this->createWebUrl('manage', array('op' => 'cate_list')), 'success');
				}
			}
			include $this->template('category');
		} elseif($op == 'cate_post') {
			if(checksubmit('submit')) {
				if(!empty($_GPC['title'])) {
					foreach($_GPC['title'] as $k => $v) {
						$v = trim($v);
						if(empty($v)) continue;
						$data['sid'] = $sid;
						$data['uniacid'] = $_W['uniacid'];
						$data['title'] = $v;
						$data['displayorder'] = intval($_GPC['displayorder'][$k]);
						pdo_insert('str_dish_category', $data);
					}
				}
				message('添加菜品分类成功', $this->createWebUrl('manage', array('sid' => $sid, 'op' => 'cate_list')), 'success');
			}
			include $this->template('category');
		} elseif($op == 'cate_del') {
			$id = intval($_GPC['id']);
			pdo_delete('str_dish_category', array('uniacid' => $_W['uniacid'], 'sid' => $sid, 'id' => $id));
			pdo_delete('str_dish', array('uniacid' => $_W['uniacid'], 'sid' => $sid, 'cid' => $id));
			message('删除菜品分类成功', $this->createWebUrl('manage', array('op' => 'cate_list')), 'success');
		} elseif($op == 'dish_del') {
			$id = intval($_GPC['id']);
			/**
			 * 二次开发：总店管理员删除单品，则str_store_dish，以及store_taocan中含有此单品的套餐都需要删除
			 */
			 //获取该dish的名称及种类。
			 $dish = pdo_fetch('SELECT title,dish_type FROM ' . tablename('str_dish') . ' WHERE id='.$id);
			 
			pdo_delete('str_dish', array('uniacid' => $_W['uniacid'], 'id' => $id));
			
			if(!empty($dish)&&$dish['dish_type'] == 'DANPIN'){
				pdo_run('DELETE FROM ' . tablename('str_dish') . " WHERE  zuhe LIKE %单品2% AND dish_type = 'TAOCAN'");
			 	//在总店菜品中获得包含该 单品的套餐，然后在门店中删除这些套餐
				pdo_run('DELETE FROM ' . tablename('str_store_dish') . ' WHERE dish_id IN' . '(SELECT id FROM '.
						tablename('str_dish') . " WHERE zuhe LIKE '%". $dish['title'] ."%' AND dish_type = 'TAOCAN')");
				pdo_run('DELETE FROM ' . tablename('str_store_taocan') . ' WHERE dish_id IN' . '(SELECT id FROM '.
						tablename('str_dish') . " WHERE zuhe LIKE '%". $dish['title'] ."%' AND dish_type = 'TAOCAN')");
			 }
			message('删除菜品成功', $this->createWebUrl('manage', array('op' => 'dish_list')), 'success');
		} elseif($op == 'dish_list') {
			$condition = ' uniacid = :aid AND sid = :sid';
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
			if(!empty($_GPC['keyword'])) {
				$condition .= " AND title LIKE '%{$_GPC['keyword']}%'";
			}
			if(!empty($_GPC['cid'])) {
				$condition .= " AND cid = :cid";
				$params[':cid'] = intval($_GPC['cid']);
			}

			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;

			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_dish') . ' WHERE ' . $condition, $params);
			$lists = pdo_fetchall('SELECT * FROM ' . tablename('str_dish') . ' WHERE ' . $condition . ' ORDER BY displayorder DESC,id ASC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			$pager = pagination($total, $pindex, $psize);
			$category = pdo_fetchall('SELECT title, id FROM ' . tablename('str_dish_category') . ' WHERE uniacid = :aid AND sid = :sid', array(':aid' => $_W['uniacid'], ':sid' => $sid), 'id');

			include $this->template('dish');
		} elseif($op == 'dish_post') {
			load()->func('tpl');
			$category = pdo_fetchall('SELECT title, id FROM ' . tablename('str_dish_category') . ' WHERE uniacid = :aid AND sid = :sid ORDER BY displayorder DESC, id ASC', array(':aid' => $_W['uniacid'], ':sid' => $sid));
			$id = intval($_GPC['id']);
			if($id) {
				$item = pdo_fetch('SELECT * FROM ' . tablename('str_dish') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $id));
				if(empty($item)) {
					message('菜品不存在或已删除', $this->createWebUrl('manage', array('dish_list')), 'success');
				}
			} else {
				$item['total'] = -1;
				$item['unitname'] = '份';
			}
			if(checksubmit('submit')) {
				$data = array(
					'sid' => $sid,
					'uniacid' => $_W['uniacid'],
					'title' => trim($_GPC['title']),
					'price' => floatval($_GPC['price']),
					'unitname' => trim($_GPC['unitname']),
					'total' => intval($_GPC['total']),
					'sailed' => intval($_GPC['sailed']),
					'grant_credit' => intval($_GPC['grant_credit']),
					'is_display' => intval($_GPC['is_display']),
					'cid' => intval($_GPC['cid']),
					'thumb' => trim($_GPC['thumb']),
					'recommend' => intval($_GPC['recommend']),
					'displayorder' => intval($_GPC['displayorder']),
					'description' => trim($_GPC['description'])
				);
				if($id) {
					pdo_update('str_dish', $data, array('uniacid' => $_W['uniacid'], 'id' => $id));
				} else {
					pdo_insert('str_dish', $data);
				}
				message('编辑菜品成功', $this->createWebUrl('manage', array('op' => 'dish_list')), 'success');
			}
			include $this->template('dish');
		} elseif($op == 'order') {
			load()->func('tpl');
			$condition = ' WHERE uniacid = :aid AND sid = :sid';
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;

			$status = intval($_GPC['status']);
			if($status) {
				$condition .= ' AND status = :stu';
				$params[':stu'] = $status;
			}
			$keyword = trim($_GPC['keyword']);
			if(!empty($keyword)) {
				$condition .= " AND (username LIKE '%{$keyword}%' OR mobile LIKE '%{$keyword}%')";
			}
			if(!empty($_GPC['addtime'])) {
				$starttime = strtotime($_GPC['addtime']['start']);
				$endtime = strtotime($_GPC['addtime']['end']) + 86399;
			} else {
				$starttime = strtotime('-15 day');
				$endtime = TIMESTAMP;
			}
			$condition .= " AND addtime > :start AND addtime < :end";
			$params[':start'] = $starttime;
			$params[':end'] = $endtime;

			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;

			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') .  $condition, $params);
			$data = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . $condition . ' ORDER BY addtime DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			if(!empty($data)) {
				foreach($data as &$da) {
					$da['is_trash'] = check_trash($da['sid'], $da['uid'], 'fetch');
				}
			}
			$pager = pagination($total, $pindex, $psize);
			include $this->template('order');
		} elseif($op == 'orderdetail') {
			$pay_types = pay_types();
			$id = intval($_GPC['id']);
			$order = pdo_fetch('SELECT * FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $id));
			if(empty($order)) {
				message('订单不存在或已经删除', $this->createWebUrl('manage', array('op' => 'order')), 'error');
			} else {
				$order['dish'] = get_dish($order['id']);
				if($order['comment'] == 1) {
					$comment = pdo_fetch('SELECT * FROM ' . tablename('str_order_comment') .' WHERE uniacid = :aid AND oid = :oid', array(':aid' => $_W['uniacid'], ':oid' => $id));
				}
			}
			include $this->template('order');
		} elseif($op == 'history_order'){
			/**
			 * @author lwq
			 * 二次开发：用户历史订单，根据消费额度，消费次数排名
			 */
			load()->func('tpl');
			$condition = ' WHERE uniacid = :aid AND sid = :sid GROUP BY uid';
			$type = intval($_GPC['type']);
			if($type == 0){
				$condition.= ' ORDER BY SUM(price) DESC';
			}else if($type == 1){
				$condition.= ' ORDER BY COUNT(uid) DESC';
			}else{
				$condition.= ' ORDER BY SUM(price) DESC';//默认
			}
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
			
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;
			
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') .  $condition, $params);
			$data = pdo_fetchall('SELECT sid,uid,COUNT(uid) as buynum,SUM(price) as total, MAX(addtime) as latestbuytime, username, mobile FROM ' . 
					tablename('str_order') . $condition . '  LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			if(!empty($data)) {
				foreach($data as &$da) {
					$da['is_trash'] = check_trash($da['sid'], $da['uid'], 'fetch');
				}
			}
			$pager = pagination($total, $pindex, $psize);
			include $this->template('hisorder');
		}elseif($op == 'his_orderdetail'){
			/**
			 * @author lwq
			 * 二次开发：用户订单详情中显示订单列表
			 */
			load()->func('tpl');
			$condition = ' WHERE uniacid = :aid AND sid = :sid AND uid = :uid';
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
			
			$uid = intval($_GPC['uid']);
			$params[':uid'] = $uid;
			
			$status = intval($_GPC['status']);
			if($status) {
				$condition .= ' AND status = :stu';
				$params[':stu'] = $status;
			}
			$keyword = trim($_GPC['keyword']);
			if(!empty($keyword)) {
				$condition .= " AND (username LIKE '%{$keyword}%' OR mobile LIKE '%{$keyword}%')";
			}
			if(!empty($_GPC['addtime'])) {
				$starttime = strtotime($_GPC['addtime']['start']);
				$endtime = strtotime($_GPC['addtime']['end']) + 86399;
			} else {
				$starttime = strtotime('-15 day');
				$endtime = TIMESTAMP;
			}
			$condition .= " AND addtime > :start AND addtime < :end";
			$params[':start'] = $starttime;
			$params[':end'] = $endtime;
			
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;
			
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') .  $condition, $params);
			$data = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . $condition . ' ORDER BY addtime DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			if(!empty($data)) {
				foreach($data as &$da) {
					$da['is_trash'] = check_trash($da['sid'], $da['uid'], 'fetch');
				}
			}
			$pager = pagination($total, $pindex, $psize);
			include $this->template('hisorder');
		}elseif($op == 'status') {
			$id = intval($_GPC['id']);
			$status = intval($_GPC['status']);
			$premark = $_GPC['premark'];
			if($status == 5) {
				pdo_update('str_order', array('pay_type' => 'cash'), array('uniacid' => $_W['uniacid'], 'id' => $id));
			} else {
				pdo_update('str_order', array('status' => $status), array('uniacid' => $_W['uniacid'], 'id' => $id));
			}
			//二次开发  by lwq 问题订单设置
			/**
			 * @author lwq
			 * 问题订单备注
			 */
			if($status == 6){
				pdo_update('str_order',array('premark'=>premark),array('uniacid' => $_W['uniacid'], 'id' => $id));
			}
			
			//todo
			wechat_notice($sid, $id, $status);
			message('更新订状态成功', referer(), 'success');
		} else if($op == 'exportExcel'){//二次开发 by lwq 导出为excel
			/**
			 * 二次开发：订单打印
			 * @author lwq
			 * 
			 */
			$status = intval($_GPC['status']);
			$title = array( "0" => "所有订单",
						   "1" => "待确认订单",
						   "2" => "处理中订单",
					       "3" => "已完成订单",
					       "4" => "已取消订单",
					       "6" => "问题订单"
			);
			$name = $title["{$status}"].date("Y-m-d");
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
			$condition = " WHERE uniacid=:aid AND sid=:sid";
			if($status != 0){
				$condition = " WHERE uniacid=:aid AND sid=:sid AND status=:status";
				$params[':status'] = $status;
			}
			$data = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . $condition, $params);
			
			$objPHPExcel = new PHPExcel();
			/*以下是一些设置 ，什么作者  标题啊之类的*/
			$objPHPExcel->getProperties()->setCreator("Admin")
			->setLastModifiedBy("Admin")
			->setTitle($name)
			->setSubject($name)
			->setDescription("订单数据")
			->setKeywords("excel")
			->setCategory("result file");
			/*设置栏目名称*/
			$column = array( "A1" => "微信平台编号", "B1" => "门店编号", "C1" => "订单编号", "D1" => "用户名", "E1" => "手机号", "F1" => "地址", "G1" => "总价", 
					"H1" => "下单时间", "I1" => "订单状态", "J1" => "支付类型"/*, "K1" => "订单状态", "L1" => "下单时间", "M1" => "订单状态", "N1" => "下单时间", "O1" => "订单状态"*/);
			$objPHPExcel->setActiveSheetIndex(0)
			//Excel的第A列，uid是你查出数组的键值，下面以此类推
			->setCellValue('A1', $column['A1'])
			->setCellValue('B1', $column['B1'])
			->setCellValue('C1', $column['C1'])
			->setCellValue('D1', $column['D1'])
			->setCellValue('E1', $column['E1'])
			->setCellValue('F1', $column['F1'])
			->setCellValue('G1', $column['G1'])
			->setCellValue('H1', $column['H1'])
			->setCellValue('I1', $column['I1'])
			->setCellValue('J1', $column['J1']);
			/*以下就是对处理Excel里的数据， 横着取数据，主要是这一步，其他基本都不要改*/
			foreach($data as $k => $v){
				$num=$k+2;				
				$v['addtime'] = date('Y-m-d H:i', $v['addtime']);
				$v['status'] = $title["{$v['status']}"];
				$objPHPExcel->setActiveSheetIndex(0)
				//Excel的第A列，uid是你查出数组的键值，下面以此类推
				->setCellValue('A'.$num, $v['uniacid'])
				->setCellValue('B'.$num, $v['sid'])
				->setCellValue('C'.$num, $v['id'])
				->setCellValue('D'.$num, $v['username'])
				->setCellValueExplicit('E'.$num, $v['mobile'], PHPExcel_Cell_DataType::TYPE_STRING)
				->setCellValue('F'.$num, $v['address'])
				->setCellValue('G'.$num, $v['price'])
				->setCellValue('H'.$num, $v['addtime'])
				->setCellValue('I'.$num, $v['status'])
				->setCellValue('J'.$num, $v['paytype']);
			}
			$objPHPExcel->getActiveSheet()->setTitle($name);
			$objPHPExcel->setActiveSheetIndex(0);
			ob_end_clean();//清除缓冲区，防止乱码
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="'.$name.'.xls"');
			header('Cache-Control: max-age=0');
			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
			$objWriter->save('php://output');
			exit;
		}elseif($op == 'trash_add') {
			$id = intval($_GPC['id']);
			$order = get_order($id);
			if(empty($order)) {
				message('订单不存在或已经删除', referer(), 'error');
			}
			$isexist = pdo_fetchcolumn('SELECT uid FROM ' . tablename('str_user_trash') . ' WHERE uniacid = :uniacid AND sid = :sid AND uid = :uid', array(':uniacid' => $_W['uniacid'], ':sid' => $order['sid'], ':uid' => $order['uid']));
			if(!empty($isexist)) {
				message('该用户已经在黑名单中', referer(), 'error');
			}
			$data = array(
				'uniacid' => $_W['uniacid'],
				'sid' => $order['sid'],
				'uid' => $order['uid'],
				'username' => $order['username'],
				'mobile' => $order['mobile'],
				'addtime' => TIMESTAMP,
			);
			pdo_insert('str_user_trash', $data);
			message('添加到黑名单成功', referer(), 'success');
		} elseif($op == 'trash_list') {
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;
			$condition = ' WHERE uniacid = :uniacid AND sid = :sid';
			$params = array(
				':uniacid' => $_W['uniacid'],
				':sid' => $sid,
			);
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_user_trash') .  $condition, $params);
			$data = pdo_fetchall('SELECT * FROM ' . tablename('str_user_trash') . $condition . ' ORDER BY addtime DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			$pager = pagination($total, $pindex, $psize);
			include $this->template('trash');
		} elseif($op == 'trash_del') {
			$uid = intval($_GPC['uid']);
			pdo_delete('str_user_trash', array('uniacid' => $_W['uniacid'], 'sid' => $sid, 'uid' => $uid));
			message('从黑名单中移除成功', referer(), 'success');
		} elseif($op == 'comment_list') {
			load()->func('tpl');
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;
			$condition = ' WHERE a.uniacid = :uniacid AND a.sid = :sid';
			$params = array(
				':uniacid' => $_W['uniacid'],
				':sid' => $sid,
			);
			$status = intval($_GPC['status']);
			if($status > 0) {
				$condition .= " AND a.status = :status";
				$params[':status'] = $status;
			}
			$oid = intval($_GPC['oid']);
			if($oid > 0) {
				$condition .= " AND a.oid = :oid";
				$params[':oid'] = $oid;
			}

			if(!empty($_GPC['addtime'])) {
				$starttime = strtotime($_GPC['addtime']['start']);
				$endtime = strtotime($_GPC['addtime']['end']) + 86399;
			} else {
				$starttime = strtotime('-15 day');
				$endtime = TIMESTAMP;
			}
			$condition .= " AND a.addtime > :start AND a.addtime < :end";
			$params[':start'] = $starttime;
			$params[':end'] = $endtime;

			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order_comment') . ' AS a '.  $condition, $params);
			$data = pdo_fetchall('SELECT a.*, b.uid,b.openid,b.addtime FROM ' . tablename('str_order_comment') . ' AS a LEFT JOIN ' . tablename('str_order') . ' AS b ON a.oid = b.id ' . $condition . ' ORDER BY a.addtime DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			$pager = pagination($total, $pindex, $psize);
			include $this->template('comment');
		} elseif($op == 'comment_status') {
			$id = intval($_GPC['id']);
			pdo_update('str_order_comment', array('status' => intval($_GPC['status'])), array('uniacid' => $_W['uniacid'], 'id' => $id));
			message('设置评论状态成功', $this->createWebUrl('manage', array('op' => 'comment_list')), 'success');
		} elseif($op == 'orderdel') {
			$id = intval($_GPC['id']);
			pdo_delete('str_order', array('uniacid' => $_W['uniacid'], 'id' => $id));
			pdo_delete('str_stat', array('uniacid' => $_W['uniacid'], 'oid' => $id));
			pdo_delete('str_order_comment', array('uniacid' => $_W['uniacid'], 'oid' => $id));
			message('删除订单成功', $this->createWebUrl('manage', array('op' => 'order')), 'success');
		} elseif($op == 'print_post') {
			$id = intval($_GPC['id']);
			if($id > 0) {
				$item = pdo_fetch('SELECT * FROM ' . tablename('str_print') . ' WHERE uniacid = :uniacid AND id = :id', array(':uniacid' => $_W['uniacid'], ':id' => $id));
			} 
			if(empty($item)) {
				$item = array('status' => 1, 'print_nums' => 1, 'type' => 1);
			}
			if(checksubmit('submit')) {
				$data['type'] = intval($_GPC['type']); 
				$data['status'] = intval($_GPC['status']); 
				$data['name'] = !empty($_GPC['name']) ? trim($_GPC['name']) : message('打印机名称不能为空', '', 'error');
				$data['print_no'] = !empty($_GPC['print_no']) ? trim($_GPC['print_no']) : message('机器号不能为空', '', 'error');
				$data['key'] = trim($_GPC['key']);
				$data['print_nums'] = intval($_GPC['print_nums']) ? intval($_GPC['print_nums']) : 1;
				if(!empty($_GPC['qrcode_link']) && (strexists($_GPC['qrcode_link'], 'http://') || strexists($_GPC['qrcode_link'], 'https://'))) {
					$data['qrcode_link'] = trim($_GPC['qrcode_link']);
				}
				$data['print_header'] = trim($_GPC['print_header']);
				$data['print_footer'] = trim($_GPC['print_footer']);
				$data['uniacid'] = $_W['uniacid'];
				$data['sid'] = $sid;
				if(!empty($item) && $id) {
					pdo_update('str_print', $data, array('uniacid' => $_W['uniacid'], 'id' => $id));
				} else {
					pdo_insert('str_print', $data);
				}
				message('更新打印机设置成功', $this->createWebUrl('manage', array('op' => 'print_list')), 'success');
			}
			include $this->template('print');
		} elseif($op == 'print_list') {
			/**
			 * 二次开发：打印机
			 */
			//$data = pdo_fetchall('SELECT * FROM ' . tablename('str_print') . ' WHERE uniacid = :uniacid AND sid = :sid', array(':uniacid' => $_W['uniacid'], ':sid' => $sid));
			$store = pdo_fetch('SELECT * FROM ' . tablename('str_store') . ' WHERE uniacid = :uniacid AND id = :sid', array(':uniacid' => $_W['uniacid'], ':sid' => $sid));
			include $this->template('print');
		} elseif($op == 'print_del') {
			$id = intval($_GPC['id']);
			pdo_delete('str_print', array('uniacid' => $_W['uniacid'], 'id' => $id));
			message('删除打印机成功', referer(), 'success');
		} elseif($op == 'log_del') {
			$id = intval($_GPC['id']);
			pdo_delete('str_order_print', array('uniacid' => $_W['uniacid'], 'id' => $id));
			message('删除打印记录成功', referer(), 'success');
		} elseif($op == 'print_log') {
			$id = intval($_GPC['id']);
			$item = pdo_fetch('SELECT * FROM ' . tablename('str_print') . ' WHERE uniacid = :uniacid AND id = :id', array(':uniacid' => $_W['uniacid'], ':id' => $id));
			if(empty($item)) {
				message('打印机不存在或已删除', $this->createWebUrl('manage', array('op' => 'print_list')), 'success');
			}
			if(!empty($item['print_no']) && !empty($item['key'])) {
				$wprint = new wprint();
				$status = $wprint->QueryPrinterStatus($item['print_no'], $item['key']);
				if(is_error($status)) {
					$status = '查询打印机状态失败。请刷新页面重试';
				}
			}
			$condition = ' WHERE a.uniacid = :aid AND a.sid = :sid AND a.pid = :pid';
			$params[':aid'] = $_W['uniacid']; 
			$params[':sid'] = $sid; 
			$params[':pid'] = $id; 
			if(!empty($_GPC['oid'])) {
				$oid = trim($_GPC['oid']);
				$condition .= ' AND a.oid = :oid';
				$params[':oid'] = $oid; 
			}
			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;

			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order_print') . ' AS a ' . $condition, $params);
			$data = pdo_fetchall('SELECT a.*,b.username,b.mobile FROM ' . tablename('str_order_print') . ' AS a LEFT JOIN' . tablename('str_order') . ' AS b ON a.oid = b.id' . $condition . ' ORDER BY addtime DESC LIMIT ' . ($pindex - 1) * $psize . ',' . $psize, $params);
			$pager = pagination($total, $pindex, $psize);
			include $this->template('print');
		} elseif($op == 'ajaxprint') {
			$id = intval($_GPC['id']);
			$status = print_order($id, true);
			if(is_error($status)) {
				exit($status['message']);
			}
			exit('success');
		} elseif($op == 'zong_dish'){
			/**
			 * 二次开发：从总店菜品库中拉取菜品
			 */
			$condition = ' uniacid = :aid AND share = 1';
			$params[':aid'] = $_W['uniacid'];
			//$params[':sid'] = $sid;
			if(!empty($_GPC['keyword'])) {
				$condition .= " AND title LIKE '%{$_GPC['keyword']}%'";
			}
			if(!empty($_GPC['cid'])) {
				$condition .= " AND cid = :cid";
				$params[':cid'] = intval($_GPC['cid']);
			}

			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;

			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_dish') . ' WHERE ' . $condition, $params);
			$lists = pdo_fetchall('SELECT * FROM ' . tablename('str_dish') . ' WHERE ' . $condition . ' ORDER BY displayorder DESC,id ASC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			
			$pager = pagination($total, $pindex, $psize);
			$category = pdo_fetchall('SELECT title, id FROM ' . tablename('str_dish_category') . ' WHERE uniacid = :aid', array(':aid' => $_W['uniacid']), 'id');
			
			$store_dishes = pdo_fetchall('SELECT * FROM ' . tablename('str_store_dish') . ' WHERE uniacid = :aid AND store_id = :sid', array(':aid' => $_W['uniacid'], ':sid' => $sid));
			//判断菜品是否已经加入该门店菜单
			for($i = 0 ; $i < count($lists) ;$i++){
				$lists[$i]['in'] = 0;//默认是不在菜单里面
				for($j=0; $j < count($store_dishes); $j++){
					if($lists[$i]['id'] == $store_dishes[$j]['dish_id']){
						$lists[$i]['in'] = 1;//某个菜品在该门店菜单内
						break;
					}
				}
			}
			load()->func('tpl');
			include $this->template('zongdish');
		} elseif($op == 'add_dish'){
			/**
			 * 二次开发，从总店中加入菜单，或者移除菜单
			 */			
			$do = intval($_GPC['value']);
			$state = false;
			if($do == 1){//移除菜单
				$id = intval($_GPC['id']);
				$state = pdo_delete('str_store_dish', array('uniacid' => $_W['uniacid'], 'store_id' => $sid, 'dish_id' => $id));
				if($_GPC['type'] == 'TAOCAN'){
					//删除套餐，里面的单品不删除
					$tstate = pdo_delete('str_store_taocan', array('uniacid' => $_W['uniacid'], 'store_id' => $sid, 'dish_id' => $id));
				}else{//移除单品，则包含该单品的套餐也一并删除
					//获得单品名称
					$dish = pdo_fetch('SELECT title FROM ' . tablename('str_dish') . ' WHERE id = :dish_id AND uniacid = :acid', array(':dish_id' => $id, ':acid' => $_W['uniacid']));
					if(!empty($dish)){
						//在总店菜品中获得包含该 单品的套餐，然后在门店中删除这些套餐
						$tstate = pdo_run('DELETE FROM ' . tablename('str_store_dish') . ' WHERE dish_id IN' . '(SELECT id FROM '.
							  tablename('str_dish') . " WHERE zuhe LIKE '%". $dish['title'] ."%' AND dish_type = 'TAOCAN'" . ' ) AND store_id='
							  . $sid);
						pdo_run('DELETE FROM ' . tablename('str_store_taocan') . ' WHERE dish_id IN' . '(SELECT id FROM '.
							  tablename('str_dish') . " WHERE zuhe LIKE '%". $dish['title'] ."%' AND dish_type = 'TAOCAN'" . ' ) AND store_id='
							  . $sid);
					}
					
				}
					
			}else if($do == 2){//套餐加入菜单，特别处理
				$insert['dish_id'] = intval($_GPC['id']);
				$insert['store_id'] = $sid;
				$insert['uniacid'] = $_W['uniacid'];
				$insert['dish_name'] = $_GPC['name'];
				$insert['dtype'] = trim($_GPC['type']);
				
				$state = pdo_insert('str_store_dish',$insert);
				if($_GPC['type'] == 'TAOCAN'){
					//套餐的话需要将套餐中的单品加进去。
					//获得套餐dish 获得里面的组合
					$taocan = pdo_fetch('SELECT zuhe FROM ' . tablename('str_dish') . ' WHERE id = :dish_id AND uniacid = :acid', array(':dish_id' => $insert['dish_id'], ':acid' => $_W['uniacid']));
					$danpinNames = explode('+',$taocan['zuhe']);
					for($i = 0; $i < count($danpinNames); $i++){
						//没有的插入
						$dish = pdo_fetch('SELECT id FROM ' . tablename('str_store_dish') . ' WHERE dish_name = :dish_name AND store_id = :sid', array(':dish_name' => $danpinNames[$i], ':sid' => $sid));
						//门店中无该单品=
						if(empty($dish)){
							//获得单个单品的id
							$danpin = pdo_fetch('SELECT id FROM ' . tablename('str_dish') . ' WHERE title = :title AND uniacid = :acid', array(':title' => $danpinNames[$i], ':acid' => $_W['uniacid']));
						
							$dish['dish_id'] = $danpin['id'];
							$dish['store_id'] = $sid;
							$dish['uniacid'] = $_W['uniacid'];
							$dish['dish_name'] = $danpinNames[$i];
							$dish['dtype']	= 'DANPIN';									
							pdo_insert('str_store_dish', $dish);
						}						
					}
					
					$tstate = pdo_insert('str_store_taocan', $insert);
				}//else{
					//$tstate = pdo_insert('str_store_danpin',$insert);
				//}
					
				
			}
			if($state !== false && $tstate !== false) {
				exit('success');
			}
			exit('error');
		} elseif($op == 'store_dish'){
			/**
			 * 二次开发：获取门店的菜单
			 */
			 $str_store_dish = tablename('str_store_dish');
			 $str_dish = tablename('str_dish');
			$condition = $str_store_dish.'.uniacid = :aid AND '. $str_store_dish .'.store_id = :sid AND ' . $str_store_dish . '.dish_id = ' . $str_dish . '.id AND '. $str_dish . '.share = 1';
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
			if(!empty($_GPC['keyword'])) {
				$condition .= (" AND " . $str_dish .".title LIKE '%{$_GPC['keyword']}%'");
			}
			if(!empty($_GPC['cid'])) {
				$condition .= (" AND " . $str_dish . ".cid = :cid");
				$params[':cid'] = intval($_GPC['cid']);
			}

			$pindex = max(1, intval($_GPC['page']));
			$psize = 20;

			$total = pdo_fetchcolumn('SELECT ' . $str_dish . '.* FROM ' . $str_dish . ',' .$str_store_dish .' WHERE ' . $condition , $params);
			$lists = pdo_fetchall('SELECT ' . $str_dish . '.*,'. $str_store_dish . '.selling,'. $str_store_dish .'.kucun FROM ' . $str_dish . ',' .$str_store_dish 
			.' WHERE ' . $condition . ' ORDER BY displayorder DESC,'. $str_dish .'.id ASC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
			
			$pager = pagination($total, $pindex, $psize);
			$category = pdo_fetchall('SELECT title, id FROM ' . tablename('str_dish_category') . ' WHERE uniacid = :aid', array(':aid' => $_W['uniacid']), 'id');
			
			$store_dishes = pdo_fetchall('SELECT * FROM ' . tablename('str_store_dish') . ' WHERE uniacid = :aid AND store_id = :sid', array(':aid' => $_W['uniacid'], ':sid' => $sid));
			load()->func('tpl');
			include $this->template('dish');
		} elseif($op == 'update_kucun'){
			/**
			 * 二次开发：修改菜单库存
			 */
			 $data['kucun'] = intval($_GPC['kucun']);			 
			 $dish_id = intval($_GPC['dish_id']);

			 $state = pdo_update('str_store_dish', $data, array('uniacid' => $_W['uniacid'], 'store_id' => $sid, 'dish_id' => $dish_id));
			
			if($state !== false){
				exit('success');
			}
			exit('error');
		} elseif($op == 'sell_status'){
			/**
			 * 二次开发，上下架
			 */
			 $dish_id = intval($_GPC['dish_id']);
			 
			 $status = intval($_GPC['state']);
			 $data;
			 if($status == 0){//下架
			 	$data['selling'] = 0;
			 }elseif($status == 1){//上架
			 	$data['selling'] = 1;
			 }else{
			 	$data['selling'] = 0;
			 }
			 
			 $state = pdo_update('str_store_dish', $data, array('uniacid' => $_W['uniacid'], 'store_id' => $sid, 'dish_id' => $dish_id));
			 if($state !== false){
				exit('success');
			}
			exit('error');
		}elseif($op == 'set_printer'){
			/**
			 * 二次开发：设置打印机名称
			 */
			 $data['printer_name'] = $_GPC['name'];
			 $state = pdo_update('str_store',$data,array('uniacid' => $_W['uniacid'], 'id' => $sid));
			 if($state !== false)
			 	exit('success');
			 else
			 	exit('error');
		}elseif($op == 'print_order'){
			/**
			 * 二次开发：浩顺打印机打印订单
			 * @author lwq
			 */
			//获取要打印但是未打印的已支付订单（处理中订单），一条订单
			$order = pdo_fetch('SELECT * FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND sid = :sid AND print = 0 AND status = 2 ORDER BY addtime ASC', array(':aid' => $_W['uniacid'], ':sid' => $sid));
			if(empty($order)) {
				message('订单不存在或已经删除', $this->createWebUrl('manage', array('op' => 'order')), 'error');
			} else {
				$order['dish'] = get_dish($order['id']);
				//时间戳转成日期
				$order['addtime'] = date('Y-m-d h:i:s', $order['addtime']);
				exit(json_encode($order));
			}
			//转成json返回
		}elseif($op == 'update_print'){
			/**
			 * 二次开发：打印成功后，更新订单打印状态
			 * @author LWQ
			 */
			 $id = intval($_GPC['id']);
			 $data['print'] = 1;//设为已打印
			 $state = pdo_update('str_order', $data, array('uniacid' => $_W['uniacid'], 'id' => $id, 'sid' => $sid));
			 if($state !== false)
			 	exit('success');
			 exit('error');
			
		}elseif($op == 'reprint'){
			/**
			 * 二次开发：重新打印
			 */
			 
		}elseif($op == 'cancelprint'){
			/**
			 * 二次开发：取消打印
			 */
		}
		if($op == 'clerk_post') {
			$accounts = uni_accounts();
			foreach($accounts as $k => $li) {
				if($li['level'] < 3) {
					unset($li[$k]);
				}
			}
			$notice_acid = pdo_fetchcolumn('SELECT notice_acid FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
			$id = intval($_GPC['id']);
			$clerk = get_clerk($id);
			
			if($_W['ispost']) {
				$insert['uniacid'] = $_W['uniacid'];
				$insert['sid'] = $sid;
				$insert['title'] = trim($_GPC['title']);
				$insert['nickname'] = trim($_GPC['nickname']);
				$insert['openid'] = trim($_GPC['openid']);
				$insert['email'] = trim($_GPC['email']);
				$insert['is_sys'] = intval($_GPC['is_sys']);
				if(empty($insert['openid']) && empty($insert['email'])) {
					exit('粉丝openid和店员邮箱必须填写一项');
				}
				if($id > 0) {
					pdo_update('str_clerk', $insert, array('uniacid' => $_W['uniacid'], 'id' => $id));
				} else {
					$insert['addtime'] = TIMESTAMP;
					pdo_insert('str_clerk', $insert);
				}
				exit('success');
			}
			include $this->template('clerk');
		}

		if($op == 'fetch_openid') {
			$acid = intval($_GPC['acid']);
			$nickname = trim($_GPC['nickname']);
			$openid = trim($_GPC['openid']);
			if(!empty($openid)) {
				$data = pdo_fetch('SELECT openid,nickname FROM ' . tablename('mc_mapping_fans') . ' WHERE uniacid = :uniacid AND acid = :acid AND openid = :openid ', array(':uniacid' => $_W['uniacid'], ':acid' => $acid, ':openid' => $openid));
			}
			if(empty($data)) {
				if(!empty($nickname)) {
					$data = pdo_fetch('SELECT openid,nickname FROM ' . tablename('mc_mapping_fans') . ' WHERE uniacid = :uniacid AND acid = :acid AND nickname = :nickname ', array(':uniacid' => $_W['uniacid'], ':acid' => $acid, ':nickname' => $nickname));
					if(empty($data)) {
						exit('error');
					} else {
						exit(json_encode($data));
					}
				} else {
					exit('error');
				}
			} else {
				exit(json_encode($data));
			}
		}

		if($op == 'clerk_list') {
			$data = pdo_fetchall('SELECT * FROM ' . tablename('str_clerk') . ' WHERE uniacid = :aid AND sid = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
			include $this->template('clerk');
		}

		if($op == 'clerk_del') {
			$id = intval($_GPC['id']);
			pdo_delete('str_clerk', array('uniacid' => $_W['uniacid'], 'sid' => $sid, 'id' => $id));
			message('删除店员成功', referer(), 'success');
		}
		
	}

	//宏信物联打印机扫描接口
	public function doWebPrint() {
		global $_W, $_GPC;
		if(isset($_GPC['sta'])) {
			$id = intval($_GPC['id']);
			$status = intval($_GPC['sta']);
			if($id > 0) {
				$status = $status == 0 ? 1 : 2;
				pdo_update('str_order_print', array('status' => $status), array('id' => $id));
				return false;
			}
		}
		$usr = !empty($_GPC['usr']) ? trim($_GPC['usr']) : '';
		$ord = !empty($_GPC['ord']) ? trim($_GPC['ord']) : 'no';
		$sgn = !empty($_GPC['sgn']) ? trim($_GPC['sgn']) : 'no';
		hongx_print_echo($usr, $ord, $sgn);
	}

	public function doMobileIndex() {
		global $_W, $_GPC;
		$config = get_config();
		if($config['version'] == 2) {
			$sid = pdo_fetchcolumn('SELECT id FROM ' . tablename('str_store') . ' WHERE uniacid = :uniacid AND status = 1 ORDER BY displayorder DESC', array(':uniacid' => $_W['uniacid']));
			if(!$sid) {
				message('没有有效的门店');
			} else {
				header('location: ' . $this->createMobileUrl('dish', array('sid' => $sid)));
				exit();
			}
		}
		$pindex = max(1, intval($_GPC['page']));
		$psize = 100;
		$key = trim($_GPC['key']);
		$condition = ' WHERE uniacid = :aid AND status = 1';
		$params[':aid'] = $_W['uniacid'];
		if(!empty($key)) {
			$condition .= " AND title LIKE '%{$key}%'";
		}
		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_store') . $condition, $params);
		$data = pdo_fetchall('SELECT * FROM ' . tablename('str_store') . $condition . ' ORDER BY displayorder DESC LIMIT ' . (($pindex - 1) * $psize) . ', ' . $psize, $params);
		$str = '';
		if(!empty($data)) {
			foreach($data as &$dca) {
				$dca['business_hours_flag'] = 0;
				$dca['business_hours'] = iunserializer($dca['business_hours']);
				if(is_array($dca['business_hours'])) {
					foreach($dca['business_hours'] as $li) {
						$li_s_tmp = explode(':', $li['s']); //开始时间
						$li_e_tmp = explode(':', $li['e']); //结束时间
						$s_timepas = mktime($li_s_tmp[0], $li_s_tmp[1]);
						$e_timepas = mktime($li_e_tmp[0], $li_e_tmp[1]);
						$now = TIMESTAMP;
						if($now >= $s_timepas && $now <= $e_timepas) {
							$dca['business_hours_flag'] = 1;
							break;
						}
					}
				}
			}
		}			
		$pager = pagination($total, $pindex, $psize, '', array('before' => 0, 'after' => 0));
		$address_id = intval($_GPC['address_id']);
		$address = get_address($address_id);
		if(empty($address)) {
			$address = get_default_address();
		}
		include $this->template('index');
	}

	public function doMobileDish() {
		global $_W, $_GPC;
		$sid = intval($_GPC['sid']);
		checkauth();
		$address_id = intval($_GPC['address_id']);
		$address = get_address($address_id);
		if(empty($address)) {
			$address = get_default_address();
		}
		if((!empty($address))&&intval($address['sid'])!=$sid){
			header('Location: '.$this->createMobileUrl('dish',array('sid' => intval($address['sid'])))); 
			exit;
		}
		$str_store_dish = tablename('str_store_dish');
		$str_dish = tablename('str_dish');
		$condition = $str_store_dish.'.uniacid = :aid AND '. $str_store_dish .'.store_id = :sid AND ' . $str_store_dish . '.dish_id = ' . $str_dish . '.id AND '. $str_dish . '.share = 1';
		$params[':aid'] = $_W['uniacid'];
		$params[':sid'] = $sid;
		
		checkclerk($sid);
		check_trash($sid);
		$store = pdo_fetch('SELECT * FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
		$title = $store['title'];
		$_share = get_share($store);
		if($store['comment_status'] == 1) {
			$comment_stat = comment_stat($sid);
		}
		$store['business_hours_flag'] = 0;
		$store['business_hours'] = iunserializer($store['business_hours']);
		if(is_array($store['business_hours'])) {
			$hour_str = '';
			foreach($store['business_hours'] as $li) {
				$hour_str .= $li['s'] . '~' . $li['e'] . '、';
				$li_s_tmp = explode(':', $li['s']); //开始时间
				$li_e_tmp = explode(':', $li['e']); //结束时间
				$s_timepas = mktime($li_s_tmp[0], $li_s_tmp[1]);
				$e_timepas = mktime($li_e_tmp[0], $li_e_tmp[1]);
				$now = TIMESTAMP;
				if(!$store['business_hours_flag']) {
					if($now >= $s_timepas && $now <= $e_timepas) {
						$store['business_hours_flag'] = 1;
					}
				}
			}
			$hour_str = trim($hour_str, '、');
		}

		if(empty($store)) {
			message('门店信息不存在', $this->createMobileUrl('index'), 'error');
		}
		if(!empty($_GPC['f'])) {
			del_order_cart($sid);
		}
		$address_id = intval($_GPC['address_id']);
		$address = get_address($address_id);
		if(empty($address)) {
			$address = get_default_address();
		}
		//获取购物车的信息
		$cart = get_order_cart($sid);
		$dish = pdo_fetchall('SELECT ' . $str_dish . '.*,'. $str_store_dish . '.selling,'. $str_store_dish .'.kucun FROM ' . $str_dish . ',' .$str_store_dish .' WHERE ' . $condition . ' ORDER BY displayorder DESC,'. $str_dish .'.id ASC', $params);
			
		include $this->template('dish');
	}

	public function doMobileAjaxDish() {
		global $_W, $_GPC;
		$sid = intval($_GPC['sid']);
		$cid = intval($_GPC['cid']);
		$store = pdo_fetch('SELECT delivery_price,business_hours,send_price FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
		$store['business_hours_flag'] = 0;
		$store['business_hours'] = iunserializer($store['business_hours']);
		if(is_array($store['business_hours'])) {
			$hour_str = '';
			foreach($store['business_hours'] as $li) {
				$hour_str .= $li['s'] . '~' . $li['e'] . '、';
				$li_s_tmp = explode(':', $li['s']); //开始时间
				$li_e_tmp = explode(':', $li['e']); //结束时间
				$s_timepas = mktime($li_s_tmp[0], $li_s_tmp[1]);
				$e_timepas = mktime($li_e_tmp[0], $li_e_tmp[1]);
				$now = TIMESTAMP;
				if(!$store['business_hours_flag']) {
					if($now >= $s_timepas && $now <= $e_timepas) {
						$store['business_hours_flag'] = 1;
					}
				}
			}
			$hour_str = trim($hour_str, '、');
		}
		//获取购物车的信息
		$cart = get_order_cart($sid);
		$category = pdo_fetch('SELECT title, id FROM ' . tablename('str_dish_category') . ' WHERE uniacid = :aid AND sid = :sid AND id = :id', array(':aid' => $_W['uniacid'], ':sid' => $sid, ':id' => $cid));
		$dish = pdo_fetchall('SELECT * FROM ' . tablename('str_dish') . ' WHERE uniacid = :aid AND sid = :sid AND cid = :cid AND is_display = 1 ORDER BY displayorder DESC, id ASC', array(':aid' => $_W['uniacid'], ':sid' => $sid, ':cid' => $cid));
		include $this->template('dish_model');
		exit();
	}
	public function doMobileStore() {
		global $_W, $_GPC;
		$sid = intval($_GPC['sid']);
		checkclerk($sid);
		check_trash($sid);
		$store = pdo_fetch('SELECT * FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
		$title = $store['title'];
		$_share = get_share($store);
		$store['thumbs'] = iunserializer($store['thumbs']);
		$store['business_hours_flag'] = 0;
		$store['business_hours'] = iunserializer($store['business_hours']);
		if(is_array($store['business_hours'])) {
			$hour_str = '';
			foreach($store['business_hours'] as $li) {
				$hour_str .= $li['s'] . '~' . $li['e'] . '、';
				$li_s_tmp = explode(':', $li['s']); //开始时间
				$li_e_tmp = explode(':', $li['e']); //结束时间
				$s_timepas = mktime($li_s_tmp[0], $li_s_tmp[1]);
				$e_timepas = mktime($li_e_tmp[0], $li_e_tmp[1]);
				$now = TIMESTAMP;
				if(!$store['business_hours_flag']) {
					if($now >= $s_timepas && $now <= $e_timepas) {
						$store['business_hours_flag'] = 1;
					}
				}
			}
			$hour_str = trim($hour_str, '、');
		}
		$store['address'] = str_replace('+', '', $store['distirct']) . $store['address'];
		include $this->template('store');
	}

	public function doMobileOrder() {
		global $_W, $_GPC;
		checkauth();
		if(!$_W['isajax']) {
			$sid = intval($_GPC['sid']);
			checkclerk($sid);
			check_trash($sid);
			$str_store_dish = tablename('str_store_dish');
			$str_dish = tablename('str_dish');
			$condition = $str_store_dish.'.uniacid = :aid AND '. $str_store_dish .'.store_id = :sid AND ' . $str_store_dish . '.dish_id = ' . $str_dish . '.id AND '. $str_dish . '.share = 1';
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;
		
			$store = pdo_fetch('SELECT * FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
			$title = $store['title'];
			$_share = get_share($store);
			if(empty($store)) {
				message('门店不存在', '', 'error');
			}
			//购物车
			$op = trim($_GPC['op']) ? trim($_GPC['op']) : 'post';
			if($op=='get'){
				$cart = get_order_cart($sid);
			}else{
				$cart = set_order_cart($sid);
			}
			if(is_error($cart)) {
				message("购物车错误".$cart.message, '', 'error');
			}
			$dishes = $cart['data'];
			//提醒客户需要点的菜品（比如：米饭）
			$is_add = 0;



			$address_id = intval($_GPC['address_id']);
			$address = get_address($address_id);
			if(empty($address)) {
				$address = get_default_address();
			}
			if(!empty($dishes)) {
				$ids_str = implode(',', array_keys($dishes));
				$dish_info = pdo_fetchall('SELECT ' . $str_dish . '.*,'. $str_store_dish . '.selling,'. $str_store_dish .'.kucun FROM ' . $str_dish . ',' .$str_store_dish .' WHERE ' . $condition . ' AND '.$str_dish.'.id IN ('.$ids_str.') ORDER BY displayorder DESC,'. $str_dish .'.id ASC', $params);
				//$dish_info = pdo_fetchall('SELECT * FROM ' . tablename('str_dish') ." WHERE uniacid = :aid AND sid = :sid AND id IN ($ids_str)", array(':aid' => $_W['uniacid'], ':sid' => $sid), 'id');
			}
		} else {
			$sid = intval($_GPC['sid']);
			$store = pdo_fetch('SELECT notice_acid,title,store_tpl,member_tpl,delivery_tpl,delivery_price FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
			$out['errno'] = 1;
			$out['error'] = '';
			$data['uniacid'] = $_W['uniacid'];
			$data['sid'] = $sid;
			$data['uid'] = $_W['member']['uid'];
			$data['openid'] = $_W['openid'];
			$data['order_type'] = 2;

			$addrdata = array(
				'uniacid' => $_W['uniacid'],
				'uid' => $_W['member']['uid'],
				'realname' => trim($_GPC['realname']),
				'mobile' => trim($_GPC['mobile']),
				'room' => trim($_GPC['room']),
			);
			pdo_update('str_address', $addrdata, array('uniacid' => $_W['uniacid'], 'id' => $_GPC['address_id']));
			$address = get_address($_GPC['address_id']);
			$data['address'] = trim($address['address']).' - '.trim($address['room']);
			$data['mobile'] = trim($address['mobile']);
			$data['username'] = trim($address['realname']);
			
			$data['delivery_time'] = trim($_GPC['delivery_time']) ? trim($_GPC['delivery_time']) : '尽快送出';
			$data['delivery_fee'] = $store['delivery_price'];
			$data['note'] = trim($_GPC['note']);
			$data['pay_type'] = '';
			$data['num'] = 0;
			$data['price'] = 0;
			$cart = get_order_cart($sid);
			//取得订单
			foreach($cart['data'] as $k => $v) {
				$k = intval($k);
				$v = intval($v);
				if($_GPC['dish'.$k]){
					$v=intval($_GPC['dish'.$k]);
					$cart['data'][$k] = $v;
					$data['num'] = $v + intval($data['num']);
					$cart['num'] = $data['num'];
				}else{
					$v=0;
				}
			}
			if($cart['num'] == 0) {
				$out['errno'] = 1;
				$out['error'] = '菜品为空';
				exit(json_encode($out));
			}
			/**
			* 二次开发：套餐转化成单品，并加上已点单品的数量，加起来之后一项项比对是否超过库存
			*/
			$temcart = $cart['data'];
			$fakecart = $cart['data'];
			foreach($temcart as $k => $v){
				$dtype = pdo_fetch('SELECT dish_type FROM '.tablename('str_dish').' WHERE id=:id',array(':id' => $k));
				if(!empty($dtype)&&$dtype == 'TAOCAN'&&$v){
					$zuhe = pdo_fetch('SELECT zuhe FROM '.tablename('str_dish'). ' WHERE id=:id',array(':id' => $k));
					$zuhe = explode('+', $zuhe);
					//先取出套餐中的每一份单品
					for($i = 0; $i < count($zuhe); $i++){
						//查找是否已经买了该单品
						$dish_id = pdo_fetch('SELECT id FROM '.tablename('str_dish').' WHERE title=:title',array(':title' => $zuhe[$i]));
						if(!empty($dish_id)){
							$comtem = $fakecart;
							$times = 0;
							foreach($comtem as $m => $n){
								//如果买了，则加上数量
								if($dish_id == $m){
									$fakecart[$m] = $n+1;
									break;
								}
								
								$times++;
							}
							if($times==count($comtem)){//单品中不包含套餐中的单品，则加入套餐中的单品
								$fakecart[$dish_id] = 1;
								//同时删除套餐
								unset($fakecart[$dish_id]);
							}
						}
					}
					
				}
			}
			//看下每份菜品的库存是否合格
			foreach($fakecart as $k => $v){
				$kucun = pdo_fetch('SELECT kucun FROM '.tablename('str_store_dish').' WHERE dish_id=:id AND store_id=:sid AND uniacid=:aid', array(':id' => $k, ':sid' => $sid, ':aid' => $_W['uniacid']));
				if(!empty($kucun)&&$v>$kucun){
					$out['errno'] = 1;
					$out['error'] = '库存不足';
					exit(json_encode($out));
				}
			}
			
			$data['addtime'] = TIMESTAMP;
			$data['status'] = 1;
			$data['is_notice'] = 0;
			$data['grant_credit'] = $cart['grant_credit'];;
			$data['is_grant'] = 0;
			pdo_insert('str_order', $data);
			$id = pdo_insertid();
			
			$str_store_dish = tablename('str_store_dish');
			$str_dish = tablename('str_dish');
			$condition = $str_store_dish.'.uniacid = :aid AND '. $str_store_dish .'.store_id = :sid AND ' . $str_store_dish . '.dish_id = ' . $str_dish . '.id AND '. $str_dish . '.share = 1';
			$params[':aid'] = $_W['uniacid'];
			$params[':sid'] = $sid;

			if(!empty($cart['data'])) {
				$ids_str = implode(',', array_keys($cart['data']));
				$dish_info = pdo_fetchall('SELECT ' . $str_dish . '.*,'. $str_store_dish . '.selling,'. $str_store_dish .'.kucun FROM ' . $str_dish . ',' .$str_store_dish .' WHERE ' . $condition . ' AND '.$str_dish.'.id IN ('.$ids_str.') ORDER BY displayorder DESC,'. $str_dish .'.id ASC', $params);
			}
			
			foreach($cart['data'] as $k => $v) {
				pdo_query('UPDATE ' . tablename('str_dish') . " set sailed = sailed + {$v} WHERE uniacid = :aid AND id = :id", array(':aid' => $_W['uniacid'], ':id' => $k));
				$stat = array();
				if($k && $v) {
					$stat['oid'] = $id;
					$stat['uniacid'] = $_W['uniacid'];
					$stat['sid'] = $sid;
					$stat['dish_id'] = $k;
					$stat['dish_num'] = $v;
					$d_info = pdo_fetchall('SELECT ' . $str_dish . '.* FROM ' . $str_dish . ',' .$str_store_dish .' WHERE '.$str_dish.'.id='.$k.' ORDER BY displayorder DESC,'. $str_dish .'.id ASC', $params);
					$stat['dish_title'] = $d_info[0]['title'];
					$stat['dish_price'] = ($v * $d_info[0]['price']);
					$data['price'] = $data['price'] + intval($stat['dish_price']);
					$stat['addtime'] = TIMESTAMP;
					pdo_insert('str_stat', $stat);
				}
			}
			pdo_update('str_order', $data, array('uniacid' => $_W['uniacid'], 'id' => $id));
			//是否打印订单
			init_print_order($sid, $id, 'order');
			//微信邮件通知
			//init_notice_order($sid, $id, 'order');
			del_order_cart($sid);
			if($id) {
				$out['id'] = $id;
				$out['openid'] = $data['openid'];
				$out['priceYuan'] = $data['price'];
				$out['errno'] = 0;
				$out['url'] = $this->createMobileUrl('pay', array('id' => $id));
			} else {
				$out['errno'] = 1;
				$out['error'] = '保存订单失败';
			}
			exit(json_encode($out));
		}
		include $this->template('order');
	}
	public function doMobileMyorder() {
		global $_W, $_GPC;
		checkauth();
		$sid = intval($_GPC['sid']);
		check_trash($sid);

		$store = get_store($sid);
		$_share = get_share($store);
		if(empty($store)) {
			message('门店不存在', referer(), 'error');
		}
		$title = $store['title'];
		$where = ' WHERE uniacid = :aid AND uid = :uid';
		$params = array(
			':aid' => $_W['uniacid'],
			':uid' => $_W['member']['uid']
		);
		$status = intval($_GPC['status']);

		if($status > 0 && $status != 5) {
			$where .= ' AND status = :status';
			$params[':status'] = $status;
		} 
		if($status == 5) {
			$where .= " AND pay_type = ''";
		}

		$pindex = max(1, intval($_GPC['page']));
		$psize = 10;

		$limit = ' ORDER BY addtime DESC LIMIT ' . ($pindex - 1) * $psize . ',' . $psize;
		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') . $where, $params);
		$data = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . $where . $limit, $params);
		$pager = pagination($total, $pindex, $psize, '', array('before' => 0, 'after' => 0));
		include $this->template('myorder');
	}
	public function doMobileOrderDetail() {
		global $_W, $_GPC;
		checkauth();
		$sid = intval($_GPC['sid']);
		$oid = intval($_GPC['id']);
		$store = pdo_fetch('SELECT * FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
		if(empty($store)) {
			message('门店不存在', '', 'error');
		}
		$_share = get_share($store);
		$title = $store['title'];
		$order = pdo_fetch('SELECT * FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $oid));
		if(empty($order)) {
			message('订单信息不存在', '', 'error');
		}
		$pay_types = pay_types();
		$order['dish'] = get_dish($order['id']);
		include $this->template('orderdetail');
	}
	public function doMobileAjaxOrder() {
		global $_W, $_GPC;
		checkauth();
		$id = intval($_GPC['id']);
		$op = trim($_GPC['op']);
		$order = pdo_fetch('SELECT id FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $id));
		$out['errno'] = 0;
		$out['error'] = 0;
		if(empty($order)) {
			$out['errno'] = 1;
			$out['error'] = '订单不存在';
			exit(json_encode($out));
		}
		if($op == 'editstatus') {
			pdo_update('str_order', array('status' => 3), array('uniacid' => $_W['uniacid'], 'id' => $id));
		} elseif($op == 'del') {
			pdo_update('str_order', array('status' => 7), array('uniacid' => $_W['uniacid'], 'id' => $id));
			$out['error'] = $this->createMobileUrl('myorder');
		}
		exit(json_encode($out));
	}
	public function doMobilePay() {
		global $_W, $_GPC;
		checkauth();
		$id = intval($_GPC['id']);
		$order = pdo_fetch('SELECT * FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $id));
		if(empty($order)) {
			message('订单不存在或已删除', $this->createMobileUrl('myorder'), 'error');
		}
		if(!empty($order['pay_type'])) {
			message('该订单已付款或已关闭,正在跳转到我的订单...',$this->createMobileUrl('myorder', array('sid' => $order['sid'])), 'info');
		}
		$params['module'] = "str_takeout";
		$params['tid'] = $order['id'];
		$params['ordersn'] = $order['id'];
		$params['user'] = $_W['member']['uid'];
		$params['fee'] = $order['price'] + $order['delivery_fee'] ;
		$params['title'] = $_W['account']['name'] . "外卖订单{$order['ordersn']}";
		include $this->template('pay');
	}
	public function payResult($params) {
		global $_W, $_GPC;
		$data['pay_type'] = $params['type'];
		pdo_update('str_order', $data, array('id' => $params['tid'], 'uniacid' => $_W['uniacid']));
		$order = pdo_fetch('SELECT * FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $params['tid']));
		if($params['from'] == 'return') {
			init_print_order($order['sid'], $order['id'], 'pay');
			init_notice_order($order['sid'], $order['id'], 'order');
			//wechat_notice($order['sid'], $order['id'], 5);
			if($params['type'] == 'credit' || $params['type'] == 'delivery') {
				message('支付成功！', $this->createMobileUrl('orderdetail', array('id' => $order['id'], 'sid' => $order['sid'])), 'success');
			} else {
				message('支付成功！', '../../app/' .$this->createMobileUrl('orderdetail', array('id' => $order['id'], 'sid' => $order['sid'])), 'success');
			}
		}
	}


	public function doMobileComment() {
		global $_W, $_GPC;
		$id = intval($_GPC['id']);
		$order = pdo_fetch('SELECT * FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $id));
		check_trash($order['sid']);
		if(!$_W['isajax']) {
			if(empty($order)) {
				message('订单不存在或已经删除', $this->createMobileUrl('myorder'), 'error');
			}
			if($order['comment'] == 1) {
				$comment = pdo_fetch('SELECT * FROM ' . tablename('str_order_comment') .' WHERE uniacid = :aid AND oid = :oid', array(':aid' => $_W['uniacid'], ':oid' => $id));
			}
		} else {
			$out['errno'] = 0;
			$out['error'] = 0;
			if(empty($order)) {
				$out['errno'] = 1;
				$out['error'] = '订单不存在或已经删除';
				exit(json_encode($out));
			}
			$store = pdo_fetch('SELECT id,comment_set FROM ' . tablename('str_store') .' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $order['sid']));

			if($order['comment'] == 1) {
				$out['errno'] = 1;
				$out['error'] = '该订单已经评价过';
				exit(json_encode($out));
			}
			if(!empty($_GPC['score_data'])) {
				$insert = array(
					'uniacid' => $_W['uniacid'],
					'sid' => $order['sid'],
					'oid' => $order['id'],
					'uid' => $order['uid'],
					'addtime' => TIMESTAMP,
					'status' => ($store['comment_set']) == 1 ? 1 : 3,
					'note' => trim($_GPC['note']),
				);
				foreach($_GPC['score_data'] as $row) {
					if($row['id'] && in_array($row['id'], array('taste', 'speed', 'serve'))) {
						$score = intval($row['score']);
						$insert[$row['id']] = $score;
					}
				}
				pdo_insert('str_order_comment', $insert);
			}
			pdo_update('str_order', array('comment' => 1), array('uniacid' => $_W['uniacid'], 'id' => $id));
			exit(json_encode($out));
		}
		include $this->template('comment');
	}
	//没隔一段时间检查是否有新的订单，并打印新的订单
	public function doWebCron() {
		global $_W, $_GPC;
		$op = trim($_GPC['op']) ? trim($_GPC['op']) : 'print';
		//飞蛾打印机订单打印状态查询。1:打印成功，2：未打印
		if($op == 'print') {
			$sid = intval($_GPC['__sid']);
			$data = pdo_fetchall('SELECT a.foid, b.print_no, b.key FROM ' . tablename('str_order_print') . ' AS a LEFT JOIN '.tablename('str_print').' AS b ON a.pid = b.id WHERE a.uniacid = :aid AND a.sid = :sid AND a.status = 2 AND a.print_type = 1 ORDER BY addtime ASC LIMIT 5', array(':aid' => $_W['uniacid'], ':sid' => $sid));
			if(!empty($data)) {
				foreach($data as $da) {
					if(!empty($da['foid']) && !empty($da['print_no']) && !empty($da['key'])) {
						$print = new wprint();
						$status = $print->QueryOrderState($da['print_no'], $da['key'], $da['foid']);
						if(!is_error($status)) {
							pdo_update('str_order_print', array('status' => $status), array('uniacid' => $_W['uniacid'], 'sid' => $sid, 'foid' => $da['foid']));
						}
					}
				}
			}
		} elseif($op == 'order') {
			//订单提醒功能
			$sid = intval($_GPC['__sid']);//店铺id
			$order = pdo_fetch('SELECT id FROM ' . tablename('str_order') . ' WHERE uniacid = :uniacid AND sid = :sid AND is_notice = 0 ORDER BY addtime DESC', array(':uniacid' => $_W['uniacid'], ':sid' => $sid));
			if(!empty($order)) {
				pdo_update('str_order', array('is_notice' => 1), array('uniacid' => $_W['uniacid'], 'id' => $order['id']));
				exit('success');
			}
			exit('error');
		} elseif($op == 'auto_print'){
			
		}
	}
	public function doWebSystem() {
		global $_W, $_GPC;
		include $this->template('system');
	}

	public function doMobileManage() {
		global $_W, $_GPC;
		$op = trim($_GPC['op']) ? trim($_GPC['op']) : 'list';
		$sid = $_GPC['sid'];
		$store = pdo_fetch('SELECT * FROM ' . tablename('str_store') . ' WHERE uniacid = :aid AND id = :id', array(':aid' => $_W['uniacid'], ':id' => $sid));
		if(empty($store)) {
			message('门店不存在', referer(), 'error');
		}
		$_share = get_share($store);
		$title = $store['title'];
		$clerk = checkclerk($sid);
		if(is_error($clerk)) {
			message($clerk['message'], referer(), 'error');
		}
		if($op == 'list') {
			$where = ' WHERE uniacid = :aid AND sid = :sid';
			$params = array(
				':aid' => $_W['uniacid'],
				':sid' => $sid
			);
			if($status > 0 && $status != 5) {
				$where .= ' AND status = :status';
				$params['status'] = $status;
			} 
			if($status == 5) {
				$where .= " AND pay_type = ''";
			}
			$pindex = max(1, intval($_GPC['page']));
			$psize = 10;

			$limit = ' ORDER BY id DESC LIMIT ' . ($pindex - 1) * $psize . ',' . $psize;
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') . $where, $params);
			$data = pdo_fetchall('SELECT * FROM ' . tablename('str_order') . $where . $limit, $params);
			$pager = pagination($total, $pindex, $psize, '', array('before' => 0, 'after' => 0));
			include $this->template('manage');
		} elseif($op == 'detail') {
			$id = intval($_GPC['id']);
			$order = pdo_fetch('SELECT * FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND sid = :sid AND id = :id', array(':aid' => $_W['uniacid'], ':sid' => $store['id'], ':id' => $id));
			if(empty($order)) {
				message('订单不存在或已经删除', referer(), 'error');
			}
			$order['dish'] = get_dish($order['id']);
			include $this->template('manage-detail');
		} 
	}

	public function doMobileStatus() {
		global $_W, $_GPC;
		$id = intval($_GPC['id']);
		$sid = intval($_GPC['sid']);
		$status = intval($_GPC['status']);
		$order = pdo_fetch('SELECT id FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND sid = :sid AND id = :id', array(':aid' => $_W['uniacid'], ':sid' => $sid, ':id' => $id));
		if(empty($order)) {
			exit('订单不存在');
		}
		if($status == 5) {
			pdo_update('str_order', array('pay_type' => 'cash'), array('uniacid' => $_W['uniacid'], 'id' => $id));
		} else {
			pdo_update('str_order', array('status' => $status), array('uniacid' => $_W['uniacid'], 'id' => $id));
		}
		wechat_notice($sid, $id, $status);
		exit('success');
	}

	public function doMobilePrint() {
		global $_W, $_GPC;
		$id = intval($_GPC['id']);
		$sid = intval($_GPC['sid']);
		$order = pdo_fetch('SELECT id FROM ' . tablename('str_order') . ' WHERE uniacid = :aid AND sid = :sid AND id = :id', array(':aid' => $_W['uniacid'], ':sid' => $sid, ':id' => $id));
		if(empty($order)) {
			exit('订单不存在');
		}
		$status = print_order($id);
		if(is_error($status)) {
			exit($status['message']);
		}
		exit('success');
	}

	public function doMobileAddress() {
		global $_W, $_GPC;
		checkauth();
		$id = intval($_GPC['id']);
		$sid = intval($_GPC['sid']);
		$uid = $_W['member']['uid'];
		$store = get_store($sid);
		$op = trim($_GPC['op']) ? trim($_GPC['op']) : 'list';
		$r = trim($_GPC['r']) ? trim($_GPC['r']) : '';
		$_share = get_share($store);
		if(empty($store)&&($op!='init')) {
			message('商家不存在', '', 'error');
		}
		$where = ' WHERE uniacid = :aid AND uid = :uid';
		$params = array(
			':aid' => $_W['uniacid'],
			':uid' => $_W['member']['uid']
		);
		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order') . $where, $params);
		$title = $store['title'];
		$return_url = '';
		if(!empty($_GPC['return_url'])) {
			$return_url = urldecode($_GPC['return_url']);
		}
		if($op == 'list') {
			$address_id = intval($_GPC['address_id']);
			$currentadd = get_address($address_id);
			if(empty($currentadd)) {
				$currentadd = get_default_address();
			}
			$addressesgroup = get_addressesgroup();
			if($r=='1'){
				$stores=pdo_fetchall('SELECT distinct(sid),title FROM ' . tablename('str_address').',' .tablename('str_store').' WHERE '.tablename('str_address').'.sid='.$sid.' AND '.tablename('str_store').'.id='.$sid.' AND '.tablename('str_address').'.uniacid = :aid AND '.tablename('str_address').'.uid = :uid', array(':aid' => $_W['uniacid'], ':uid' => $uid));
			}else{
				$stores=pdo_fetchall('SELECT distinct(sid),title FROM ' . tablename('str_address').',' .tablename('str_store').' WHERE '.tablename('str_address').'.sid='.tablename('str_store').'.id'.' AND '.tablename('str_address').'.uniacid = :aid AND '.tablename('str_address').'.uid = :uid', array(':aid' => $_W['uniacid'], ':uid' => $uid));
			}
		}
		if($op == 'post'||$op == 'init') {
			$currentadd = get_default_address();
			$id = intval($_GPC['id']);
			$address = get_address($id);
			$otherArea = pdo_fetchall('SELECT points,title,id FROM ' . tablename('str_store') . ' WHERE uniacid = :aid ORDER BY id ASC', array(':aid' => $_W['uniacid']));
			if($_W['ispost']) {
				$data = array(
					'uniacid' => $_W['uniacid'],
					'uid' => $_W['member']['uid'],
					'realname' => trim($_GPC['realname']),
					'mobile' => trim($_GPC['mobile']),
					'address' => trim($_GPC['address']),
					'room'=> trim($_GPC['room']),
					'sid'=> trim($_GPC['sid'])
				);
				if(!empty($address)) {
					pdo_update('str_address', $data, array('uniacid' => $_W['uniacid'], 'id' => $id));
				} else {
					pdo_insert('str_address', $data);
					$id = pdo_insertid();
				}
				exit(json_encode(array('errorno' => 0, 'message' => $id)));
			}
			if(empty($address)) {
				$address['realname'] = $currentadd['realname'];
				$address['mobile'] = $currentadd['mobile'];
			}
		}

		if($op == 'del') {
			$id = intval($_GPC['id']);
			pdo_delete('str_address', array('uniacid' => $_W['uniacid'], 'uid' => $_W['member']['uid'], 'id' => $id));
			exit(json_encode(array('errorno' => 0, 'message' => '','del' => $id)));
		}

		if($op == 'default') {
			$id = intval($_GPC['id']);
			pdo_update('str_address', array('is_default' => 0), array('uniacid' => $_W['uniacid'], 'uid' => $_W['member']['uid']));
			pdo_update('str_address', array('is_default' => 1), array('uniacid' => $_W['uniacid'], 'id' => $id));
			exit(json_encode(array('errorno' => 0, 'message' => '')));
		}

		include $this->template('address');
	}
	public function doMobileComment_list() {
		global $_W, $_GPC;
		checkauth();
		$sid = intval($_GPC['sid']);
		check_trash($sid);
		$store = get_store($sid);
		$_share = get_share($store);
		if(empty($store)) {
			message('商家不存在', '', 'error');
		}
		$title = $store['title'];
		$comment_stat = comment_stat($sid);
		$avg = ($comment_stat['avg_taste'] + $comment_stat['avg_serve'] + $comment_stat['avg_speed'])/3;
		$pindex = max(1, intval($_GPC['page']));
		$psize = 15;
		$condition = ' WHERE a.uniacid = :uniacid AND a.sid = :sid AND a.status = 1';
		$params = array(
			':uniacid' => $_W['uniacid'],
			':sid' => $sid,
		);

		$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('str_order_comment') . ' AS a '.  $condition, $params);
		$data = pdo_fetchall('SELECT a.*, b.nickname,b.avatar,b.realname FROM ' . tablename('str_order_comment') . ' AS a LEFT JOIN ' . tablename('mc_members') . ' AS b ON a.uid = b.uid ' . $condition . ' ORDER BY a.addtime DESC LIMIT '.($pindex - 1) * $psize.','.$psize, $params);
		$pager = pagination($total, $pindex, $psize, '', array('before' => 0, 'after' => 0));
		include $this->template('comment_list');
	}

	public function doWebLimit() {
		global $_W, $_GPC;
		$op = trim($_GPC['op']) ? trim($_GPC['op']) : 'list';
		if($op == 'list') {
			if(!$_W['isfounder']) {
				message('此项操作只有超级管理员有权限', referer(), 'error');
			}
			$title = intval($_GPC['title']);
			$condition = '';
			if($title > 0) {
				$condition .= " WHERE uniacid = {$title}";
			} else {
				$title = trim($_GPC['title']);
				if(!empty($title)) {
					$condition .= " WHERE name LIKE '%{$title}%'";
				}
			}
			$pindex = max(1, intval($_GPC['page']));
			$psize = 15;
			$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('uni_account') . $condition);
			$accounts = pdo_fetchall('SELECT * FROM ' . tablename('uni_account') . $condition .' ORDER BY uniacid DESC LIMIT '.($pindex - 1) * $psize.','.$psize, array(), 'uniacid');
			if(!empty($accounts)) {
				$ids = implode(',', array_keys($accounts));
				$limits = pdo_fetchall('SELECT uniacid, num_limit FROM ' . tablename('str_config') . " WHERE uniacid IN ({$ids})", array(), 'uniacid');
			}
			$pager = pagination($total, $pindex, $psize);
		}

		if($op == 'num') {
			if(!$_W['isfounder']) {
				exit('您没有权限进行该操作');
			}
			$uniacid = intval($_GPC['uniacid']);
			if(!$uniacid) exit('公众号信息错误');
			$num = intval($_GPC['num']);
			$config = get_config($uniacid);
			if(!empty($config)) {
				pdo_update('str_config', array('num_limit' => $num), array('uniacid' => $uniacid));
			} else {
				$data = array(
					'uniacid' => $uniacid,
					'version' => 1,
					'num_limit' => $num
				);
				pdo_insert('str_config', $data);
			}
			exit('success');
		}

		include $this->template('limit');
	}
}
