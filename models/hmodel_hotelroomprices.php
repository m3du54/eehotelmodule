<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Hmodel_hotelroomprices extends CI_Model {
  
  function __construct() {
    $this->db->db_debug   = FALSE;
    $this->load->model('hmodel_hotelmeals');
    $this->load->model('hmodel_hotelroomscenarios');
    $this->load->model('hmodel_pricecatalogs');
    $this->load->model('hmodel_hotelrooms');
    $this->load->model('hmodel_hoteloffers');
    $this->load->model('hmodel_hotelofferpolicies');
    $this->load->model('hmodel_hotelpolicies');
  }
    
  function _db_shema() {
    $return = array();
    $fields = $this->db->field_data('hotelmodule_roomprices');
    foreach ($fields as $field){
      $return[$field->name] = $field->default;
    }
    return $return;
  }
    
  function fetch_roomprices($data=false,$type="array",$cache="normal") {
    $hash = md5("fetch_roomprices".$this->hmodel_global->encode_params($data).$type);
    if($cache!="reset") if(isset($this->session->cache[$hash])) return $this->session->cache[$hash];
    
    $return = array();
    
    if(isset($data['select'])) $this->db->select(implode(",",$data['select']));
    else $this->db->select("*");
    
    $this->db->from('hotelmodule_roomprices');

    if(isset($data['where']) && $data['where']!==false) {
      $this->db->where($data['where']);
    }
        
    if(isset($data['order'])) {
    	foreach($data['order'] as $field=>$dir) {
    	 $this->db->order_by($field, $dir);
    	}
    }
    
    if(isset($data['limit'])) {
    	$this->db->limit($data['limit']['offset'],$data['limit']['start']);
    }
    
    $result = $this->db->get();
    if($result === false) throw new Exception($this->db->_error_message());  
    
    foreach($result->result_array() as $row){
      if($type == "id_list") {
        $return[] = $row[$data['select'][key($data['select'])]];
        continue;
      }           	
      $tmp = array();
      $tmp = $row;
      try {
      	if(isset($row['meal_id'])) {
      	  $params = array("where"=>array("hotelmeal_id"=>$row['meal_id']));
      	  if(isset($data['extras']['fetch_hotelmeals'])) $params = array_merge_recursive($params,$data['extras']['fetch_hotelmeals']);
      	  $tmp['hotelmeal'] = $this->hmodel_hotelmeals->fetch_hotelmeals($params,"element");
      	}

        if(isset($row['scenario_id'])) {
      	  $params = array("where"=>array("scenario_id"=>$row['scenario_id']));
      	  if(isset($data['extras']['fetch_scenarios'])) $params = array_merge_recursive($params,$data['extras']['fetch_scenarios']);
      	  $tmp['scenario'] = $this->hmodel_hotelroomscenarios->fetch_scenarios($params,"element");
      	}
      } catch (Exception $e) {
      	throw new Exception($e->getMessage());
      }            
            
      if($type=="array") $return[$row['pricecatalog_id']."_".$row['room_id']."_".$row['scenario_id']."_".$row['meal_id']."_".$row['year']] = $tmp;
      if($type=="element") $return = $tmp;
    } 
    
    $this->session->cache[$hash] = $return;
    return $return;   
  }
  
  function save_roomprices($data) {
  	$pricecatalog_id = false;
  	
    $days = array("Mon"=>1,"Tue"=>2,"Wed"=>4,"Thu"=>8,"Fri"=>16,"Sat"=>32,"Sun"=>64);
    
    foreach($data['new_prices'] as $key=>$price) { 
      foreach($data['periods'] as $k=>$period) {
        if($period['from_date'] =="" || $period['to_date'] =="") {
          throw new Exception("Please select your dates");
        }
      }
    }
    
    if(array_sum($data['days'])==0) throw new Exception("Please select weekdays");
    
    try {
  		$this->db->trans_start();    
        
      $md5key = md5($data['analysis']['analysis']);
      $analysis = $this->fetch_analysis(array('md5key'=>$md5key),"element");
      if($analysis) {
        $analysis_id = $analysis['priceanalysis_id'];
      } else {
        $fields = array('md5key'=>$md5key,'analysis' => $data['analysis']['analysis']);
        $sql = $this->db->insert_string("hotelmodule_priceanalysis", $fields);
        if($this->db->query($sql)===false) throw new Exception($this->db->_error_message());
        $analysis_id = $this->db->insert_id();        
      }
      
      $target_catalog = $data['pricecatalog_id'];
      if(isset($data['target_catalog']) && $data['target_catalog']!=-1) $target_catalog = $data['target_catalog'];
      
      $pricecatalog_id = $target_catalog;
      
      $insert_data = array();
      $delete_data = array();
      $prs = array();  
      
      $locked = null;
      if($data['lock']) $locked = 1;
      
      foreach($data['periods'] as $k=>$period) {   
        $day = strtotime($period['from_date']);
        $end = strtotime($period['to_date']);
        while($day <= $end) {
          if(in_array($days[date("D",$day)],$data['days'])) {    
            $year = date("Y",$day);
            $date = date("m-d",$day);  
            $existing_locked = $this->check_locked($target_catalog,$data['room_id'],$year."-".$date);        
            if(($data['unlock'] || $existing_locked==null) && isset($data['new_prices']['Prices'])) {
            	
              foreach($data['new_prices']['Prices'][$data['pricecatalog_id']]['Rooms'][$data['room_id']]['Scenarios'] as $scenario_id=>$scenario) {
                foreach($scenario['Meals'] as $meal_id=>$meal) {
                  $price = $meal['Analysis'];
                  if(isset($prs[$target_catalog."_".$data['room_id']."_".$scenario_id."_".$meal_id][$year][$date])) continue;
                  $prs[$target_catalog."_".$data['room_id']."_".$scenario_id."_".$meal_id][$year][$date] = $price[key($price)];
                }
              }
              
              $this->db->where(array('pricecatalog_id'=>$target_catalog,'room_id'=>$data['room_id'],'date'=>$year."-".$date));
              if($this->db->delete('hotelmodule_priceanalysis_to_date')===false) throw new Exception($this->db->_error_message());

              $insert_data[] = array('pricecatalog_id'=>$target_catalog,'room_id'=>$data['room_id'],'priceanalysis_id' => $analysis_id,'date'=>$year."-".$date,'hotelmeal_id'=> $data['base'],'locked'=>$locked);                  
              
              if(count($insert_data)%150==0) {
              	if($this->db->insert_batch("hotelmodule_priceanalysis_to_date", $insert_data)===false) throw new Exception($this->db->_error_message());
              	$insert_data = array();
              }
              
            }
          }
          $day = strtotime("+1 day", $day);
        }
      }
      
      if($insert_data) if($this->db->insert_batch("hotelmodule_priceanalysis_to_date", $insert_data)===false) throw new Exception($this->db->_error_message());

      foreach($prs as $scenario_meal=>$year_array) {
        $tmp = explode("_",$scenario_meal);
        foreach($year_array as $year=>$date_prices) {
          $exists = $this->fetch_roomprices(array("where"=>array("pricecatalog_id"=>$target_catalog,"room_id"=>$data['room_id'],"scenario_id"=>$tmp[2],"meal_id"=>$tmp[3],"year"=>$year)));
          if($exists) {
            $fields = array();
            foreach($date_prices as $date=>$price) {              
              if(!is_numeric($price)) $price = NULL;
              $fields[$date] = $price;
            }
            $where = array('pricecatalog_id' => $target_catalog,'room_id' => $data['room_id'], 'scenario_id' => $tmp[2], 'meal_id' => $tmp[3], 'year' => $year);
            $sql = $this->db->update_string("hotelmodule_roomprices", $fields, $where);  
            if($this->db->query($sql)===false) throw new Exception($this->db->_error_message());   
          } else {
            $fields = array('pricecatalog_id' => $target_catalog,'room_id' => $data['room_id'], 'scenario_id' => $tmp[2], 'meal_id' => $tmp[3], 'year' => $year);
            foreach($date_prices as $date=>$price) {
              if(!is_numeric($price)) $price = NULL;
              $fields[$date] = $price;
            }
            $sql = $this->db->insert_string("hotelmodule_roomprices", $fields);
            if($this->db->query($sql)===false) throw new Exception($this->db->_error_message());
          }
        }
      }
      
      $this->db->trans_complete();
      
    } catch(Exception $e) {
      throw new Exception($e->getMessage());
    }
            
    return $pricecatalog_id;      
  }
    
  function delete_roomprices() {}  
      
  function fetch_roomprices_period($from,$to,$params=array()) {
    $hash = md5("fetch_roomprices_period".$from.$to.$this->hmodel_global->encode_params($params));
    if(isset($this->session->cache[$hash])) return $this->session->cache[$hash];
    
    $return = array();
    
    $days = array("Mon"=>1,"Tue"=>2,"Wed"=>4,"Thu"=>8,"Fri"=>16,"Sat"=>32,"Sun"=>64);
    
    $from = strtotime($from);
    $to = strtotime($to);
    if($from<$to) $to = strtotime('-1 day',$to);
    
    $year_start = date("Y",$from);
    $year_end = date("Y",$to);
    
    //Query the pricetable  
    //$this->db->save_queries = true;
    $this->db->from('hotelmodule_roomprices as t1');      
    $this->db->select("rooms.entry_id as hotelId");
    $this->db->select("t1.pricecatalog_id");
    $this->db->select("t1.room_id");
    $this->db->select("t1.scenario_id");
    $this->db->select("t1.meal_id");
    $this->db->select("rooms.price_type as pt");
    
    $day = $from;
    while($day <= $to) {
      $year = (date("Y",$day) - $year_start) + 1;
      $date = date("m-d",$day);          
      $fdate = date("Y-m-d",$day);  
      $this->db->select('t'.$year.".".$date." as `".$fdate."`");        
      $day = strtotime("+1 day", $day);
    }
    
    $y=2;
    for($x=$year_start+1;$x<=$year_end;$x++) {
      if($year_start<$year_end) {
        $this->db->join("hotelmodule_roomprices as t".$y, "t1.pricecatalog_id = t".$y.".pricecatalog_id and t1.room_id = t".$y.".room_id and t1.scenario_id = t".$y.".scenario_id and t1.meal_id = t".$y.".meal_id and t1.year+".($y-1)."=t".$y.".year", 'left');  
        $y++;
      }
    }
    
    $room_join = "rooms.room_id = t1.room_id";
    
    if(isset($params['hotelId'])) $room_join .=" and rooms.entry_id =".$params['hotelId'];
    
    $this->db->join("hotelmodule_rooms as rooms", $room_join);
    
    if(isset($params['channel']) || isset($params['catalogType'])) {
    	$catalog_join = "";
    	if(isset($params['channel'])) $catalog_join .= " AND catalogs.channel = '".$params['channel']."' ";
    	if(isset($params['catalogType'])) $catalog_join .= " AND catalogs.catalog_type = '".$params['catalogType']."' ";
    	$this->db->join("hotelmodule_pricecatalogs as catalogs", "t1.pricecatalog_id = catalogs.pricecatalog_id ".$catalog_join);
    }
    if(isset($params['pricecatalogId'])) $this->db->where("t1.pricecatalog_id",$params['pricecatalogId']);
    if(isset($params['roomId'])) $this->db->where("t1.room_id",$params['roomId']);
    if(isset($params['scenarioId'])) {
      if(is_array($params['scenarioId'])) {
        if(isset($params['scenarioId']['PP']) || isset($params['scenarioId']['PU'])){
          $opar="";
          $cpar="";
          if(isset($params['scenarioId']['PU']) && $params['scenarioId']['PU'] && isset($params['scenarioId']['PP']) && $params['scenarioId']['PP']) {
            $opar = "(";
            $cpar = ")";
          }
          $pp=0;
          if(isset($params['scenarioId']['PP']) && $params['scenarioId']['PP']) {
          	$pp=1;
            $this->db->where($opar."(rooms.price_type = 'PP' and t1.scenario_id in (".implode(",",$params['scenarioId']['PP'])."))");
          }
          if(isset($params['scenarioId']['PU']) && $params['scenarioId']['PU']) {
            if($pp) $this->db->or_where("(rooms.price_type = 'PU' and t1.scenario_id in (".implode(",",$params['scenarioId']['PU'])."))".$cpar);
            else $this->db->where("(rooms.price_type = 'PU' and t1.scenario_id in (".implode(",",$params['scenarioId']['PU'])."))".$cpar);
          }
        } else {
          $this->db->where_in("t1.scenario_id",$params['scenarioId']);
        }
      }  else $this->db->where("t1.scenario_id",$params['scenarioId']);
    }
    if(isset($params['mealId'])) $this->db->where("t1.meal_id",$params['mealId']);
    
    $this->db->where("t1.year",$year_start);
    
    $result = $this->db->get();    
    
    //echo $this->db->last_query();
    
    $helper = array();
    $default_meals = array();
    foreach($result->result_array() as $row){
      $day = $from;
      $total_price = 0;
      
      $analysis = array();
      $tmp = array();
      $mtmp = array();
      $default_meal = $this->fetch_priceanalysis_to_date(array("pricecatalog_id"=>$row['pricecatalog_id'],"room_id"=>$row['room_id'],"date >="=>date("Y-m-d",$from),"date <="=>date("Y-m-d",$to)));

      while($day <= $to) {
        $fdate = date("Y-m-d",$day);
        if($row[$fdate]==null) {
        	$analysis = array();
          $tmp = array();
          break;
        }
        
        $analysis[$fdate] = array("price"=>$row[$fdate]);
        $tmp[$fdate] = $row[$fdate];
        
        if(isset($default_meal[$fdate]['hotelmeal_id'])) $mtmp[$fdate] = $default_meal[$fdate]['hotelmeal_id'];
        else $mtmp[$fdate] = false;
        
        $day = strtotime("+1 day", $day);
      }
      
      
      if($analysis) {
      	$return['hotel_id'] = $row['hotelId'];
      	
        $catalog = $this->hmodel_pricecatalogs->fetch_pricecatalogs(array("where"=>array("pricecatalog_id"=>$row['pricecatalog_id'])),"element");
                
        if(!isset($return['Catalogs'][$catalog['pricecatalog_id']])) {
          $return['Catalogs'][$catalog['pricecatalog_id']] = $catalog;
        }
        
        if(!isset($return['Rooms'][$row['room_id']])) {
          $return['Rooms'][$row['room_id']] = $this->hmodel_hotelrooms->fetch_rooms(array("where"=>array("room_id"=>$row['room_id'])),"element");
        }          

        if(!isset($return['Scenarios'][$row['scenario_id']])) {
          $return['Scenarios'][$row['scenario_id']] = $this->hmodel_hotelroomscenarios->fetch_scenarios(array("where"=>array("scenario_id"=>$row['scenario_id'])),"element");
        }  
        
        if(!isset($return['Meals'][$row['meal_id']])) {
          $return['Meals'][$row['meal_id']] = $this->hmodel_hotelmeals->fetch_hotelmeals(array("where"=>array("hotelmeal_id"=>$row['meal_id'])),"element");
        }
                          
        $return['Prices'][$row['pricecatalog_id']]['Rooms'][$row['room_id']]['Scenarios'][$row['scenario_id']]['Meals'][$row['meal_id']]['Analysis'] = $analysis;
        
        $helper[$row['pricecatalog_id']."_".$row['room_id']."_".$row['scenario_id']."_".$row['meal_id']]['prices'] = $tmp;
        $helper[$row['pricecatalog_id']."_".$row['room_id']."_".$row['scenario_id']."_".$row['meal_id']]['default_meal'] = $mtmp;
      }
    }

    $offerpolicies = array();

    foreach($helper as $key=>$daily_prices) {
      list($catalog_id,$room_id,$scenario_id,$meal_id) = explode("_",$key);
              
      $min = min($daily_prices['prices']);
      $total = array_sum($daily_prices['prices']);
      $offer_min = $min;
      $offer_total = $total;   
      
      $offer_tmp = $daily_prices['prices'];
      $offerid = 0;
            
      if(!isset($return['Prices'][$catalog_id]['catalog_id'])) $return['Prices'][$catalog_id]['catalog_id'] = $catalog_id;
      if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['room_id'])) $return['Prices'][$catalog_id]['Rooms'][$room_id]['room_id'] = $room_id;
      if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]["scenario_id"])) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]["scenario_id"] = $scenario_id;
      if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]["meal_id"])) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]["meal_id"] = $meal_id;
      
      if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_price'] > $offer_total) {
        $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['Key'] = $key;
        $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offerid'] = $offerid;
        $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['total_price'] = $total;
        $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['min_daily_price'] = $min;
        $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_price'] = $offer_total;
        $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_min_daily_price'] = $offer_min;
                
        $policies = array();
        if(isset($params['policies'][$catalog_id][$room_id])) $policies = $params['policies'][$catalog_id][$room_id];
        $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['policies'] = $policies;
      }
      
      if(!isset($return['Prices'][$catalog_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Total']['offer_price'] > $offer_total) $return['Prices'][$catalog_id]['Total'] = $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total'];
      if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Rooms'][$room_id]['Total']['offer_price'] > $offer_total) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Total'] = $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total'];
      if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Total']['offer_price'] > $offer_total) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Total'] = $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total'];              
      
      if(isset($params['offers'][$catalog_id][$room_id][$meal_id])) {  
                
        foreach($params['offers'][$catalog_id][$room_id][$meal_id] as $offerid) {
          
          $offer_sum = array();
          $new_offer_sum = array();
          
          if($params['offers']['Offers'][$offerid]['text_only']!=1) {              
            $meal_upgrade = $params['offers']['Offers'][$offerid]['meal_upgrade_id'];
                          
            $base_discount = $params['offers']['Offers'][$offerid]['base_discound'];
            $meal_discount = $params['offers']['Offers'][$offerid]['meal_discound'];
            $days_applied = $params['offers']['Offers'][$offerid]['days_applied'];
            
            $free_type = $params['offers']['Offers'][$offerid]['free_type'];
            $free_total_nights = $params['offers']['Offers'][$offerid]['free_total_nights'];
            $free_free_nights = $params['offers']['Offers'][$offerid]['free_free_nights'];
            
            $start = strtotime($params['offers']['Offers'][$offerid]['offer_start']);
            $end = strtotime($params['offers']['Offers'][$offerid]['offer_end']);
            $offer_tmp = array();
            $no_offer_tmp = array();

            foreach($daily_prices['prices'] as $date => $price) {
              $weekday = date("D",strtotime($date));
              if((!$start || strtotime($date) >= $start) && (!$end || strtotime($date) <= $end) && (!$days_applied || $days[$weekday] & $days_applied) && isset($helper[$key]['default_meal'][$date])  && isset($helper[$catalog_id."_".$room_id."_".$scenario_id."_".$helper[$key]['default_meal'][$date]])) {
                if($meal_upgrade > 0) {
                  $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Meal_upgrade'][$date] = $meal_upgrade;
                }
                $def_meal_price = false;
                if(isset($helper[$catalog_id."_".$room_id."_".$scenario_id."_".$helper[$key]['default_meal'][$date]][$date])) $def_meal_price = $helper[$catalog_id."_".$room_id."_".$scenario_id."_".$helper[$key]['default_meal'][$date]][$date];
                if($def_meal_price !==false && $def_meal_price < $price) {
                  $meal_supplement = $price - $def_meal_price;
                  $offer_tmp[$date] = $def_meal_price - ($def_meal_price * ($base_discount/100)) + $meal_supplement - ($meal_supplement * ($meal_discount/100));
                } else {
                  $offer_tmp[$date] = $price - ($price * ($base_discount/100)); 
                }
              } else {
                $no_offer_tmp[$date] = $price;
              }
            }
            
            if($free_type !='N') {
              if(count($offer_tmp) > $free_total_nights) {
                $chunks = array_chunk($offer_tmp,$free_total_nights,true);
                foreach($chunks as $chunk) {
                  if(count($chunk) < $free_total_nights) continue;
                  switch($free_type) {
                    case "First" :
                      $x=1;
                      foreach($chunk as $d=>$pr) {
                        $weekday = date("D",strtotime($d));
                        if($x > $free_free_nights) break;
                        if(($days[$weekday] & $days_applied)) $offer_tmp[$d] = 0;
                        $x++;
                      }
                    break;
                    case "Last" :
                      $x=0;
                      foreach($chunk as $d=>$pr) { 
                        $weekday = date("D",strtotime($d));
                        $x++;
                        if($x <= ($free_total_nights - $free_free_nights)) continue;
                        if(($days[$weekday] & $days_applied)) $offer_tmp[$d] = 0;
                      }
                    break;  
                    case "Cheaper" :
                      $x=1;
                      asort($chunk);
                      foreach($chunk as $d=>$pr) { 
                        $weekday = date("D",strtotime($d));
                        if($x > $free_free_nights) break;
                        if(($days[$weekday] & $days_applied)) $offer_tmp[$d] = 0;
                        $x++;
                      }
                    break;  
                    case "Expensive" :
                      $x=1;
                      arsort($chunk);
                      foreach($chunk as $d=>$pr) { 
                        $weekday = date("D",strtotime($d));
                        if($x > $free_free_nights) break;
                        if(($days[$weekday] & $days_applied)) $offer_tmp[$d] = 0;
                        $x++;
                      }
                    break;                                                              
                  }
                }
              }
            }
            
            $values = array_filter($offer_tmp, create_function('$v', 'return $v > 0;'));
            if($values)  {
              $values1 = array_filter($no_offer_tmp, create_function('$v', 'return $v > 0;'));
              $v = array_merge($values,$values1);
              $offer_sum = array_merge($offer_tmp,$no_offer_tmp);
              $offer_min = min($v);
              $offer_total = array_sum($offer_sum);
            }
            
            if($offer_sum) 
              foreach($offer_sum as $k=>$v) {
            	  $new_offer_sum[$k] = array("offer_price"=>$v,"offer_id"=>$offerid);
              }
          }
          
          if(!isset($offerpolicies[$offerid])) {
            $offerpolicies[$offerid] = $this->hmodel_hotelofferpolicies->fetch_hotelofferpolicies(array("where"=>array("hoteloffer_id"=>$offerid)));
            $return['Offers'][$offerid] = $this->hmodel_hoteloffers->fetch_hoteloffers(array("where"=>array("hoteloffer_id"=>$offerid)),"element");
          }
          
          if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_price'] > $offer_total) {
          	
          	if($new_offer_sum) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Analysis'] = array_merge_recursive($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Analysis'],$new_offer_sum);
          	
            $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['Key'] = $key;
            $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offerid'] = $offerid;
            $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['total_price'] = $total;
            $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['min_daily_price'] = $min;
            $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_price'] = $offer_total;
            $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['offer_min_daily_price'] = $offer_min;
           
            if($offerpolicies[$offerid]) {
              foreach($offerpolicies[$offerid] as $key=>$offerpolicy){
              	if(isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['policies'])) {
              		$found=0;
              	  foreach($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['policies'] as $pk=>$policy) {
              		  if($policy['group'] == $offerpolicy['policy']['group']) {
              			  if($policy['weight'] < $offerpolicy['policy']['weight']) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['policies'][$pk] = $offerpolicy['policy'];
              			  $found =1;
              		  }
              	  }
              	  if($found==0) array_push($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['policies'],$offerpolicy['policy']);
              	}	else {
              		$return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total']['policies'][] = $offerpolicy['policy'];
              	} 
              }
            }    
            
          }
          
          if(!isset($return['Prices'][$catalog_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Total']['offer_price'] > $offer_total) $return['Prices'][$catalog_id]['Total'] = $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total'];
          if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Rooms'][$room_id]['Total']['offer_price'] > $offer_total) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Total'] = $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total'];
          if(!isset($return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Total']['offer_price']) || $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Total']['offer_price'] > $offer_total) $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Total'] = $return['Prices'][$catalog_id]['Rooms'][$room_id]['Scenarios'][$scenario_id]['Meals'][$meal_id]['Total'];      
        }
      } 
    }

    $this->session->cache[$hash] = $return;
    return $return;          
  }
    
  function fetch_analysis($data=false,$type="array") {
    $hash = md5("fetch_analysis".$this->hmodel_global->encode_params($data).$type);
    if(isset($this->session->cache[$hash])) return $this->session->cache[$hash];
    
    $return = array();
    $this->db->select('priceanalysis_id,md5key,analysis');
    $this->db->from('hotelmodule_priceanalysis');  
    
    if($data!==false) {
      $this->db->where($data);
    }      
    
    $result = $this->db->get();

    foreach($result->result_array() as $row){
      $tmp = array();
      $tmp = $row;
      if($type=="array") $return[$row['priceanalysis_id']] = $tmp;
      if($type=="element") $return = $tmp;
    }
    
    $this->session->cache[$hash] = $return;
    return $return;  
  }    
    
    
  function fetch_priceanalysis_to_date($data=false,$type="array") {
    $hash = md5("fetch_priceanalysis_to_date".$this->hmodel_global->encode_params($data).$type);
    if(isset($this->session->cache[$hash])) return $this->session->cache[$hash];
    
    $return = array();
    $this->db->select('*');
    $this->db->from('hotelmodule_priceanalysis_to_date');  
    
    if($data!==false) {
      $this->db->where($data);
    }      
    
    $result = $this->db->get();

    foreach($result->result_array() as $row){
      $tmp = array();
      $tmp = $row;
      if($type=="array") $return[$row['date']] = $tmp;
      if($type=="element") $return = $tmp;
    }
    
    $this->session->cache[$hash] = $return;
    return $return;        
  }
    
  function fetch_analysisid_per_day($pricecatalogId,$roomid,$day) {
    if($day=="") return false;
    $this->db->select('*');
    $this->db->from('hotelmodule_priceanalysis_to_date');
    $this->db->where("pricecatalog_id",$pricecatalogId);
    $this->db->where("room_id",$roomid);
    $this->db->where("date",$day);
    
    $result = $this->db->get();      
    if($result->num_rows != 1) return false;
    else {
      $row = $result->row();
      $analysis = $this->fetch_analysis(array("priceanalysis_id"=>$row->priceanalysis_id),"element");
      $analysis['base'] = $row->hotelmeal_id;
      return $analysis;
    }      
  }
    
  function check_locked($pricecatalogId,$roomid,$date) {
    $this->db->select('locked');
    $this->db->from('hotelmodule_priceanalysis_to_date');
    $this->db->where("pricecatalog_id",$pricecatalogId);
    $this->db->where("room_id",$roomid);
    $this->db->where("date",$date);
    $result = $this->db->get();
    if($result->num_rows == 1) {
      $row = $result->row();
      return $row->locked;
    } else {
      return null;
    }
  }
  
  function least_price($perioddate,$entries,$room=null) {

    $from = strtotime($perioddate['from_date']);
    $to = strtotime($perioddate['to_date']);  	
    $year_start = date("Y",$from);
    $year_end = date("Y",$to);    
  	
    $dbprefix = $this->db->dbprefix;
		$this->db->dbprefix = "";

    $this->db->from($dbprefix.'hotelmodule_roomprices as t1');    	
    
    $day = $from;
    $least = " MIN(LEAST(";
		while($day <= $to) {
			$year = (date("Y",$day) - $year_start) + 1;
			$date = date("m-d",$day);					
			if($least != " MIN(LEAST(") $least .= ",";
			$least .= 'IFNULL(`t'.$year.'`.`'.$date.'`,9999999999)';
			$day = strtotime("+1 day", $day);
		}
		$least .= ",9999999999)) as least ";

		$this->db->select($least,false);
    
    $y=2;
    for($x=$year_start+1;$x<=$year_end;$x++) {
    	if($year_start<$year_end) {
    		$this->db->join($dbprefix."hotelmodule_roomprices as t".$y, "t1.pricecatalog_id = t".$y.".pricecatalog_id and t1.room_id = t".$y.".room_id and t1.scenario_id = t".$y.".scenario_id and t1.meal_id = t".$y.".meal_id and t1.year+".($y-1)."=t".$y.".year", 'left');	
    		$y++;
    	}
    }
    
    $room_join = "rooms.room_id = t1.room_id";
   	$room_join .=" and rooms.entry_id in (".$entries.")";
    
    $this->db->join($dbprefix."hotelmodule_rooms as rooms", $room_join); 	
    $this->db->join($dbprefix."hotelmodule_pricecatalogs as catalogs", "t1.pricecatalog_id = catalogs.pricecatalog_id AND catalogs.channel = 'WEBSITE' AND catalogs.catalog_type = 'OFFICIAL'");    
    $this->db->join($dbprefix."hotelmodule_roomoccupancyscenarios as scenarios", "t1.scenario_id = scenarios.scenario_id AND scenarios.is_default = 1");
    if($room!=null) $this->db->where("rooms.room_id",$room['room_id']);
    $this->db->where("t1.year",$year_start);
    
		$result = $this->db->get();
				
		if($result === false) return -1;
    								
		$this->db->dbprefix = $dbprefix;
		
		if($result->row()->least)	{
			if($result->row()->least == 9999999999) return -1; 
			return $result->row()->least;
		}	
		
		return -1;
		
  }
  
  function entries_range($perioddate,$entries,$min=0,$max=99999999999,$room=null) {
    $from = strtotime($perioddate['from_date']);
    $to = strtotime($perioddate['to_date']);  	
    $year_start = date("Y",$from);
    $year_end = date("Y",$to);    

    $this->db->save_queries = true;

    $dbprefix = $this->db->dbprefix;
		$this->db->dbprefix = "";

    $this->db->from($dbprefix.'hotelmodule_roomprices as t1');    	
    
    $day = $from;
    $least = " DISTINCT IF(LEAST(";
		while($day <= $to) {
			$year = (date("Y",$day) - $year_start) + 1;
			$date = date("m-d",$day);					
			if($least != " DISTINCT IF(LEAST(") $least .= ",";
			$least .= 'IFNULL(`t'.$year.'`.`'.$date.'`,9999999999)';
			$day = strtotime("+1 day", $day);
		}
		$least .= ") >= ".ceil($min).",";
		$least .= " IF(LEAST(";
		$day = $from;
		$l=0;
		while($day <= $to) {
			$year = (date("Y",$day) - $year_start) + 1;
			$date = date("m-d",$day);					
			if($l != 0) $least .= ",";
			$least .= 'IFNULL(`t'.$year.'`.`'.$date.'`,9999999999)';
			$day = strtotime("+1 day", $day);
			$l=1;
		}
		$least .= ") <= ".floor($max).",rooms.entry_id,0)";
		
		$least .= ",0) as eid ";
		
		$this->db->select($least,false);
    
    $y=2;
    for($x=$year_start+1;$x<=$year_end;$x++) {
    	if($year_start<$year_end) {
    		$this->db->join($dbprefix."hotelmodule_roomprices as t".$y, "t1.pricecatalog_id = t".$y.".pricecatalog_id and t1.room_id = t".$y.".room_id and t1.scenario_id = t".$y.".scenario_id and t1.meal_id = t".$y.".meal_id and t1.year+".($y-1)."=t".$y.".year", 'left');	
    		$y++;
    	}
    }
    
    $room_join = "rooms.room_id = t1.room_id";
   	$room_join .=" and rooms.entry_id in (".$entries.")";
    
    $this->db->join($dbprefix."hotelmodule_rooms as rooms", $room_join); 	
    $this->db->join($dbprefix."hotelmodule_pricecatalogs as catalogs", "t1.pricecatalog_id = catalogs.pricecatalog_id AND catalogs.channel = 'WEBSITE' AND catalogs.catalog_type = 'OFFICIAL'");    
    $this->db->join($dbprefix."hotelmodule_roomoccupancyscenarios as scenarios", "t1.scenario_id = scenarios.scenario_id AND scenarios.is_default = 1");
    if($room!=null) $this->db->where("rooms.room_id",$room['room_id']);
    $this->db->where("t1.year",$year_start);  	 
    
		$result = $this->db->get();
		
		if($result === false) return -1;  
		
		$this->db->dbprefix = $dbprefix;
		
		$return = array();
		foreach($result->result_array() as $row){
      $return[] = $row['eid'];
    }		
    
		return implode("|",$return);
		
  }
  
  function max_least_price($perioddate,$entries,$room=null) {
  	
    $from = strtotime($perioddate['from_date']);
    $to = strtotime($perioddate['to_date']);  	
    $year_start = date("Y",$from);
    $year_end = date("Y",$to);    
  	
    $dbprefix = $this->db->dbprefix;
		$this->db->dbprefix = "";
		
		//$this->db->save_queries = true;

    $this->db->from($dbprefix.'hotelmodule_roomprices as t1');    	
    
    $day = $from;
    $least = " MIN(NULLIF(LEAST(";
		while($day <= $to) {
			$year = (date("Y",$day) - $year_start) + 1;
			$date = date("m-d",$day);					
			if($least != " MIN(NULLIF(LEAST(") $least .= ",";
			$least .= 'IFNULL(`t'.$year.'`.`'.$date.'`,9999999999)';
			$day = strtotime("+1 day", $day);
		}
		$least .= ",9999999999),9999999999)) as max_least ";
				
		$this->db->select($least,false);
    
    $y=2;
    for($x=$year_start+1;$x<=$year_end;$x++) {
    	if($year_start<$year_end) {
    		$this->db->join($dbprefix."hotelmodule_roomprices as t".$y, "t1.pricecatalog_id = t".$y.".pricecatalog_id and t1.room_id = t".$y.".room_id and t1.scenario_id = t".$y.".scenario_id and t1.meal_id = t".$y.".meal_id and t1.year+".($y-1)."=t".$y.".year", 'left');	
    		$y++;
    	}
    }
    
    $room_join = "rooms.room_id = t1.room_id";
    $room_join .=" and rooms.entry_id in (".$entries.")";
    
    $this->db->join($dbprefix."hotelmodule_rooms as rooms", $room_join); 	
    $this->db->join($dbprefix."hotelmodule_pricecatalogs as catalogs", "t1.pricecatalog_id = catalogs.pricecatalog_id AND catalogs.channel = 'WEBSITE' AND catalogs.catalog_type = 'OFFICIAL'");    
    $this->db->join($dbprefix."hotelmodule_roomoccupancyscenarios as scenarios", "t1.scenario_id = scenarios.scenario_id AND scenarios.is_default = 1");
    if($room!=null) $this->db->where("rooms.room_id",$room['room_id']);
    $this->db->where("t1.year",$year_start);
    
    $this->db->group_by("rooms.entry_id");
    $this->db->order_by("max_least","DESC");
    $this->db->limit(1);
        
		$result = $this->db->get();
				
		if($result === false) return -1;
    								
		$this->db->dbprefix = $dbprefix;
		
		if($result->row()->max_least)	{
			if($result->row()->max_least == null) return -1; 
			else return $result->row()->max_least;
		}	
		
		return -1;  		

  }  
  
  /*function entries_range($perioddate,$entries,$min=0,$max=99999999999,$room=null) {

    $from = strtotime($perioddate['from_date']);
    $to = strtotime($perioddate['to_date']);  	
    $year_start = date("Y",$from);
    $year_end = date("Y",$to);    
  	$this->db->save_queries = true;
    $dbprefix = $this->db->dbprefix;
		$this->db->dbprefix = "";

    $this->db->from($dbprefix.'hotelmodule_roomprices as t1');    	
    
    $day = $from;
    $least = " MIN(LEAST(";
		while($day <= $to) {
			$year = (date("Y",$day) - $year_start) + 1;
			$date = date("m-d",$day);					
			if($least != " MIN(LEAST(") $least .= ",";
			$least .= 'IFNULL(`t'.$year.'`.`'.$date.'`,9999999999)';
			$day = strtotime("+1 day", $day);
		}
		$least .= ",9999999999)) as least ";

    $this->db->select("rooms.entry_id",false);
		$this->db->select($least,false);
    
    $y=2;
    for($x=$year_start+1;$x<=$year_end;$x++) {
    	if($year_start<$year_end) {
    		$this->db->join($dbprefix."hotelmodule_roomprices as t".$y, "t1.pricecatalog_id = t".$y.".pricecatalog_id and t1.room_id = t".$y.".room_id and t1.scenario_id = t".$y.".scenario_id and t1.meal_id = t".$y.".meal_id and t1.year+".($y-1)."=t".$y.".year", 'left');	
    		$y++;
    	}
    }
    
    $room_join = "rooms.room_id = t1.room_id";
   	$room_join .=" and rooms.entry_id in (".$entries.")";
    
    $this->db->join($dbprefix."hotelmodule_rooms as rooms", $room_join); 	
    $this->db->join($dbprefix."hotelmodule_pricecatalogs as catalogs", "t1.pricecatalog_id = catalogs.pricecatalog_id AND catalogs.channel = 'WEBSITE' AND catalogs.catalog_type = 'OFFICIAL'");    
    $this->db->join($dbprefix."hotelmodule_roomoccupancyscenarios as scenarios", "t1.scenario_id = scenarios.scenario_id AND scenarios.is_default = 1");
    if($room!=null) $this->db->where("rooms.room_id",$room['room_id']);
    $this->db->where("t1.year",$year_start);
    //$this->db->where("least >=",$min);
    //$this->db->where("least <=",$max);
    $this->db->group_by("rooms.entry_id");
    
    
		$result = $this->db->get();
		
		echo $this->db->last_query();
		
		if($result === false) return -1;
    								
		$this->db->dbprefix = $dbprefix;
		
		if($result->row()->least)	{
			if($result->row()->least == 9999999999) return -1; 
			return $result->row()->least;
		}	
		
		return -1;
		
  }  */
  
  
  
}