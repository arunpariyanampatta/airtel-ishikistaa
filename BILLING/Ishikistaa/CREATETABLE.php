<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$conn = connect_db();
if($conn){
    
    $tbldate = date("Ymd", strtotime("tomorrow"));
    $sql = 'CREATE TABLE `balance_assessment_logs_'.$tbldate.'` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `MSISDN` bigint(20) NOT NULL,
  `BALANCE` int(11) NOT NULL,
  `STATUS_CODE` int(11) NOT NULL,
  `DATE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1';

mysqli_query($conn,$sql);



$sql = 'CREATE TABLE `vas_transaction_ishikistaa_'.$tbldate.'` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `MSISDN` bigint(20) NOT NULL,
  `STATUS` varchar(15) NOT NULL,
  `STATUS_CODE` int(11) DEFAULT NULL,
  `TXN_ID` varchar(50) NOT NULL,
  `TXN_AMOUNT` int(11) NOT NULL,
  `REQUEST_SENT` varchar(5000) NOT NULL,
  `RESPONSE_RECEIVED` varchar(5000) NOT NULL,
  `DATE` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `MSISDN` (`MSISDN`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1';
mysqli_query($conn, $sql);

mysqli_close($conn);
}
function connect_db(){
    
    $conn = mysqli_connect("localhost","create_table","XwUTXFmy5ayUQtzG@","ishikistaa_airtel_vas");
    
    if($conn){
        
        return $conn;
    }
    else{
        
        return false;
    }
}
