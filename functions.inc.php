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
	global $currentcomponent;

        $display = isset($_REQUEST['display'])?$_REQUEST['display']:null;
        if($display == "extensions") {
            global $endpoint;

            $doc_root =	$_SERVER["DOCUMENT_ROOT"] ."/admin/modules/endpointman/";
            require($doc_root . "includes/functions.inc");

            $endpoint = new endpointmanager();
            ini_set('display_errors', 0);

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

	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;

	// Don't display this stuff it it's on a 'This xtn has been deleted' page.
	if ($action != 'del') {
            $sql = "SELECT mac_id,luid,line FROM endpointman_line_list WHERE ext = '".$extdisplay."' ";
            $line_info =& $db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
            if($line_info) {
                $info = $endpoint->get_phone_info($line_info['mac_id']);

                
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

                $section = _('End Point Manager');

                $brand_list = $endpoint->brands_available($info['brand_id'], false);
                $model_list = $endpoint->models_available(NULL,$info['brand_id']);
                $line_list = $endpoint->linesAvailable($line_info['luid']);
                $template_list = $endpoint->display_templates($info['product_id']);

                $currentcomponent->addguielem($section, new gui_textbox('epm_mac', $info['mac'], 'MAC Address', 'The MAC Address of the Phone Assigned to this Extension/Device.', 'isEmpty()', 'Please enter a valid MAC Address', true, 12, true),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_brand', $brand_list, $info['brand_id'], 'Brand', 'The Brand of this Phone.', false, 'frm_extensions_brand_change(this.options[this.selectedIndex].value)', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_model', $model_list, $info['model_id'], 'Model', 'The Model of this Phone.', false, 'frm_extensions_model_change(this.options[this.selectedIndex].value,\''.$line_info['luid'].'\')', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_line', $line_list, $line_info['line'], 'Line', 'The Line of this Extension/Device.', false, '', false),9);
                $currentcomponent->addguielem($section, new gui_selectbox('epm_temps', $template_list, $info['template_id'], 'Template', 'The Template of this Phone.', false, '', false),9);
            }
        }
}

function endpointman_hookProcess_core($viewing_itemid, $request) {
}