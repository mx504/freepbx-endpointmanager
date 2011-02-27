<?PHP
/**
 * Endpoint Manager FreePBX Hooks File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */

function endpointman_get_config($engine) {
  global $db;
  global $ext; 
  global $core_conf;

  $sql = 'SELECT value FROM `admin` WHERE `variable` LIKE CONVERT(_utf8 \'version\' USING latin1) COLLATE latin1_swedish_ci';
  $amp_version = $db->getOne($sql);

  switch($engine) {
    case "asterisk":
    if (isset($core_conf) && is_a($core_conf, "core_conf") && ($amp_version >= "2.8.0")) {
        $core_conf->addSipNotify('polycom-check-cfg',array('Event' => 'check-sync','Content-Length' => '0'));
        $core_conf->addSipNotify('polycom-reboot',array('Event' => 'check-sync','Content-Length' => '0'));
        $core_conf->addSipNotify('sipura-check-cfg',array('Event' => 'resync','Content-Length' => '0'));
        $core_conf->addSipNotify('grandstream-check-cfg',array('Event' => 'sys-control'));
        $core_conf->addSipNotify('cisco-check-cfg',array('Event' => 'check-sync','Content-Length' => '0'));
        $core_conf->addSipNotify('reboot-snom',array('Event' => 'reboot','Content-Length' => '0'));
        $core_conf->addSipNotify('aastra-check-cfg',array('Event' => 'check-sync','Content-Length' => '0'));
        $core_conf->addSipNotify('linksys-cold-restart',array('Event' => 'reboot_now','Content-Length' => '0'));
        $core_conf->addSipNotify('linksys-warm-restart',array('Event' => 'restart_now','Content-Length' => '0'));
        $core_conf->addSipNotify('spa-reboot',array('Event' => 'reboot','Content-Length' => '0'));
      }
    break;
  }
}
function endpointman_configpageinit($pagename) {
	global $currentcomponent, $amp_conf;

        $display = isset($_REQUEST['display'])?$_REQUEST['display']:null;

        if($display == "extensions") {
            if(isset($_REQUEST['extension'])) {
                $extdisplay = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
            } else {
                $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
            }
        } elseif($display == "devices") {
            $extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
        }

        $action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
        $delete = isset($_REQUEST['epm_delete'])?$_REQUEST['epm_delete']:null;
        $tech = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

        if((($display == "extensions") OR ($display == "devices")) && (isset($extdisplay) OR ($tech == "sip_generic"))) {
            global $endpoint;

            $doc_root =	$amp_conf['AMPWEBROOT'] ."/admin/modules/endpointman/";
            require($doc_root . "includes/functions.inc");

            $endpoint = new endpointmanager();
            ini_set('display_errors', 0);

            if ($action == "del") {        
                $sql = "SELECT mac_id,luid FROM endpointman_line_list WHERE ext = ". $extdisplay;
                $macid = $endpoint->db->getRow($sql,array(),DB_FETCHMODE_ASSOC);
                if($macid) {
                    $endpoint->delete_line($macid['luid'], TRUE);
                }
            }

            if(($action == "edit") OR ($action == "add")) {
                if(isset($delete)) {
                    $sql = "SELECT mac_id,luid FROM endpointman_line_list WHERE ext = ". $extdisplay;
                    $macid = $endpoint->db->getRow($sql,array(),DB_FETCHMODE_ASSOC);
                    if($macid) {
                        $endpoint->delete_line($macid['luid'], TRUE);
                    }
                }

                $mac = isset($_REQUEST['epm_mac'])?$_REQUEST['epm_mac']:null;

                if(!empty($mac)) {
                    //Mac is set
                    $brand = isset($_REQUEST['epm_brand'])?$_REQUEST['epm_brand']:null;
                    $model = isset($_REQUEST['epm_model'])?$_REQUEST['epm_model']:null;
                    $line = isset($_REQUEST['epm_line'])?$_REQUEST['epm_line']:null;
                    $temp = isset($_REQUEST['epm_temps'])?$_REQUEST['epm_temps']:null;
                    if(isset($_REQUEST['name'])) {
                        $name = isset($_REQUEST['name'])?$_REQUEST['name']:null;
                    } else {
                        $name = isset($_REQUEST['description'])?$_REQUEST['description']:null;
                    }
                    $reboot = isset($_REQUEST['epm_reboot'])?$_REQUEST['epm_reboot']:null;

                    if($endpoint->mac_check_clean($mac)) {
                        $sql = "SELECT id FROM endpointman_mac_list WHERE mac = '".$endpoint->mac_check_clean($mac)."'";
                        $macid = $endpoint->db->getOne($sql);
                        if($macid) {
                            //In Database already
                            $sql = 'SELECT * FROM endpointman_line_list WHERE ext = '.$extdisplay.' AND mac_id = '. $macid;
                            $lines_list =& $endpoint->db->getRow($sql,array(),DB_FETCHMODE_ASSOC);
                            if(($lines_list) AND (isset($model)) AND (isset($line)) AND (!isset($delete)) AND (isset($temp))) {
                                //Modifying line already in the database
                                $sql = "UPDATE endpointman_line_list SET line = ".$lines_list['line'].", description =  '".$name."' WHERE  luid =" . $lines_list['luid'];
                                $endpoint->db->query($sql);

                                $sql = "UPDATE  endpointman_mac_list SET model = ".$model.", template_id =  ".$temp." WHERE id = ".$macid;
                                $endpoint->db->query($sql);

                                $row = $endpoint->get_phone_info($macid);
                                if(isset($reboot)) {
                                    $endpoint->prepare_configs($row);
                                } else {
                                    $endpoint->prepare_configs($row,FALSE);
                                }
                            } elseif((isset($model)) AND (!isset($delete)) AND (isset($line)) AND (isset($temp))) {
                                //Add line to the database
                                $sql = "INSERT INTO endpointman_line_list (mac_id, line, ext, description) VALUES (".$macid.",  ".$line.",  ".$extdisplay.",  '".$name."')";
                                $endpoint->db->query($sql);

                                $sql = "UPDATE  endpointman_mac_list SET model = ".$model.", template_id =  ".$temp." WHERE id = ".$macid;
                                $endpoint->db->query($sql);

                                $row = $endpoint->get_phone_info($macid);
                                if(isset($reboot)) {
                                    $endpoint->prepare_configs($row);
                                } else {
                                    $endpoint->prepare_configs($row,FALSE);
                                }
                            }
                        } elseif(!isset($delete)) {
                            //Add Extension/Phone to database
                            $mac = $endpoint->mac_check_clean($mac);
                            $sql = "INSERT INTO `endpointman_mac_list` (`mac`, `model`, `template_id`) VALUES ('".$mac."', '".$model."', '".$temp."')";
                            $endpoint->db->query($sql);

                            $sql = 'SELECT last_insert_id()';
                            $ext_id =& $endpoint->db->getOne($sql);

                            if(!isset($line)) {
                                $line = 1;
                            }

                            $sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('".$ext_id."', '".$extdisplay."', '".$line."', '".$name."')";
                            $endpoint->db->query($sql);

                            $row = $endpoint->get_phone_info($ext_id);
                            if(isset($reboot)) {
                                $endpoint->prepare_configs($row);
                            } else {
                                $endpoint->prepare_configs($row,FALSE);
                            }
                        }
                    }
                } else {
                    //Mac not set so delete
                    $sql = "SELECT mac_id,luid FROM endpointman_line_list WHERE ext = ". $extdisplay;
                    $macid = $endpoint->db->getRow($sql,array(),DB_FETCHMODE_ASSOC);
                    if($macid) {
                        $endpoint->delete_line($macid['luid'], TRUE);
                    }
                }
            }
            endpointman_applyhooks();
        }
}
function endpointman_applyhooks() {
	global $currentcomponent;

	// Add the 'process' function - this gets called when the page is loaded, to hook into
	// displaying stuff on the page.
	$currentcomponent->addguifunc('endpointman_configpageload');
}
// This is called before the page is actually displayed, so we can use addguielem().
function endpointman_configpageload() {
	global $currentcomponent, $endpoint, $db;

        $display = isset($_REQUEST['display'])?$_REQUEST['display']:null;

	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

	// Don't display this stuff it it's on a 'This xtn has been deleted' page.
	if ($action != 'del') {

            $js = "
                    $.ajaxSetup({ cache: false });

                    $.getJSON(\"config.php?type=tool&quietmode=1&handler=file&module=endpointman&file=ajax_select.html.php&atype=model\",{id: value}, function(j){
                            var options = '';
                            for (var i = 0; i < j.length; i++) {
                                    options += '<option value=\"' + j[i].optionValue + '\">' + j[i].optionDisplay + '</option>';
                            }
                            $('#epm_model').html(options);
                            $('#epm_model option:first').attr('selected', 'selected');
                            $('#epm_temps').html('<option></option>');
                            $('#epm_temps option:first').attr('selected', 'selected');
                            $('#epm_line').html('<option></option>');
                            $('#epm_line option:first').attr('selected', 'selected');
                    })
                ";
            $currentcomponent->addjsfunc('brand_change(value)', $js);

            $section = _('End Point Manager');

            $sql = "SELECT mac_id,luid,line FROM endpointman_line_list WHERE ext = '".$extdisplay."' ";
            $line_info =& $db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
            if($line_info) {

                $js = "
                    $.ajaxSetup({ cache: false });
                    $.getJSON('config.php?type=tool&quietmode=1&handler=file&module=endpointman&file=ajax_select.html.php&atype=template2',{id: value}, function(j){
                            var options = '';
                            for (var i = 0; i < j.length; i++) {
                                    options += '<option value=\"' + j[i].optionValue + '\">' + j[i].optionDisplay + '</option>';
                            }
                            $('#epm_temps').html(options);
                            $('#epm_temps option:first').attr('selected', 'selected');
                    }),
                    $.ajaxSetup({ cache: false });
                    $.getJSON('config.php?type=tool&quietmode=1&handler=file&module=endpointman&file=ajax_select.html.php&macid='+ macid +'&atype=lines',{id: value}, function(j){
                            var options = '';
                            for (var i = 0; i < j.length; i++) {
                                    options += '<option value=\"' + j[i].optionValue + '\">' + j[i].optionDisplay + '</option>';
                            }
                            $('#epm_line').html(options);
                            $('#epm_line option:first').attr('selected', 'selected');
                    })
                ";
                $currentcomponent->addjsfunc('model_change(value,macid)', $js);

                $info = $endpoint->get_phone_info($line_info['mac_id']);
                
                $brand_list = $endpoint->brands_available($info['brand_id'], true);
                if(!empty($info['brand_id'])) {
                    $model_list = $endpoint->models_available(NULL,$info['brand_id']);
                    $line_list = $endpoint->linesAvailable($line_info['luid']);
                    $template_list = $endpoint->display_templates($info['product_id']);
                } else {
                    $model_list = array();
                    $line_list = array();
                    $template_list = array();
                }

                $checked = false;

                $currentcomponent->addguielem($section, new gui_checkbox('epm_delete', $checked, 'Delete','Delete this Extension from Endpoint Manager'),9);
                $currentcomponent->addguielem($section, new gui_textbox('epm_mac', $info['mac'], 'MAC Address', 'The MAC Address of the Phone Assigned to this Extension/Device. <br />(Leave Blank to Remove from Endpoint Manager)', '', 'Please enter a valid MAC Address', true, 17, false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_brand', $brand_list, $info['brand_id'], 'Brand', 'The Brand of this Phone.', false, 'frm_'.$display.'_brand_change(this.options[this.selectedIndex].value)', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_model', $model_list, $info['model_id'], 'Model', 'The Model of this Phone.', false, 'frm_'.$display.'_model_change(this.options[this.selectedIndex].value,\''.$line_info['luid'].'\')', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_line', $line_list, $line_info['line'], 'Line', 'The Line of this Extension/Device.', false, '', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_temps', $template_list, $info['template_id'], 'Template', 'The Template of this Phone.', false, '', false),9);
                $currentcomponent->addguielem($section, new gui_checkbox('epm_reboot', $checked, 'Reboot','Reboot this Phone on Submit'),9);
                
            } else {

                $js = "
                    $.ajaxSetup({ cache: false });
                    $.getJSON('config.php?type=tool&quietmode=1&handler=file&module=endpointman&file=ajax_select.html.php&atype=template2',{id: value}, function(j){
                            var options = '';
                            for (var i = 0; i < j.length; i++) {
                                    options += '<option value=\"' + j[i].optionValue + '\">' + j[i].optionDisplay + '</option>';
                            }
                            $('#epm_temps').html(options);
                            $('#epm_temps option:first').attr('selected', 'selected');
                    }),
                    $.ajaxSetup({ cache: false });
                    $.getJSON('config.php?type=tool&quietmode=1&handler=file&module=endpointman&file=ajax_select.html.php&mac='+ mac +'&atype=lines',{id: value}, function(j){
                            var options = '';
                            for (var i = 0; i < j.length; i++) {
                                    options += '<option value=\"' + j[i].optionValue + '\">' + j[i].optionDisplay + '</option>';
                            }
                            $('#epm_line').html(options);
                            $('#epm_line option:first').attr('selected', 'selected');
                    })
                ";
                $currentcomponent->addjsfunc('model_change(value,mac)', $js);

                $brand_list = $endpoint->brands_available(NULL, true);
                $model_list = array();
                $line_list = array();
                $template_list = array();

                $currentcomponent->addguielem($section, new gui_textbox('epm_mac', $info['mac'], 'MAC Address', 'The MAC Address of the Phone Assigned to this Extension/Device. <br />(Leave Blank to Remove from Endpoint Manager)', '', 'Please enter a valid MAC Address', true, 17, false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_brand', $brand_list, $info['brand_id'], 'Brand', 'The Brand of this Phone.', false, 'frm_'.$display.'_brand_change(this.options[this.selectedIndex].value)', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_model', $model_list, $info['model_id'], 'Model', 'The Model of this Phone.', false, 'frm_'.$display.'_model_change(this.options[this.selectedIndex].value,document.getElementById(\'epm_mac\').value)', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_line', $line_list, $line_info['line'], 'Line', 'The Line of this Extension/Device.', false, '', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_temps', $template_list, $info['template_id'], 'Template', 'The Template of this Phone.', false, '', false),9);
            }
        }
}

function endpointman_hookProcess_core($viewing_itemid, $request) {
}