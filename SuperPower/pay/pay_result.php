<?php
/*define('IN_SYS', true);
require '../framework/bootstrap.inc.php';
require IA_ROOT . '/web/common/bootstrap.sys.inc.php';
load()->web('common');
require '../framework/bootstrap.inc.php';
require '../framework/function/pdo.func.php';
require IA_ROOT . '/app/common/bootstrap.app.inc.php';
*/
define('IN_SYS', true);
require '../framework/bootstrap.inc.php';
require IA_ROOT . '/web/common/bootstrap.sys.inc.php';
load()->web('common');

defined('IN_IA') or exit('Access Denied');
define('IN_GW', true);
$event = json_decode(file_get_contents("php://input"));

// 对异步通知做处理
if (!isset($event->type)) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    exit("fail");
}
switch ($event->type) {
    case "charge.succeeded":
        // 开发者在此处加入对支付异步通知的处理代码
        //獲得訂單ID
        $order_no = $event->data->object->order_no;
        $channel = $event->data->object->channel;
		$amount = $event->data->object->amount;
		$order = pdo_fetch('SELECT * FROM '.tablename('str_order').' WHERE id=:id',array(':id'=>$orderNo));
		if(!empty($order)){
			$payAmount = $order['price']*100;
			if($payAmount!=$amount){
				$order['status'] = 5;
		        if($channel=='alipay_wap')
		        	$order['pay_type'] = '支付宝:已支付'.$amount;
		        elseif($channel=='wx_pub')
		        	$order['pay_type'] = '微信支付:已支付'.$amount;
		        pdo_update('str_order',$order,array('id'=>$order_no));
				header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
				break;
			}
		}
        $order['status'] = 2;//已支付
        if($channel=='alipay_wap')
        	$order['pay_type'] = '支付宝';
        elseif($channel=='wx_pub')
        	$order['pay_type'] = '微信支付';

        pdo_update('str_order',$order,array('id'=>$order_no));
        header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
        break;
    case "refund.succeeded":
        // 开发者在此处加入对退款异步通知的处理代码
        header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
        break;
    default:
        header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
        break;
}
?>
