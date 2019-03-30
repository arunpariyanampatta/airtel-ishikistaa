<?php
set_time_limit(0);
ini_set('memory_limit', '-1');
date_default_timezone_set('Africa/Dar_Es_Salaam');
require_once  __DIR__ .'/vendor/autoload.php';
require_once 'queue-config.php';
require_once 'DbConfig.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


try{
$connection = new AMQPStreamConnection('localhost',5672, 'airtel_manage', 'manage@CNu8Ut');
$channel = $connection->channel();
}
catch(Exception $ex){
    
    echo "Error : ".$ex->getMessage();
}


echo " [*] Waiting for messages. To exit press CTRL+C \n";
$callback = function ($msg) {
$message            =  json_decode($msg->body,TRUE);
$mobile             =  $message['msisdn'];
$subStatus          = $message['charging_type'];
$amount             = $message['next_charge_amount'];
$totChargedAmount   = $message['tot_amount_charged'];
$deliveryState      = balanceCheck($mobile,$amount,$totChargedAmount);

if($totChargedAmount >= 90){
$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

}

if($deliveryState){
$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
}
else{
$msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'],true,true);
}
};

 
     $channel->queue_declare('airtel_ishikistaa_retry', false, true, false, false);//Second Element Should be true to make it durable so that we don't lose our queue
     $channel->basic_qos(null,QOS_LIMIT,null);
     $channel->basic_consume('airtel_ishikistaa_retry', '', false, false, false, false, $callback); // auto ack is false
     

