<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
class Hotelmodule_enquiries extends Hotelmodule_global{

  function __construct(){   
    parent::__construct();
    $this->EE->load->model('hmodel_hotelrooms');
    $this->EE->load->model('hmodel_hotelmeals');
    $this->EE->load->model('hmodel_enquiries');
    $this->EE->load->model('hmodel_countries');
    
  }
  
  function enquiries() {
    $this->_permissions_check();
    
    $this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('hotelmodule_enquiries'));
    $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule', $this->EE->lang->line('hotelmodule_module_name'));
  
    $this->EE->cp->add_js_script(array(
      'ui'    => array('datepicker')
    ));
    $this->EE->javascript->compile();   
    
    $filter = array();
    $vars = Array();    
    
    $vars['title'] = $this->EE->lang->line('hotelmodule_hotel_offers');
    $vars['default_language'] = $this->default_language;
    
    $vars['filter_name'] = array();
    if($this->EE->input->post('filter_name'))  {
      $vars['filter_name'] = $this->EE->input->post('filter_name');
      $vars['oper'] = $this->EE->input->post('oper');
      foreach($vars['filter_name'] as $k=>$filter_name) {
        $post_val = $this->EE->input->post($filter_name);
        $filter_values[$k] = json_encode($post_val[$k]);
        $o = "";
        if(isset($vars['oper'][$k])) $o = $vars['oper'][$k];
        $filter[$k][$filter_name] = array($o,$post_val[$k]);
      }
      $vars['filter_value'] = $filter_values;
    }    
  
    $pagination['page'] = 1;
    if($this->EE->input->get('page') && $this->EE->input->get('page')>=1) $pagination['page'] = $this->EE->input->get('page');
    $pagination['perpage'] = 12;
    $pagination['from'] = ($pagination['page']-1)*$pagination['perpage'];  
    $vars['enquiries'] = $this->EE->hmodel_enquiries->list_enquiries($filter,$pagination);

    $total_rows = $vars['enquiries']['totalresults'];
    if($pagination['from'] > $total_rows-1) {
      $pagination['page'] = 1;
    }
    $total_pages = ceil($total_rows/$pagination['perpage']);
  
    $pagination['totalpages'] = $total_pages;
    $vars['pagination'] = $pagination;
    
    $vars['message'] = $this->EE->session->flashdata('message');
    
