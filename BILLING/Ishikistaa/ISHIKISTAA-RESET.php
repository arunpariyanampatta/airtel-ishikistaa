<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$conn = connect_db();
if(!$conn){
    exit;
}   
    

$sql = "UPDATE tbl_subscribers SET QUEUE_STATUS = '1',RETRY_STATUS = '1',TOTAL_AMOUNT_CHARGED = 0,NEXT_AMOUNT_CHARGE = 0, RETRY_COUNT = '0',SUB_CHARGING_STATUS ='1'";

mysqli_query($conn,$sql);
function connect_db(){
   $conn = mysqli_connect("localhost","ishikistaa_reset","Cu9Px2LASxsvsDNV@@@","ishikistaa_airtel_vas"); 
   if($conn){
       
       return $conn;
   } 
   else{
       
       return false;
   }
    
    
}
