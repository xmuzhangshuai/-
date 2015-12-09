<?php
/*
 * Created on 2015��11��30��
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
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
  function curlrequest($url,$data,$method='post'){  
    $ch = curl_init(); //初始化CURL句柄   s
    
    curl_setopt($ch, CURLOPT_URL, $url); //设置请求的URL  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); //设为TRUE把curl_exec()结果转化为字串，而不是直接输出   
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); //设置请求方式  
      
    $aHeader[] = "Content-Type:application/x-www-form-urlencoded";  
      
    curl_setopt($ch,CURLOPT_HTTPHEADER,$aHeader);//设置HTTP头信息  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//设置提交的字符串  
    $document = curl_exec($ch);//执行预定义的CURL   
    curl_close($ch); 
      
    return $document;  
}  
?>
