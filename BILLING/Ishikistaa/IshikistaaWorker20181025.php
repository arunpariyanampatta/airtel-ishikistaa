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
    echo $ex->getMessage();
}




echo " [*] Waiting for messages. To exit press CTRL+C \n";

$callback = function ($msg) {
    if(!json_decode($msg->body)){      
$msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'],true,true);
return false;        
}
else{
$message =  json_decode($msg->body,TRUE);
$mobile 	=  $message['msisdn'];
$subStatus = $message['charging_type'];
$amount = $message['amount'];

try{
$deliveryState = balanceCheck($mobile,$subStatus,$amount);
if(!$deliveryState){// check if the connection is ok  
    $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'],true,true);
}
else{
$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag'],true);
}
}
catch(Exception $ex){
    $msg->delivery_info['channel']->basic_nack($msg->delivery_info['delivery_tag'],true,true);
}
}
};

   
     $channel->queue_declare('airtel_ishikistaa_fullbase', false, true, false, false);//Second Element Should be true to make it durable so that we don't lose our queue
     $channel->basic_qos(null,QOS_LIMIT,null);
     
     $result = $channel->basic_consume('airtel_ishikistaa_fullbase', '', false, false, false, false, $callback); // auto ack is false
     

while (count($channel->callbacks)) {
    $channel->wait();
}


$channel->close();


$connection->close();

