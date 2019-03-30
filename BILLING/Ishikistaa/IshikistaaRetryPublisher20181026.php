<?php
set_time_limit(0);
ini_set('memory_limit', '-1');
date_default_timezone_set('Africa/Dar_Es_Salaam');
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 .php';*/
require_once  __DIR__ .'/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
require_once 'DbConfig.php';
$id = 0;
while(true){
$connection = new AMQPStreamConnection('localhost', 5672, 'airtel_manage', 'manage@CNu8Ut');
$channel = $connection->channel();
$sql = "SELECT ID,MSISDN,NEXT_AMOUNT_CHARGE,TOTAL_AMOUNT_CHARGED FROM tbl_subscribers WHERE ID > '".$id."' AND SUB_CHARGING_STATUS = '1' AND TOTAL_AMOUNT_CHARGED < 90  AND RETRY_STATUS = '0' AND QUEUE_STATUS = '1' AND RETRY_COUNT < 20     LIMIT 1000" ;

$conn  = connect_db();
if(!$conn){
    
    sleep(2);
    
}
$result = mysqli_query($conn,$sql);
if(!$result){
    
    sleep(2);
}
$broadcast = array();
while($row = mysqli_fetch_array($result)){
    $broadcast[] = $row;
}

 if(empty($broadcast)){
     $id = 0;
     mysqli_close($conn);
     sleep(5);
 }
 else{
     $channel->queue_declare('airtel_ishikistaa_retry', false, true, false, false);
     $max_id = end($broadcast);
     $id = $max_id['ID'];
     for($j=0;$j<sizeof($broadcast);$j++){
         $mobile     = $broadcast[$j]['MSISDN'];
         $sub_id     = $broadcast[$j]['ID'];
         $amount     = $broadcast[$j]['NEXT_AMOUNT_CHARGE'];
         $totCharged = $broadcast[$j]['TOTAL_AMOUNT_CHARGED'];
         $str = array("msisdn"=>$mobile,"next_charge_amount"=>$amount,"charging_type"=>"retry","tot_amount_charged"=>$totCharged);
         $msg = new AMQPMessage(json_encode($str));
         $channel->basic_publish($msg,'', 'airtel_ishikistaa_retry'); 
         $sql = "UPDATE tbl_subscribers SET QUEUE_STATUS = '0' WHERE ID = ".$sub_id;
         mysqli_query($conn,$sql);
     }
       
     
    mysqli_close($conn);  
 }
 
 sleep(10);
}
function connect_db(){
    
    $connect = mysqli_connect(DB_HOST,DB_PUBLISHER_USER,DB_PUBLISHER_PASSWORD,DB);
    if($connect){
        
        return $connect;
    }
    else{
        
        return FALSE;
    }
    
}