    return $this->EE->load->view('enquiries', $vars, TRUE);    
  }
  
  function ajax_show_enquiries_filter() {
    $filter_name = $this->EE->input->post('filter_name');
    $filter_value = $this->EE->input->post('filter_value');
    $index = $this->EE->input->post('index');
    $this->EE->load->library('hotelmodule_libenquiries');
    if(method_exists('hotelmodule_libenquiries',$filter_name)) {
      $filter_html = $this->EE->hotelmodule_libenquiries->$filter_name($filter_value,$index);
      echo $filter_html;
    } else {
      echo "ERROR";
    }
    die();
  }
  
  function edit_enquiry() {
    $this->_permissions_check();
    return $this->edit_enquiry_customer();    
  }
  
  function edit_enquiry_customer() {
    $this->_permissions_check();
    
    $enquiry_id = $this->EE->input->get('enquiryID');
    
    $existing = $this->EE->hmodel_enquiries->fetch_enquiries(array("where"=>array("enquiry_id"=>$enquiry_id)),"element");
    if(!$existing) {
      $this->set_flash_message("enquiryID Not Exists");     
      $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule'.AMP.'method=enquiries');   
      die(); 	
    }    
           
    $this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('hotelmodule_enquiry'));
    $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule', $this->EE->lang->line('hotelmodule_module_name'));
    
    $this->EE->cp->add_js_script(array(
      'ui'    => array('datepicker')
    ));  
    
    $this->EE->javascript->compile();      
            
    $vars = Array(); 
    $vars['channels'] = $this->EE->hmodel_global->enum_select("hotelmodule_pricecatalogs","channel");
    $vars['countries'] = $this->EE->hmodel_countries->fetch_countries(array("order"=>array("name"=>"ASC")));
    
    $vars['title'] = $this->EE->lang->line('hotelmodule_enquiry')." / ".$this->EE->lang->line('hotelmodule_enquiry_customer');
    if($enquiry_id!=0) $vars['title'] .= " / Edit"; else $vars['title'] .= " / Add New";
    $vars['selected_menu'] = "enquiriescustomer";
    $vars['get_params'] = array("enquiryID"=>$enquiry_id);
    $vars['default_language'] = $this->default_language;
        
    $data = array();        
    $data['enquiry_id'] = $enquiry_id;   
    $vars['data'] = $existing;
    $vars['data']['requestdate'] = date('Y-m-d', strtotime($existing['requestdate']));
    $vars['data']['requesttime'] = date('H:i', strtotime($existing['requestdate']));
    
    $vars['message'] = $this->EE->session->flashdata('message');
 
    if($this->EE->input->post('action')){
      switch($this->EE->input->post('action')) {
        case "save" :
        
          $this->EE->hotelmodule_validation->set_rules('firstname','firstname','trim|required');
          $this->EE->hotelmodule_validation->set_rules('lastname','lastname','trim|required');
          $this->EE->hotelmodule_validation->set_rules('email','email','trim|required|valid_email');
          $this->EE->hotelmodule_validation->set_rules('telephone','telephone','trim');
          $this->EE->hotelmodule_validation->set_rules('mobile','mobile','trim');
          $this->EE->hotelmodule_validation->set_rules('fax','fax','trim');
          $this->EE->hotelmodule_validation->set_rules('vat','vat','trim');   
          $this->EE->hotelmodule_validation->set_rules('address','address','trim');
          $this->EE->hotelmodule_validation->set_rules('zip','zip','trim');
          $this->EE->hotelmodule_validation->set_rules('city','city','trim');
          $this->EE->hotelmodule_validation->set_rules('state','state','trim');
          $this->EE->hotelmodule_validation->set_rules('country','country','trim');
          
          if($this->EE->input->post('member')==0) {            
            $this->EE->hotelmodule_validation->set_rules('firstname','firstname','trim|required|valid_screen_name[new]');
            $this->EE->hotelmodule_validation->set_rules('lastname','lastname','trim|required|valid_screen_name[new]');
          
            $this->EE->hotelmodule_validation->set_rules('email','email','trim|required|valid_user_email[new]');
            
            $random_password = $this->EE->hotelmodule_library->get_random_password();
            $_POST['password'] = $random_password;

            $this->EE->hotelmodule_validation->set_rules('password','password','trim|required|valid_password[firstname]');
          }     
          
          if($this->EE->hotelmodule_validation->run() === FALSE) {
          	$vars['message'] = $this->create_message($this->EE->hotelmodule_validation->error_string());
            return $this->EE->load->view('edit_enquiry_customer', $vars, TRUE);
          }      
          
          try {
            // BEGIN UPDATE ENQUIRY CUSTOMER TRANSACTION
            $this->EE->db->trans_start();
                        
            // Create System Member
            if($this->EE->input->post('member')==0) {            
              $member_data = array();                    
              $member_data['group_id'] = $this->member_group['group_id'];          
              $member_data['screen_name'] = $this->EE->input->post('firstname')." ".$this->EE->input->post('lastname');
              $member_data['username']   = $this->EE->input->post('email');
              $member_data['password']  = do_hash($random_password);
              $member_data['email']    = $this->EE->input->post('email');
              $member_data['ip_address']  = $this->EE->input->ip_address();
              $member_data['unique_id']  = random_string('encrypt');
              $member_data['join_date']  = $this->EE->localize->now;
              $member_data['language']   = $this->EE->config->item('deft_lang');
              $member_data['timezone']   = ($this->EE->config->item('default_site_timezone') && $this->EE->config->item('default_site_timezone') != '') ? $this->EE->config->item('default_site_timezone') : $this->EE->config->item('server_timezone');
              $member_data['daylight_savings'] = ($this->EE->config->item('default_site_dst') && $this->EE->config->item('default_site_dst') != '') ? $this->EE->config->item('default_site_dst') : $this->EE->config->item('daylight_savings');
              $member_data['time_format'] = ($this->EE->config->item('time_format') && $this->EE->config->item('time_format') != '') ? $this->EE->config->item('time_format') : 'us';          
              $member_id = $this->EE->member_model->create_member($member_data);
            } else {         
              $member_id = $this->EE->input->post('member');
            }
    
            // Update hotelmodule member and connected with system member
            $hotelmodule_member_data = array();
            $hotelmodule_member_data['customer_id'] = $vars['data']['customer_id'];
            $hotelmodule_member_data['member_id'] = $member_id;
            $hotelmodule_member_data['prefix'] = $this->EE->input->post('prefix');
            $hotelmodule_member_data['firstname'] = $this->EE->input->post('firstname');
            $hotelmodule_member_data['lastname'] = $this->EE->input->post('lastname');
            $hotelmodule_member_data['email'] = $this->EE->input->post('email');
            $hotelmodule_member_data['telephone'] = $this->EE->input->post('telephone');
            $hotelmodule_member_data['mobile'] = $this->EE->input->post('mobile');
            $hotelmodule_member_data['fax'] = $this->EE->input->post('fax');
            $hotelmodule_member_data['vat'] = $this->EE->input->post('vat');  
		        $hotelmodule_member_data['address'] = $this->EE->input->post('address');
		        $hotelmodule_member_data['zip'] = $this->EE->input->post('zip');
		        $hotelmodule_member_data['city'] = $this->EE->input->post('city');
		        $hotelmodule_member_data['state'] = $this->EE->input->post('state');
		        $hotelmodule_member_data['country'] = $this->EE->input->post('country');
            //$existing_member = $this->EE->hmodel_members->fetch_members(array("where"=>array("member_id"=>$member_id)),"element");
           if($this->EE->input->post('member')==0) $this->EE->hmodel_members->save_member($hotelmodule_member_data);
            
            // Update Enquiry Lead Customer 
            $customer_id = $this->EE->hmodel_customers->save_customer($hotelmodule_member_data);            
                        
            // Update Enquiry
            $enquiry_data = array();
            $enquiry_data['enquiry_id'] = $enquiry_id;
            $enquiry_data['customer_id'] = $customer_id;
            $enquiry_data['member_id'] = $member_id;
            $enquiry_data['requestdate'] = date('Y-m-d H:i:s', strtotime($this->EE->input->post('requestdate')." ".$this->EE->input->post('requesttime')));
            $enquiry_data['channel'] = $this->EE->input->post('channel');
            $enquiry_id = $this->EE->hmodel_enquiries->save_enquiry($enquiry_data);
            
            //END INSERT ENQUIRY TRANSACTION
            $this->EE->db->trans_complete();
          }  catch (Exception $e) {
            $vars['message'] = $this->create_message($e->getMessage());
            return $this->EE->load->view('edit_enquiry_customer', $vars, TRUE);
          }    
          
          $this->set_flash_message("Success","success");
          $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule'.AMP.'method=edit_enquiry_customer'.AMP.'enquiryID='.$enquiry_id);
        break;    
      }
    }
    
    return $this->EE->load->view('edit_enquiry_customer', $vars, TRUE);
  }
 
	function list_enquiry_services() {
	  $this->_permissions_check();
	   
    $this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('hotelmodule_enquiry'));
    $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule', $this->EE->lang->line('hotelmodule_module_name'));
    
    $enquiry_id = $this->EE->input->get('enquiryID');   
   
    $vars = Array();
    try {
      $vars['title'] = $this->EE->lang->line('hotelmodule_enquiry')." / ".$this->EE->lang->line('hotelmodule_enquiry_services'); 
      $vars['selected_menu'] = "enquiriesservices";
      $vars['get_params'] = array("enquiryID"=>$enquiry_id);
      $vars['default_language'] = $this->default_language;

      $vars['enquiry'] = $this->EE->hmodel_enquiries->fetch_enquiries(array("where"=>array("enquiry_id"=>$enquiry_id)),"element");
      if(!$vars['enquiry']) throw new Exception("Enquiry not Exists");
    } catch (Exception $e) {
      $this->set_flash_message($e->getMessage());
      $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule'.AMP.'method=enquiries');
      die(); 
    }
    
    $vars['message'] = $this->EE->session->flashdata('message');
    
    return $this->EE->load->view('list_enquiry_services', $vars, TRUE);     
  }
  
  function edit_hotelenquiry() {
    $this->_permissions_check();
    
    $this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('hotelmodule_enquiry'));
    $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule', $this->EE->lang->line('hotelmodule_module_name'));   
    $this->EE->cp->add_js_script(array(
      'ui'    => array('draggable', 'droppable','datepicker'),
      'file'    => array('cp/publish_tabs')
    ));      
    $this->EE->javascript->compile();
    
    $statuses = $this->EE->hmodel_global->enum_select('hotelmodule_hotelenquiries','status');
    $s = json_encode($statuses['entries']);    
    $s = str_replace("[","(",$s);
    $s = str_replace("]",")",$s);
    $this->EE->hotelmodule_validation->set_rules('status','Status',"trim|required|hm_in_set[{$s}]");
    $this->EE->hotelmodule_validation->set_rules('hotelname','Hotel Name',"trim|required");
    $this->EE->hotelmodule_validation->set_rules('entry_id','Hotel Id',"trim|required|integer");
    $this->EE->hotelmodule_validation->set_rules('roomname','Room Name',"trim|required");
    $this->EE->hotelmodule_validation->set_rules('room_id','Room Id',"trim|required|integer");
    $this->EE->hotelmodule_validation->set_rules('mealname','Meal Name',"trim|required");
    $this->EE->hotelmodule_validation->set_rules('mealname','Meal Name',"trim|required");
    $this->EE->hotelmodule_validation->set_rules('adults','Adults',"trim|required|integer");
    $this->EE->hotelmodule_validation->set_rules('children','Children',"trim|required|integer");
    $this->EE->hotelmodule_validation->set_rules('children','Children',"trim|required|integer");
    if($this->EE->input->post('children')) for($x=1;$x<$this->EE->input->post('children');$x++) $this->EE->hotelmodule_validation->set_rules("ages[$x]","Age $x","trim|required|integer");
    $this->EE->hotelmodule_validation->set_rules('checkin','Checkin',"trim|required|hm_valid_date[Y-m-d]");
    $this->EE->hotelmodule_validation->set_rules('checkout','Checkout',"trim|required|hm_valid_date[Y-m-d]|hm_valid_period[checkin]");
    $this->EE->hotelmodule_validation->set_rules('requirements','Requirements',"");
    $this->EE->hotelmodule_validation->set_rules('official','Official',"trim|required|numeric");
    $this->EE->hotelmodule_validation->set_rules('offer_official','Offer Official',"trim|required|numeric");
    $this->EE->hotelmodule_validation->set_rules('net','Net',"trim|required|numeric");
    $this->EE->hotelmodule_validation->set_rules('offer_net','Offer Net',"trim|required|numeric");
    $this->EE->hotelmodule_validation->set_rules('offer_title','Offer Title',"");
    $this->EE->hotelmodule_validation->set_rules('offer_description','Offer Description',"");
    $this->EE->hotelmodule_validation->set_rules('policies','Policies',"");
    
    $enquiry_id = $this->EE->input->get('enquiryID');
    $hotelenquiry_id = $this->EE->input->get('hotelenquiryID');
    if(!$hotelenquiry_id) $hotelenquiry_id=0;
            
    $vars = Array(); 
    
    $vars['statuses'] = $statuses;
    $vars['default_language'] = $this->default_language;
    $vars['title'] = $this->EE->lang->line('hotelmodule_enquiry')." / ".$this->EE->lang->line('hotelmodule_enquiry_services');
    if($hotelenquiry_id!=0) $vars['title'] .= " / Edit"; else $vars['title'] .= " / Add New";
    $vars['selected_menu'] = "enquiriesservices";
    $vars['get_params'] = array("enquiryID"=>$enquiry_id,"hotelenquiryID"=>$hotelenquiry_id);
        
    $data = Array();
    $data['enquiry_id'] = $enquiry_id;
    $data['hotelenquiry_id'] = $hotelenquiry_id;
    $data['status'] = "";
    $data['hotelname'] = "";
    $data['hotel'] = "";
    $data['hotel']['title'] = "";
    $data['entry_id'] = "";
    $data['info']['room_name'] = "";
    $data['info']['meal_name'] = "";
    $data['guestsinfo']['configuration']['adults'] = 1;
    $data['guestsinfo']['configuration']['children'] = 0;
    $data['checkin'] = "";
    $data['checkout'] = "";
    $data['requirements'] = "";
    $data['official'] = "";
    $data['offer_official'] = "";
    $data['net'] = "";
    $data['offer_net'] = "";
    
    
    $vars['rooms'] = array();
    $vars['meals'] = array();
    
    if($hotelenquiry_id!=0) {
      try {
        $data = $this->EE->hmodel_hotelenquiries->fetch_hotel_enquiries(array("where"=>array("enquiry_id"=>$enquiry_id,"hotelenquiry_id"=>$hotelenquiry_id)),"element");
        if(!$data) {
          $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule'.AMP.'method=list_enquiry_services'.AMP.'enquiryID='.$enquiry_id);
          die();
        }      
        $vars['rooms'] = $this->EE->hmodel_hotelrooms->fetch_rooms(array("where"=>array("entry_id"=>$data['entry_id'])));
        $vars['meals'] = $this->EE->hmodel_hotelmeals->fetch_hotelmeals(array("where"=>array("entry_id"=>$data['entry_id'])));
      } catch (Exception $e) {
        echo $e->getMessage();
        die();
      }
    }
    
    $vars['data'] = $data;
    
    $vars['message'] = $this->EE->session->flashdata('message');
    
    if($this->EE->input->post('action')){
      switch($this->EE->input->post('action')) {
        case "save" :
          $data['entry_id'] = $this->EE->input->post('entry_id');  
          $vars['rooms'] = $this->EE->hmodel_hotelrooms->fetch_rooms(array("where"=>array("entry_id"=>$data['entry_id'])));
          $vars['meals'] = $this->EE->hmodel_hotelmeals->fetch_hotelmeals(array("where"=>array("entry_id"=>$data['entry_id'])));
                
          if($this->EE->hotelmodule_validation->run() === FALSE) {
            $vars['message'] = $this->create_message($this->EE->hotelmodule_validation->error_string());
            return $this->EE->load->view('edit_hotelenquiry', $vars, TRUE);
          }
          
          $data['status'] = $this->EE->input->post('status');
                  
          $data['room_id'] = $this->EE->input->post('room_id');
          $data['hotelname'] = $this->EE->input->post('hotelname');
                              
          $enquiryinfo = Array();
          $enquiryinfo['hotel_name'] = $this->EE->input->post('hotelname');
          $enquiryinfo['room_name'] = $this->EE->input->post('roomname');
          $enquiryinfo['meal_name'] = $this->EE->input->post('mealname');
          
          $enquiryinfo['policies'] = $this->EE->input->post('policies');
          
          $enquiryinfo['official_offer']['offer_id'] = 0;
          $enquiryinfo['official_offer']['title'] = $this->EE->input->post('offer_title');
          $enquiryinfo['official_offer']['description'] = $this->EE->input->post('offer_description');
          
          $data['info'] = json_encode($enquiryinfo);
          
          $guestinfo = array();
          $guestinfo['configuration'] = array();
          $guestinfo['configuration']['adults'] = $this->EE->input->post('adults');
          $guestinfo['configuration']['children'] = $this->EE->input->post('children');
          $guestinfo['configuration']['children_ages'] = array();
          if($guestinfo['configuration']['children']) $guestinfo['configuration']['children_ages'] = $this->EE->input->post('ages');
                        
          $data['guestsinfo'] = json_encode($guestinfo);
          
          $data['checkin'] = $this->EE->input->post('checkin');
          $data['checkout'] = $this->EE->input->post('checkout');
          $data['official'] = $this->EE->input->post('official');
          $data['net'] = $this->EE->input->post('net');
          $data['offer_official'] = $this->EE->input->post('offer_official');
          $data['offer_net'] = $this->EE->input->post('offer_net');
          
          $data['requirements'] = $this->EE->input->post('requirements');
          
          try {
            $data['hotelenquiry_id'] = $this->EE->hmodel_hotelenquiries->save_hotel_enquiry($data);
          } catch(Exception $e) {
            $vars['message'] = $this->create_message($e->getMessage());
            return $this->EE->load->view('edit_hotelenquiry', $vars, TRUE);            
          }          
          
          $message['success'][] = "Success";
          $vars['message'] = $this->EE->load->view('message',$message, TRUE);
          $this->set_flash_message("Success","success");
          $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=hotelmodule'.AMP.'method=edit_hotelenquiry'.AMP.'enquiryID='.$enquiry_id.AMP.'hotelenquiryID='.$data['hotelenquiry_id']);
          die();
        break;    
      }
    }
    
    return $this->EE->load->view('edit_hotelenquiry', $vars, TRUE);    
  }
 
  function ajax_load_hotel() {
    $entry_id = $this->EE->input->post('entryID');    
    $hotel = $this->EE->hmodel_global->fetch_hotel($entry_id);
    if(!$hotel) $result='';
    else $result = json_encode($hotel);
    echo $result;
    die();
  } 
  
  function ajax_room_select() {
    $entry_id = $this->EE->input->post('entryID'); 
    $rooms = $this->EE->hmodel_hotelrooms->fetch_rooms(array("where"=>array("entry_id"=>$entry_id)));
    echo "<select name='room_id' id='room_id'>";
    foreach($rooms as $room_id=>$room) {
       echo "<option value='".$this->EE->db->escape_like_str($room['languages'][$this->default_language['language_id']]['room_id'])."'>".$room['languages'][$this->default_language['language_id']]['name']."</option>";
    }
    echo "</select>";
    die();
  }
  
  function ajax_meal_select() {
    $entry_id = $this->EE->input->post('entryID'); 
    $meals = $this->EE->hmodel_hotelmeals->fetch_hotelmeals(array("where"=>array("entry_id"=>$entry_id)));
    echo "<select name='meal_id' id='meal_id'>";
    foreach($meals as $meal_id=>$meal) {
      echo "<option value='".$this->EE->db->escape_like_str($meal['meal']['languages'][$this->default_language['language_id']]['meal_id'])."'";
      echo ">".$meal['meal']['languages'][$this->default_language['language_id']]['name']."</option>";
    }
    echo "</select>";
    die();
  }
  
}