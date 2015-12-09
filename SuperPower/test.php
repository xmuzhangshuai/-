<?php
header("Content-type:text/html;charset=utf-8");
 $now = "1448973400";
 $params = array("consumer_key"=>"3445350222","timestamp"=>$now,"restaurant_id"=>63949185,"tp_restaurant_id"=>"358495");
 $join = concatParams($params);
 
 $path = "http://v2.openapi.ele.me/restaurant/binding/";
 $signature = genSig($path,$params,"89b9b887a5dab845622072f4dc26a77d6263bd5a");
 //$result = file_get_contents($path."?consumer_key=0170804777&sig=d675fcddd66fb162e8b37693b7f22ff99aaf500a&timestamp=1448973400&restaurant_id=62028381");
 //echo $result;
 echo $join."<br/>";
 echo $signature;
 
$orderID = "100196508600829257";
$path2 = "http://v2.openapi.ele.me/order/100196508600829257/status/";
$params = array("consumer_key"=>"0170804777","timestamp"=>$now,'status'=>2);
$signature2 = genSig($path2,$params,"87217cb263701f90316236c4df00d9352fb1da76");
echo "<br/>".$signature2."";
 
  function concatParams($params) {
    ksort($params);
    $pairs = array();
    foreach($params as $key=>$val) {
        array_push($pairs, $key . '=' . urlencode($val));
    }
    return join('&', $pairs);
  }

  function genSig($pathUrl, $params, $consumerSecret) {
    $params = concatParams($params);
    $str = $pathUrl.'?'.$params.$consumerSecret;
    //echo "hex=".bin2hex($str)."<br/>";
    return sha1(bin2hex($str));
  }

?>