while (count($channel->callbacks)) {
    $channel->wait();
}
$channel->close();
$connection->close();
function  balanceCheck($msisdn,$amount,$totChargedAmount){
if($totChargedAmount >=90){//fallback should not go beyond 90
    
    return true;
    
} 
else{
        $mobile = substr($msisdn, 3);
        $dt = date("Y-m-d h:i:s");
        $isoTimestamp = date(DATE_ISO8601, strtotime($dt));
        $isoTime = str_replace("-", "", $isoTimestamp);
        $transactionID = str_replace("-", "", str_replace(":", "", str_replace(" ", "", $dt)));
   $xml_data = <<<EOD
<methodCall>
<methodName>GetBalanceAndDate</methodName>
<params>
<param>
<value>
<struct>
<member>
<name>originNodeType</name>
<value>
<string>EXT</string>
</value>
</member>
<member>
<name>originHostName</name>
<value>
<string>greentelecom</string>
</value>
</member>
<member>
<name>originTransactionID</name>
<value>
<string>$transactionID</string>
</value>
</member>
<member>
<name>originTimeStamp</name>
<value>
<dateTime.iso8601>$isoTime</dateTime.iso8601>
</value>
</member>
<member>
<name>subscriberNumber</name>
<value>
<string>$mobile</string>
</value>
</member>
<member>
<name>externalData1</name>
<value>
<string>Ishi_kistaa</string>
</value>
</member>
</struct>
</value>
</param>
</params>
</methodCall>
EOD
;

        $username = "greentelecom";
        $password = "green@123";
        $auth = base64_encode($username . ":" . $password);
        $headers = array(
            "Method:POST",
            "Content-Type: text/xml;charset=\"utf-8\"",
            "Content-length: " . strlen($xml_data),
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Accept:text/xml",
            "Content-Encoding:gzip,compress",
            "Authorization: Basic Z3JlZW50ZWxlY29tOmdyZWVuQDEyMw==",
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://10.87.72.35:10010/Air");
        curl_setopt($ch, CURLOPT_USERAGENT, "greentelecom/3.1/3.1");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 160);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data); // the SOAP request
        $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $resulResp = curl_exec($ch);
       if (!$resulResp) {
            $data = "ERROR";
                return FALSE;

        }
        else{
            $chargeAmount = "ERROR";
        $decoderesponse = xmlrpc_decode($resulResp);
        $balancestatusCode = $decoderesponse["responseCode"];
        $conn = connect_db();
        $sql = "UPDATE tbl_subscribers SET `BAL_ASSESS_CODE` = '".$balancestatusCode."' WHERE MSISDN = '".$msisdn."'";
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        if ($balancestatusCode == "0") {
            $data = "{STATUS_CODE:200,STATUS : SUCCESS,BALANCE_INFO:".$decoderesponse["accountValue1"]."}";
        if(!isset($decoderesponse["accountValue1"])){
            
            file_put_contents("Errorbal.txt",$data,FILE_APPEND);
            return false;
        }
            $balance = $decoderesponse["accountValue1"];
            
            if($balance >=99){
                if($totChargedAmount ==80||$totChargedAmount ==70){
                    
                    $chargeAmount = 10 ;
                }
                 if($totChargedAmount ==60||$totChargedAmount ==50||$totChargedAmount ==40){
                    
                    $chargeAmount = 30 ;
                }
                if($totChargedAmount ==30||$totChargedAmount ==20||$totChargedAmount ==10){
                    
                    $chargeAmount = 60 ;
                }
                if($totChargedAmount < 10){
                    
                    $chargeAmount = 99;
                }
       $sql = "INSERT INTO `balance_assessment_logs_".date("Ymd")."` (`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
       $conn = connect_db();
       mysqli_query($conn,$sql);
       mysqli_close($conn);
       processCharge($msisdn,$chargeAmount,$totChargedAmount);  
       return true;
            }
            
            if($balance >=60 && $balance < 99){
                if($totChargedAmount ==80||$totChargedAmount ==70){
                    $chargeAmount = 10 ;
                }
                 if($totChargedAmount ==60||$totChargedAmount ==50||$totChargedAmount ==40){
                    $chargeAmount = 30 ;
                }
                if($totChargedAmount ==30||$totChargedAmount ==20||$totChargedAmount ==10){
                    $chargeAmount = 60 ;
                }
                if($totChargedAmount < 10){
                    
                    $chargeAmount = 60;
                }
                 $sql = "INSERT INTO `balance_assessment_logs_".date("Ymd")."` (`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
            
       $conn = connect_db();
       mysqli_query($conn,$sql);
       mysqli_close($conn);
       processCharge($msisdn,$chargeAmount,$totChargedAmount);  
       return true;
            }
            
            if($balance >=30 && $balance < 60){
                if($totChargedAmount ==80||$totChargedAmount ==70){
                    $chargeAmount = 10 ;
                }
                 if($totChargedAmount ==60||$totChargedAmount ==50||$totChargedAmount ==40){
                    $chargeAmount = 30 ;
                }
                if($totChargedAmount ==30||$totChargedAmount ==20||$totChargedAmount ==10){
                    $chargeAmount = 10 ;
                }
                if($totChargedAmount < 10){
                    
                    $chargeAmount = 30;
                }
                 $sql = "INSERT INTO `balance_assessment_logs_".date("Ymd")."` (`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
            
       $conn = connect_db();
       mysqli_query($conn,$sql);
       mysqli_close($conn);
       processCharge($msisdn,$chargeAmount,$totChargedAmount);  
       return true;
            }
            if($balance >=10 && $balance < 30){   
               $chargeAmount = 10;
               if($totChargedAmount < 10){
                    
                    $chargeAmount = 10;
                }
                 $sql = "INSERT INTO `balance_assessment_logs_".date("Ymd")."` (`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
            
       $conn = connect_db();
       mysqli_query($conn,$sql);
       mysqli_close($conn);
       processCharge($msisdn,$chargeAmount,$totChargedAmount);  
       return true;
            }
           if($balance < 10 ){
               
               $chargeAmount = 10;
                if($totChargedAmount < 10){
                    
                    $chargeAmount = 10;
                }
                 $sql = "INSERT INTO `balance_assessment_logs_".date("Ymd")."` (`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
            
       $conn = connect_db();
       mysqli_query($conn,$sql);
       mysqli_close($conn);
       processCharge($msisdn,$chargeAmount,$totChargedAmount);  
       return true;
            }
           
           
        } else {
                $data = "{STATUS_CODE :400, STATUS : FAILED,BALANCE_INFO :0}";
                $balance = 0;
                $sql = "INSERT INTO balance_assessment_logs_".date("Ymd")."(`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
                $conn = connect_db();
                mysqli_query($conn,$sql);
                mysqli_close($conn);
                return true;
        }
        
        return true;

}
}
}


