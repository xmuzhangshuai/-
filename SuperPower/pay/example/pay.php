<?php
/* *
 * Ping++ Server SDK
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写, 并非一定要使用该代码。
 * 该代码仅供学习和研究 Ping++ SDK 使用，只是提供一个参考。
*/
define('IN_SYS', true);
require '../../framework/bootstrap.inc.php';
require IA_ROOT . '/web/common/bootstrap.sys.inc.php';
load()->web('common');

defined('IN_IA') or exit('Access Denied');
define('IN_GW', true);
require_once(dirname(__FILE__) . '/../init.php');
$input_data = json_decode(file_get_contents('php://input'), true);
if (empty($input_data['channel']) || empty($input_data['amount'])) {
    echo 'channel or amount is empty';
    exit();
}
$channel = strtolower($input_data['channel']);
$amount = $input_data['amount'];
$orderNo = $input_data['order_no'];

$order = pdo_fetch('SELECT * FROM '.tablename('str_order').' WHERE id=:id',array(':id'=>$orderNo));
if(!empty($order)){
	$payAmount = $order['price']*100;
	if($payAmount!=$amount){
		echo '0';//支付金额不正确
		exit(json_encode($out));
	}
	$amount = $order['price']*100;//为了保证金额的正确性
}else{
	echo '1';//获取不到该订单
	exit(json_encode($out));
}

//$extra 在使用某些渠道的时候，需要填入相应的参数，其它渠道则是 array() .具体见以下代码或者官网中的文档。其他渠道时可以传空值也可以不传。
$extra = array();
switch ($channel) {
    case 'alipay_wap':
        $extra = array(
            'success_url' => 'http://www.11yaoyao.com/pay/alipay_success.php',
            'cancel_url' => 'http://www.11yaoyao.com/pay/alipay_cancel.php'
        );
        break;
    case 'upmp_wap':
        $extra = array(
            'result_url' => 'http://www.yourdomain.com/result?code='
        );
        break;
    case 'bfb_wap':
        $extra = array(
            'result_url' => 'http://www.yourdomain.com/result?code=',
            'bfb_login' => true
        );
        break;
    case 'upacp_wap':
        $extra = array(
            'result_url' => 'http://www.yourdomain.com/result'
        );
        break;
    case 'wx_pub':
        $extra = array(
            'open_id' => $input_data['open_id']
        );
        break;
    case 'wx_pub_qr':
        $extra = array(
            'product_id' => 'Productid'
        );
        break;
    case 'yeepay_wap':
        $extra = array(
            'product_category' => '1',
            'identity_id'=> 'your identity_id',
            'identity_type' => 1,
            'terminal_type' => 1,
            'terminal_id'=>'your terminal_id',
            'user_ua'=>'your user_ua',
            'result_url'=>'http://www.yourdomain.com/result'
        );
        break;
    case 'jdpay_wap':
        $extra = array(
            'success_url' => 'http://www.yourdomain.com',
            'fail_url'=> 'http://www.yourdomain.com',
            'token' => 'dsafadsfasdfadsjuyhfnhujkijunhaf'
        );
        break;
}

\Pingpp\Pingpp::setApiKey('sk_live_0eTCuP5aL0qHfXrz5O54qbf5');
try {
    $ch = \Pingpp\Charge::create(
        array(
            'subject'   => '企鹅造饭',
            'body'      => '企鹅造饭订单支付',
            'amount'    => $amount,
            'order_no'  => $orderNo,
            'currency'  => 'cny',
            'extra'     => $extra,
            'channel'   => $channel,
            'client_ip' => $_SERVER['REMOTE_ADDR'],
            'app'       => array('id' => 'app_mzXP8ODWj508qfHa')
        )
    );
    echo $ch;
} catch (\Pingpp\Error\Base $e) {
    header('Status: ' . $e->getHttpStatus());
    echo($e->getHttpBody());
}
