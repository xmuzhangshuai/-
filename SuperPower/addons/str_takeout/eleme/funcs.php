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
?>