function processCharge($msisdn,$amount,$totChargedAmount){
    $mobile = substr($msisdn, 3);
        $dt = date("Y-m-d h:i:s");
        $isoTimestamp = date(DATE_ISO8601, strtotime($dt));
        $isoTime = str_replace("-", "", $isoTimestamp);  
        $txn = microtime();
        $txn = str_replace("0.","",$txn);
        $transactionID = str_replace(" ","",$txn);
       $xml_data = <<<EOD
<methodCall>
<methodName>UpdateBalanceAndDate</methodName>
<params>
<param>
<value>
<struct>
<member>
<name>originNodeType</name>
<value>
<string>EXT</string>
</value>
</member>
<member>
<name>originHostName</name>
<value>
<string>greentelecom</string>
</value>
</member>
<member>
<name>originTransactionID</name>
<value>
<string>$transactionID</string>
</value>
</member>
<member>
<name>originTimeStamp</name>
<value>
<dateTime.iso8601>$isoTime</dateTime.iso8601>
</value>
</member>
<member>
<name>subscriberNumber</name>
<value>
<string>$mobile</string>
</value>
</member>
<member>
<name>transactionCurrency</name>
<value>TZS</value>
</member>
<member>
<name>adjustmentAmountRelative</name>
<value>
<string>-$amount</string>
</value>
</member>
<member>
<name>externalData1</name>
<value>
<string>Ishi_kistaa</string>
</value>
</member>
</struct>
</value>
</param>
</params>
</methodCall>
EOD
        ;
        $username = "greentelecom";
        $password = "green@123";
        $auth = base64_encode($username . ":" . $password);
        $headers = array(
            "Method:POST",
            "Content-Type: text/xml;charset=\"utf-8\"",
            "Content-length: " . strlen($xml_data),
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Accept:text/xml",
            "Content-Encoding:gzip,compress",
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://10.87.72.35:10010/Air"); 
        curl_setopt($ch, CURLOPT_USERAGENT, "greentelecom/3.1/3.1");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 160);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data); // the SOAP request
	    $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $data = curl_exec($ch);

        if (!$data) {
            
            return false;
        } 
        else{
        $conn = connect_db();    
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoderesponse = xmlrpc_decode($data);
        if(!$decoderesponse){
               $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','ERROR OCCURED:".$data."')"; 
        mysqli_query($conn,$sql);
            
            return false;
        }
        $response = $data;
        if(!isset($decoderesponse["responseCode"])){
            
            return false;
        }
        $status = $decoderesponse["responseCode"];
       
        if ($status == "0") {
            $fullamount = $totChargedAmount+$amount;
             $chargingStatus = "SUCCESS";
             if($amount == 99||$fullamount >=90){
                 $nextChargeAmount = 0;
                 $totAmount        = $fullamount;
                 $retry            = 1;
                 $queueStatus      = 0;
                 $last_charging    = date("Y-m-d H:i:s");
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
             }
             if($amount == 60){
                 if($fullamount>=90){
                     $nextChargeAmount = 0;
                     $totAmount        = $fullamount;
                      $retry            = 1;
                      $queueStatus      = 0;
                 }
                 if($fullamount==80||$fullamount==70){
                     $nextChargeAmount = 10;
                     $totAmount        = $fullamount;
                      $retry            = 0;
                      $queueStatus      = 1;
                 }
                 if($fullamount==60||$fullamount==50||$fullamount==40){
                     $nextChargeAmount = 30;
                     $totAmount        = $fullamount;
                      $retry            = 0;
                      $queueStatus      = 1;
                     
                 }
                 if($fullamount==30||$fullamount==20){
                     $nextChargeAmount = 10;
                     $totAmount        = $fullamount;
                      $retry            = 0;
                      $queueStatus      = 1;
                 }
                 if($fullamount==10){
                     $nextChargeAmount = 60;
                     $totAmount        = $fullamount;
                      $retry            = 0;
                      $queueStatus      = 1;
                 }
                 
                 $totAmount = $fullamount;
                 $lastChargingAmount  = $amount;
                 $last_charging    = date("Y-m-d H:i:s");
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
             }
             if($amount == 30){
                 if($fullamount>=90){
                     $nextChargeAmount = 0;
                     $totAmount        = $fullamount;
                     $retry = 1;
                     $lastChargingAmount = $amount;
                 $totAmount        = $fullamount;
                 $last_charging    = date("Y-m-d H:i:s"); 
                 $queueStatus      = 1;
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
                 }
                 if($fullamount==80||$fullamount==70){
                     $nextChargeAmount = 10;
                     $totAmount        = $fullamount;
                     $retry = 0;
                     $queueStatus      = 1;
                     $lastChargingAmount = $amount;
                 $totAmount        = $fullamount;
                 $last_charging    = date("Y-m-d H:i:s"); 
                 $queueStatus      = 1;
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
                 }
                 if($fullamount==60||$fullamount==50||$fullamount==40){
                     $nextChargeAmount = 30;
                     $retry = 0;
                     $queueStatus      = 1;
                 }
                 if($fullamount==30||$fullamount==20||$fullamount==10){
                     $nextChargeAmount = 60;
                     $totAmount        = $fullamount;
                     $retry = 0;
                     $queueStatus      = 1;
                     $lastChargingAmount = $amount;
                 $totAmount        = $fullamount;
                 $last_charging    = date("Y-m-d H:i:s"); 
                 $queueStatus      = 1;
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
                 }
                 
             }
             
             if($amount == 10){
                 
                 if($fullamount>=90){
                     $nextChargeAmount = 0;
                     $totAmount        = $fullamount;
                     $retry = 1;
                     $queueStatus      = 0;
                     $lastChargingAmount = $amount;
                 $totAmount        = $fullamount;
                 $last_charging    = date("Y-m-d H:i:s"); 
                 $queueStatus      = 1;
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
                 }
                 if($fullamount==80||$fullamount==70){
                     $nextChargeAmount = 10;
                     $totAmount        = $fullamount;
                     $retry = 0;
                     $queueStatus      = 1;
                     $lastChargingAmount = $amount;
                 $totAmount        = $fullamount;
                 $last_charging    = date("Y-m-d H:i:s"); 
                 $queueStatus      = 1;
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."' ,RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
                 }
                 if($fullamount==60||$fullamount==50||$fullamount==40){
                     $nextChargeAmount = 30;
                     $totAmount        = $fullamount;
                     $retry = 0;
                     $queueStatus      = 1;
                     $lastChargingAmount = $amount;
                 $totAmount        = $fullamount;
                 $last_charging    = date("Y-m-d H:i:s"); 
                 $queueStatus      = 1;
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
                 }
                 if($fullamount==30||$fullamount==20||$fullamount==10){
                     $nextChargeAmount = 60;
                     $retry = 0;
                     $queueStatus      = 1;
                 }
                 $totAmount        = $fullamount;
                 $last_charging    = date("Y-m-d H:i:s");
                 $lastChargingAmount  = $amount;
                  $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.",QUEUE_STATUS = '".$queueStatus."', RETRY_STATUS = '".$retry."' , RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
         $result = mysqli_query($conn,$sql);        
         
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
             }
        return true;
        } else { 
            $chargingStatus = "FAILED";
            if($amount == 99){
                 $nextChargeAmount = 60;
                 $totAmount        = $totChargedAmount;
                 $retry            = 0;
                 $queueStatus      = 1;
}
             if($amount == 60){
                 $nextChargeAmount = 30;
                 $totAmount        = $totChargedAmount;
                 $retry            = 0;
                 $queueStatus      = 1;
             }
             if($amount == 30){
                 $nextChargeAmount = 10;
                 $totAmount        = $totChargedAmount;
                 $retry            = 0;
                 $queueStatus      = 1;
             }
             if($amount == 10){
                 $nextChargeAmount = 10;
                 $totAmount        = $totChargedAmount;
                 $retry            = 0;
                 $queueStatus      = 1;
             }
            $sql = "UPDATE tbl_subscribers SET  TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.", RETRY_STATUS = '".$retry."',QUEUE_STATUS = '".$queueStatus."',RETRY_COUNT = CASE WHEN RETRY_COUNT IN (NULL,0,'') THEN 1 ELSE  RETRY_COUNT+1 END WHERE MSISDN = '".$msisdn."'";
            mysqli_query($conn,$sql);        
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
            
        }
        
    }
}
function connect_db(){
    $conn = mysqli_connect(DB_HOST,DB_CONSUMER_USER,DB_CONSUMER_PASSWORD,DB);
    if($conn){       
        return $conn;
    }
    else{
        return false;
    }
    
}