function  balanceCheck($msisdn,$subStatus,$amount){
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
            if(!xmlrpc_decode($resulResp)){
                return false;
            }
        $decoderesponse = xmlrpc_decode($resulResp);
        if(!isset($decoderesponse["responseCode"])){
            
            return false;
        }
        $balancestatusCode = $decoderesponse["responseCode"];
        $conn = connect_db();
        $sql = "UPDATE tbl_subscribers SET `BAL_ASSESS_CODE` = '".$balancestatusCode."' WHERE MSISDN = '".$msisdn."'";
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        if ($balancestatusCode == "0") {
            $data = "{STATUS_CODE:200,STATUS : SUCCESS,BALANCE_INFO:".$decoderesponse["accountValue1"]."}";
            $balance = $decoderesponse["accountValue1"];
            if($balance >=99){
                $chargeAmount = 99;
            }
            if($balance >=60 && $balance < 99){
                $chargeAmount = 60;
            }
            if($balance >=30 && $balance < 60){
                $chargeAmount = 30;   
            }
            if($balance >=10 && $balance < 30){   
                $chargeAmount = 10; 
            }
           if($balance < 10 ){
               if($subStatus =="0"){
                   
                   $sms = "Ndugu mteja, Hauna salio la kutosha kuweza kupokea dondoo za Ishi Kistaa";
                   $phone = $msisdn;
                   $msg  = urlencode($sms);
                   file_get_contents("http://192.168.168.2:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$phone&text=$msg&from=15670&dlr-mask=31");
                    $sql = "INSERT INTO `balance_assessment_logs_".date("Ymd")."` (`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
                    $sql2 = "INSERT INTO sms_outgoing_logs (`MSISDN`,`TEXT`,`TEXT_TYPE`) VALUES ('".$msisdn."','".$sms."','NO_BALANCE')";
       $conn = connect_db();
       mysqli_query($conn,$sql);
       mysqli_query($conn,$sql2);
       
        $sql = "UPDATE tbl_subscribers SET   TOTAL_AMOUNT_CHARGED = 0, NEXT_AMOUNT_CHARGE =  99, RETRY_STATUS = '0', SUB_CHARGING_STATUS = '1', QUEUE_STATUS = '1', RETRY_COUNT = 1  WHERE MSISDN = '".$msisdn."'";
       
        mysqli_query($conn,$sql);
        mysqli_close($conn);
       
       return true;
               }
               else{
               $chargeAmount = 10;
               }
            }
            $sql = "INSERT INTO `balance_assessment_logs_".date("Ymd")."` (`MSISDN`,`STATUS_CODE`,`BALANCE`) VALUES('".$msisdn."','".$balancestatusCode."','".$balance."')";        
       $conn = connect_db();
       mysqli_query($conn,$sql);
       mysqli_close($conn);
       processCharge($msisdn,$chargeAmount,$subStatus);  
       return true;
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


function processCharge($msisdn,$amount,$subStatus){
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
            $data = "ERROR";
            return false;
        }
        
        else{
        $conn = connect_db();    
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoderesponse = xmlrpc_decode($data);
        $response = $data;
        $status = $decoderesponse["responseCode"];
        if ($status == "0") {
            
             $chargingStatus = "SUCCESS";
             
             if($amount == 99){
                 $nextChargeAmount = 0;
                 $totAmount        = 99;
                 $retry            = 0;
                 $queueStatus = 0;
                 $last_charging    = date("Y-m-d H:i:s");
                 
             }
             if($amount == 60){
                 $nextChargeAmount = 30;
                 $totAmount        = 60;
                 $retry            = 0;
                 $queueStatus = 1;
                 $last_charging    = date("Y-m-d H:i:s");
             }
             if($amount == 30){
                 $nextChargeAmount = 10;
                 $totAmount        = 30;
                 $retry            = 0;
                 $queueStatus = 1;
                 $last_charging    = date("Y-m-d H:i:s");
                 
             }
             
             if($amount == 10){
                 $nextChargeAmount = 60;
                 $totAmount        = 10;
                 $retry            = 0;
                 $queueStatus = 1;
                 $last_charging    = date("Y-m-d H:i:s");
             }
             
           if($subStatus == "0"){
               $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.", RETRY_STATUS = '".$retry."',SUB_CHARGING_STATUS = '1'  WHERE MSISDN = '".$msisdn."'";
               mysqli_query($conn,$sql);
               $welcomeSms = "Asante kwa kujiunga na ISHI KISTAA. Furahia HABARI ZA WASANII WA BONGO NA USHINDE ZAWADI KIBAO! Utatozwa Tsh 99 kwa siku. Kujiondoa tuma neno ONDOA kwenda 15670";
               $sms = urlencode($welcomeSms);
               $sql = "INSERT INTO sms_outgoing_logs (`MSISDN`,`TEXT`,`TEXT_TYPE`) VALUES ('".$msisdn."','".$welcomeSms."','WELCOM_SMS_1')";
               mysqli_query($conn,$sql);
               file_get_contents("http://192.168.168.2:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$msisdn&text=$sms&from=15670&dlr-mask=31");
               $sms2 = 'Mteja, utapokea maswali 5 kwa wiki. Ukijibu kwa usahihi utakuwa mshindi na kupata nafasi ya kukutana na msanii wako. Na UTASHINDA zawadi KIBAO ikiwemo VOCHA';
               $sql = "INSERT INTO sms_outgoing_logs (`MSISDN`,`TEXT`,`TEXT_TYPE`) VALUES ('".$msisdn."','".$sms2."','WELCOM_SMS_2')";
               mysqli_query($conn,$sql);
               $sms = urlencode($sms2);
               file_get_contents("http://192.168.168.2:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$msisdn&text=$sms&from=15670&dlr-mask=31");
               return true;
               
           }
           else{
               $sql = "UPDATE tbl_subscribers SET LAST_CHARGING = '".$last_charging."', TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.", RETRY_STATUS = '".$retry."',QUEUE_STATUS = '".$queueStatus."'  WHERE MSISDN = '".$msisdn."'";
               mysqli_query($conn,$sql);
               $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
               return true;
           }
           
        } else {
            $chargingStatus = "FAILED";
            if($amount == 99){
                 $nextChargeAmount = 60;
                 $totAmount        = 0;
                 $retry            = 0;
                 $queueStatus = 1;
                 $last_charging    = "";
                 
             }
             if($amount == 60){
                 $nextChargeAmount = 30;
                 $totAmount        = 0;
                 $retry            = 0;
                 $last_charging    = "";
             }
             if($amount == 30){
                 $nextChargeAmount = 10;
                 $totAmount        = 0;
                 $retry            = 0;
                 $queueStatus = 1;
                 $last_charging    = "";
                 
             }
             
             if($amount == 10){
                 $nextChargeAmount = 99;
                 $totAmount        = 0;
                 $retry            = 0;
                 $queueStatus = 1;
                 $last_charging    = "";
             }
            $sql = "UPDATE tbl_subscribers SET   TOTAL_AMOUNT_CHARGED = ".$totAmount.", NEXT_AMOUNT_CHARGE =  ".$nextChargeAmount.", RETRY_STATUS = '".$retry."',QUEUE_STATUS = '1',RETRY_COUNT = '1'  WHERE MSISDN = '".$msisdn."'";
        }
        
        mysqli_query($conn,$sql);        
        $date = date("Ymd");
        $sql = "INSERT INTO `vas_transaction_ishikistaa_".$date."`(`MSISDN`,`STATUS`,`STATUS_CODE`,`TXN_ID`,`TXN_AMOUNT`,`REQUEST_SENT`,`RESPONSE_RECEIVED`) VALUES ('".$msisdn."','".$chargingStatus."','".$status."','".$transactionID."',".$amount.",'".$xml_data."','".$data."')"; 
        mysqli_query($conn,$sql);
        mysqli_close($conn);
        return true;
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