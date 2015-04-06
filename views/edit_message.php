<?php
$enquiryID = $get_params['enquiryID'];
?>
<script>

$(document).ready(function() {
  $("#load_template").bind("click",function() {load_template("load")});
  $("#insert_notice").bind("click",function() {load_template("insert_notice")});
  $("#insert_message").bind("click",function() {load_template("insert_message")});
  
  $("#load_data").bind("click",function() {load_data()});
});


function load_template(act) {
    var template_id = $("#template").val();
    var enquiry_id = $("#template").val();
    
    var hotel_services = $("input[name='hotel_services']").serializeArray();
    
		$.ajax({
				type: "POST",
		 		url:"<?php echo HOTELMODULE_URL.'&method=ajax_load_messagetemplate' ?>",
		 		data: { XID:EE.XID,"templateID":template_id,"enquiryID":<?=$enquiryID?>,"hotel_services":hotel_services},
			  success: function(data) {
			  	//$("#l").html(data);
			  	try { 
			  		var jsdata = jQuery.parseJSON(data);
			  		
			  		var notice_editor = CKEDITOR.instances.email_notice;
			  		var message_editor = CKEDITOR.instances.message;
					  
					  if(act=='load') {
					    $("#recipient_email").val(jsdata['to']);
					    $("#subject").val(jsdata['subject']);
					    notice_editor.setData(jsdata['email_notice']);
					    message_editor.setData(jsdata['message']);
					  }
	          
	          if(act == "insert_notice") notice_editor.insertHtml(jsdata['email_notice']);
	          if(act == "insert_message") message_editor.insertHtml(jsdata['message']);	          
	          
	        } catch(err) { alert("error");}
		  	},
				error: function(xhr, textStatus, errorThrown){
					alert("AJAX ERROR")
    		}
		});	
}
</script>

<?php

echo "<table class='mainTable' cellspacing='0' cellpadding='4' border='0'>";
echo "<tr>";
echo "<th colspan='2'>";

include "enquiry_head.php";

echo "</th>";
echo "</tr>";
echo "<tr>";
echo "<td valign='top' width='250'>";

include "enquiry_menu.php";

echo "</td>";
echo "<td>";

echo "<div class='cp_button' style='float:left'>";
echo "<a href='".HOTELMODULE_URL."&method=list_messages&enquiryID=".$enquiryID."'> &laquo; List messages</a>";
echo "</div>";

echo form_open(HOTELMODULE_FORMURL."&method=edit_message&enquiryID=".$enquiryID."&messageID=".$data['message_id']);
echo form_hidden(array('action' => 'save_send'));

echo "<div class='clear'></div>";

if(isset($error_message)) echo $error_message;
if(isset($message)) echo $message;

echo "<div class='heading'><h2>New Message</h2></div>";

echo "<table class='mainTable' cellspacing='0' cellpadding='4' border='0'>";

echo "<tr>";
echo "<td width='400'>Services :</td>";
echo "<td>";

foreach($enquiry['services'] as $group => $group_services) {
	foreach($group_services as $service_id => $service) {
		switch($group) {
		  case "hotel" :
		    echo "<input type='checkbox' name='hotel_services' value='".$service['hotelenquiry_id']."' /> ".$service['status']." ".$group." ".$service['hotelname']." ".$service['info']['room_name']." ".$service['checkin']." ".$service['checkout']."<br/>";
		  break;
		}
	}
}

echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td width='400'>Template :</td>";
echo "<td>";

echo "<select id='template'>";
echo "<option value=0></option>";
foreach($templates as $k=>$template) {
	echo "<option value='".$template['messagetemplate_id']."'>".$template['title']." {".$template['language']['language_name']."}</option>";
}
echo "</select>";

echo "<input type='button' value='Load' id='load_template'>";
echo "<input type='button' value='Insert Notice' id='insert_notice'>";
echo "<input type='button' value='Insert Message' id='insert_message'>";

echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td width='400'>From :</td>";
echo "<td>";
echo $sender;
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>To :</td>";
echo "<td>";
if(isset($enquiry['member'])) echo $enquiry['member']['firstname']." ".$enquiry['member']['lastname']." &laquo;".$enquiry['member']['email']."&raquo";
else echo $enquiry['customer']['firstname']." ".$enquiry['customer']['lastname']." &laquo;".$enquiry['customer']['email']."&raquo";
echo "</td>";
echo "</tr>";


echo "<tr>";
echo "<td>Subject :</td>";
echo "<td>";
echo "<input type='text' name='subject' id='subject' value='".$this->hotelmodule_validation->set_value('subject',$data['subject'])."'/>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Email notice :</td>";
echo "<td>";
echo "<textarea name='email_notice' id='email_notice'>";
echo $this->hotelmodule_validation->set_value('email_notice',$data['email_notice']);
echo "</textarea>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Message :</td>";
echo "<td>";
echo "<textarea name='message' id='message'>";
echo $this->hotelmodule_validation->set_value('message',$data['message']);
echo "</textarea>";
echo "</td>";
echo "</tr>";


echo "</table>";

echo "<input class='submit' type='submit' value='Save & Send' name='submit'>";

echo form_close();

echo "</td>";
echo "</tr>";
echo "</table>";
