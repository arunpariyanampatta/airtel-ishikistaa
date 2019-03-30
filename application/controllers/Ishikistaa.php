<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
defined('BASEPATH') OR exit('No direct script access allowed');
require_once  './vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Ishikistaa extends CI_Controller{
       
   public  function __construct() {
        parent::__construct();
        $this->load->model('IshikistaaServices');
        $this->lang->load('sms', 'kiswahili');
    }
    function index(){
        
        
    }
    function ivr_unsub_manage(){
           $sms = $this->lang->line('UNSUB');
            $msg = urlencode($sms);
            $phone = $msisdn;
            $smsArray = array("MSISDN"=>$phone,"TEXT"=>$sms,"TEXT_TYPE"=>"UNSUB");
            file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$phone&text=$msg&from=15670&dlr-mask=31");
            $this->IshikistaaServices->_recordData("sms_outgoing_logs",$smsArray);
           $this->IshikistaaServices->_deleteSubscriber($msisdn);   
           echo"RECEIVED";
        
    }
    function ivr_subscribe_manage(){
       $msisdn = $this->input->get('msisdn');
        $isExist = $this->IshikistaaServices->checkSubscription($msisdn); 
           $result = $this->IshikistaaServices->checkUnsubChannel($msisdn);
            if(empty($result)){
           $data['MSISDN'] = $msisdn;
           $data['SUB_CHANNEL'] = 
           $msg = $this->lang->line('WELCOME');
           $msg = urlencode($msg);
           file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$msisdn&text=$msg&from=15670&dlr-mask=31");
          $this->IshikistaaServices->_recordData("tbl_subscribers",$data);
          $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
          $channel = $connection->channel();	
          $channel->queue_declare('airtel_ishikistaa_fullbase', false, true, false, false);
          $message = array("msisdn"=>$msisdn,"charging_type"=>"0","amount"=>99);
          $msg = new AMQPMessage(json_encode($message));
          $channel->basic_publish($msg, '', 'airtel_ishikistaa_fullbase');
          $channel->close();
          $connection->close();
          echo"RECEIVED";
        }
        else{  
          $sms = $this->lang->line('SMS_RESTRICTED');
	   $msg = urlencode($sms);
          $smsArray = array("MSISDN"=>$msisdn,"TEXT"=>$sms,"TEXT_TYPE"=>"MSISDN_RESTRICTED");
          file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$msisdn&text=$msg&from=15670&dlr-mask=31");
          $this->IshikistaaServices->_recordData("sms_outgoing_logs",$smsArray);
        }
        }
        
     
    
    function subscribe(){
        $postData = file_get_contents("php://input");
        $postArray = json_decode($postData,TRUE);
        $msisdn = $postArray['msisdn'];
        $isExist = $this->IshikistaaServices->checkSubscription($msisdn);
       
        if(!empty($isExist)){ 
//            $sms = $this->lang->line('MSISDN_EXIST');
//            $msg = urlencode($sms);
//            $phone = $msisdn;
//            $smsArray = array("MSISDN"=>$phone,"TEXT"=>$sms,"TEXT_TYPE"=>"MSISDN_EXIST");
//            file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$phone&text=$msg&from=15670&dlr-mask=31");
//            $this->IshikistaaServices->_recordData("sms_outgoing_logs",$smsArray);
//            exit;
        }
        else{
           $result = $this->IshikistaaServices->checkUnsubChannel($msisdn);
            if(empty($result)){
           $data['MSISDN'] = $msisdn;
           $msg = $this->lang->line('WELCOME');
           $msg = urlencode($msg);
           file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$msisdn&text=$msg&from=15670&dlr-mask=31");
          $this->IshikistaaServices->_recordData("tbl_subscribers",$data);
          $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
          $channel = $connection->channel();	
          $channel->queue_declare('airtel_ishikistaa_fullbase', false, true, false, false);
          $message = array("msisdn"=>$msisdn,"charging_type"=>"0","amount"=>99);
          $msg = new AMQPMessage(json_encode($message));
          $channel->basic_publish($msg, '', 'airtel_ishikistaa_fullbase');
          $channel->close();
          $connection->close();
        }
        else{  
          $sms = $this->lang->line('SMS_RESTRICTED');
	   $msg = urlencode($sms);
          $smsArray = array("MSISDN"=>$msisdn,"TEXT"=>$sms,"TEXT_TYPE"=>"MSISDN_RESTRICTED");
          file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$msisdn&text=$msg&from=15670&dlr-mask=31");
          $this->IshikistaaServices->_recordData("sms_outgoing_logs",$smsArray);
        }
        }
    }
            

function unsubscribe(){
    
    $postData = file_get_contents("php://input");
        $postArray = json_decode($postData,TRUE);
        $msisdn = $postArray['msisdn'];
        $isExist = $this->IshikistaaServices->checkSubscription($msisdn);
        
        if(empty($isExist)){//unsub without subscription
        
            return true;
        }
        else{
            $sms = $this->lang->line('UNSUB');
            $msg = urlencode($sms);
            $phone = $msisdn;
            $smsArray = array("MSISDN"=>$phone,"TEXT"=>$sms,"TEXT_TYPE"=>"UNSUB");
            file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=airtelTX&password=greentx&to=$phone&text=$msg&from=15670&dlr-mask=31");
            $this->IshikistaaServices->_recordData("sms_outgoing_logs",$smsArray);
           $this->IshikistaaServices->_deleteSubscriber($msisdn);
            
        }
    
}
function airtelCharging($msisdn) {

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
<string>$msisdn</string>
</value>
</member>
<member>
<name>transactionCurrency</name>
<value>TZS</value>
</member>
<member>
<name>adjustmentAmountRelative</name>
<value>
<string>$amount</string>
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
            return $data;
        }
        else{
        $response = $data;
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoderesponse = xmlrpc_decode($data);
        $status = $decoderesponse["responseCode"];
        if ($status == "0") {
            $data = "SUCCESS";
             $file = "SUCCESS"."-".date("Ymd").".txt";
        } else {
                $data = "{status : FAILED,responsecode:".$status."}";
        }
        $link = mysqli_connect('localhost','airtel_reqrep','Lm9gnavkA6d6BUP7@','vas');
        $sql = "INSERT INTO airtel_response(`TRANSACTION_ID`,`MSISDN`,`AMOUNT`,`STATUS_CODE`,`RESPONSE`) VALUES ('".$transactionID."','".$msisdn."','".$amount."','".$status."','".$response."')";
        mysqli_query($link,$sql);
        return $data;
}
    }



   function get_string_between($string, $start, $end) {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0)
            return '';
            $ini += strlen($start);
            $len = strpos($string, $end, $ini) - $ini;
            return substr($string, $ini, $len);
    }

}
