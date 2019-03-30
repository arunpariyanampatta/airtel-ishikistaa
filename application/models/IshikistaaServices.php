<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of IshikistaaServices
 *
 * @author DELL
 */
class IshikistaaServices extends CI_Model{

    function __construct() {
        parent::__construct();
    }
    
   function checkSubscription($msisdn){
    
    $result = $this->db->select('ID,MSISDN')->from('tbl_subscribers')->where('MSISDN',$msisdn)->get();
    return $result->row_array();
    
}    


function _deleteSubscriber($msisdn){
    $sql = "INSERT INTO tbl_unsubs(`MSISDN`,`LAST_CHARGING`) (SELECT `MSISDN`,`LAST_CHARGING` FROM  tbl_subscribers WHERE MSISDN = '".$msisdn."')" ;  
    $this->db->query($sql);
    $this->db->where('MSISDN',$msisdn);
    $this->db->delete('tbl_subscribers');
    
}

    
    function _recordData($table,$data){
        
        $this->db->insert($table,$data);
        $id = $this->db->insert_id();
        return $id;
    }
       
     
    function checkUnsubChannel($msisdn){

$result = $this->db->select('*')->from('web_audit_trial')->where('MSISDN',$msisdn)->where('UNSUB_CHANNEL','WEB')->get();

return $result->row_array();


} 
    
    
}
