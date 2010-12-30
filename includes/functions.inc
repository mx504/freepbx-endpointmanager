<?php
/**
 * Endpoint Manager Functions File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
class endpointmanager {
    //Load this class upon construction of the class

    public $db;
    public $amp_conf;
    public $global_cfg;
    public $error;
    public $endpoint_data;
    public $tpl;

    /**
     *
     * @global array $amp_conf Data taken about FreePBX from FreePBX as a global, we move this into a public variable
     * @global object $db Pear DB information, we move this into a public variable
     */
    function __construct() {
        require('abstraction/freepbx.inc');
        $this->endpoint_data = new epm_data_abstraction();

        $this->global_cfg = $this->endpoint_data->get_stored_globals();

        $this->db = $this->endpoint_data->db;

        //TODO: Need to have this change when the user changes timezones, for now we keep it set to my timezone
        date_default_timezone_set('America/Los_Angeles');

                //Move all information from the global $amp_conf into our public space. This will help prevent security concerns
        $this->amp_conf = $amp_conf;

        //Generate empty array
        $this->error = array();

        //TODO: Possibly remove these as I don't believe anything uses them anymore
        define("UPDATE_PATH", $this->global_cfg['update_server']);
        define("VER", $this->global_cfg['version']);
        //end possible removal

        //Define the location of phone modules, keeping it outside of the module directory so that when the user updates endpointmanager they don't lose all of their phones
        define("PHONE_MODULES_PATH", dirname($_SERVER["SCRIPT_FILENAME"])."/modules/_ep_phone_modules/");

        //Get local path information
        define("WEB_PATH", dirname($_SERVER['SCRIPT_NAME'])."/modules/endpointman/");
        define("LOCAL_PATH", dirname($_SERVER["SCRIPT_FILENAME"])."/modules/endpointman/");

        //Define error reporting
        if($this->global_cfg['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            ini_set('display_errors', 0);
        }

        //Check if config location is writable and/or exists!
        if(isset($this->global_cfg['config_location'])) {
            if(is_dir($this->global_cfg['config_location'])) {
                if(!is_writeable($this->global_cfg['config_location'])) {
                    $user = exec('whoami');
                    $this->error['config_location'] = "Configuration Directory is not writable!".
                        "<br />Please change the location: <a href='config.php?type=tool&display=epm_advanced'>Here</a>".
                        "<br />Or run this command on SSH: 'chown -R ".$user.":".$user." ".$this->global_cfg['config_location']."' then 'chmod -R 777 ".$this->global_cfg['config_location']."'";
                }
            } else {
                $this->error['config_location'] = "Configuration Directory is not a directory or does not exist! Please change the location here: <a href='config.php?type=tool&display=epm_advanced'>Here</a>";
            }
        }

        //include the local template class
        require(LOCAL_PATH."includes/rain.tpl.class.php");

        $this->tpl = new RainTPL( LOCAL_PATH.'templates/freepbx', LOCAL_PATH.'templates/freepbx/compiled', '/admin/modules/endpointman/templates/images' );

    }

    /**
     * Used to send sample configurations to provisioner.net
     * NOTE: The user has to explicitly click a link that states they are sending the configuration to the project
     * We don't take configs on our own accord!!
     * @param <type> $brand Brand Directory
     * @param <type> $product Product Directory
     * @param <type> $orig_name The file's original name we are sending
     * @param <type> $data The config file's data
     */
    function submit_config($brand,$product,$orig_name,$data) {
        $posturl = 'http://www.provisioner.net/submit_config.php';
        $postvars = 'brand='.$brand.'&product='.$product.'&origname='.htmlentities(addslashes($orig_name)).'&config_data='.base64_encode($data);

        $ch = curl_init($posturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);  // DO NOT RETURN HTTP HEADERS
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // RETURN THE CONTENTS OF THE CALL, probably not needed
        $Rec_Data = curl_exec($ch);

        ob_start();
        header("Content-Type: text/html");
        $Final_Out=ob_get_clean();
        curl_close($ch);

    }

    /**
     * Displays the message box on any page if triggered.
     * @param string $message The text of the message
     * @param string $tpl Not used at this time
     * @param int $fatal_error Either 0 or 1, 1 would be red, 0 would be black
     */
    function display_message_box($message, $tpl, $fatal_error=0) {
        $tpl->assign("show_error_box", 1);
        $tpl->assign("advanced_debug", $this->global_cfg['debug']);
        $tpl->assign("fatal_error", $fatal_error);
        $tpl->assign("error_message", $message);
    }

    /**
     * A hacky way to add notifications into freepbx
     * @deprecated Supposed to remove
     * @param <type> $id
     * @param <type> $type
     * @param <type> $display_text
     * @param <type> $text
     * @param <type> $link
     */
    function add_freepbx_notification($id, $type, $display_text, $text, $link) {
        $sql = "INSERT INTO notifications (module, id, level, display_text, link, candelete, timestamp, extended_text) VALUES ('endpointman', '".$id."', '500', '".$display_text."', '".$link."', '1', '".time()."', '".$text."')";
        mysql_query($sql);
    }

    /**
     * Taken from PHP.net. A list of errors returned when uploading files.
     * @param <type> $error_code
     * @return string
     */
    function file_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Get the brand from any mac sent to this function
     * @param string $mac
     * @return array
     */
    function get_brand_from_mac($mac){
        //Check for valid mac address first
        if(!preg_match("/[0-9a-f][0-9a-f][:-][0-9a-f][0-9a-f][:-][0-9a-f][0-9a-f][:-][0-9a-f][0-9a-f][:-][0-9a-f][0-9a-f][:-][0-9a-f][0-9a-f]/i",$mac)) {
            return(FALSE);
        }

        //Get the OUI only
        $oui = substr($mac,0,6);
        //Find the matching brand model to the oui
        $oui_sql = "SELECT endpointman_brand_list.name, endpointman_brand_list.id FROM endpointman_oui_list, endpointman_brand_list WHERE oui LIKE '%". $oui ."%' AND endpointman_brand_list.id = endpointman_oui_list.brand AND endpointman_brand_list.installed = 1 LIMIT 1";
        $brand =& $this->db->getRow($oui_sql, array(), DB_FETCHMODE_ASSOC);

        $res =& $this->db->query($oui_sql);
        $brand_count = $res->numRows();

        if (!$brand_count) {
            //oui doesn't have a matching mysql reference, probably a PC/router/wap/printer of some sort.
           $phone_info['id'] = 0;
           $phone_info['name'] = _("Unknown");
        } else {
           $phone_info['id'] = $brand['id'];
           $phone_info['name'] = $brand['name'];
        }

        return($phone_info);
    }

    /**
     * Setup provisioner class from Provisioner.net
     *
     * Not used yet
     *
     * @param <type> $mac_id
     */
    function setup_provisioner($mac_id=NULL) {
        if(!class_exists('ProvisionerConfig')) {
            require(PHONE_MODULES_PATH.'setup.php');
        }
        $phone_info = $endpoint->get_phone_info($mac_id);

        if(is_array($phone_info['template_data'])) {
            $template_data = unserialize($phone_info['template_data']['custom_cfg_data']);
        } else {
            $template_data = unserialize($phone_info['custom_cfg_data']);
        }

        $new_template_data = array();
        if(is_array($template_data)) {
            foreach($template_data as $key => $data) {
                $new_template_data = array_merge($new_template_data, array($key => $data['value']));
            }
        }

        $class = "endpoint_" . $phone_info['directory'] . "_" . $phone_info['cfg_dir'] . '_phone';

        $provisioner_lib = new $class();

        $provisioner_lib->root_dir = PHONE_MODULES_PATH;

        $provisioner_lib->engine = 'asterisk';
        $provisioner_lib->system = 'unix';

        $provisioner_lib->options = $new_template_data;

        //have to because of versions less than php5.3
        $provisioner_lib->brand_name = $phone_info['directory'];
        $provisioner_lib->family_line = $phone_info['cfg_dir'];
    }

    /**
     * Send this function an ID from the mac devices list table and you'll get all the information we have on that particular phone
     * @param integer $mac_id ID number reference from the MySQL database referencing the table endpointman_mac_list
     * @return <type>
     */
    function get_phone_info($mac_id=NULL) {
        //You could screw up a phone if the mac_id is blank
        if (!isset($mac_id)) {
            $this->error['get_phone_info'] = "Mac ID is not set";
            return(FALSE);
        }
        $sql = "SELECT id FROM endpointman_mac_list WHERE model > 0 AND id =".$mac_id;

        $res =& $this->db->query($sql);
        if($res->numRows()) {
            //Returns Brand Name, Brand Directory, Model Name, Mac Address, Extension (FreePBX), Custom Configuration Template, Custom Configuration Data, Product Name, Product ID, Product Configuration Directory, Product Configuration Version, Product XML name,
            $sql = "SELECT endpointman_mac_list.config_files_override, endpointman_mac_list.global_user_cfg_data, endpointman_model_list.id as model_id, endpointman_brand_list.id as brand_id, endpointman_brand_list.name, endpointman_brand_list.directory, endpointman_model_list.model, endpointman_mac_list.mac, endpointman_mac_list.template_id, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.long_name, endpointman_product_list.id as product_id, endpointman_product_list.cfg_dir, endpointman_product_list.cfg_ver, endpointman_model_list.template_data, endpointman_model_list.enabled FROM endpointman_line_list, endpointman_mac_list, endpointman_model_list, endpointman_brand_list, endpointman_product_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_brand_list.id = endpointman_model_list.brand AND endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.id = ". $mac_id;

            $phone_info =& $this->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);

            if(!$phone_info) {
                $this->error['get_phone_info'] = "Error with SQL Statement";
            }

            //If there is a template associated with this phone then pull that information and put it into the array
            if ($phone_info['template_id'] > 0) {
                $sql = "SELECT name, global_custom_cfg_data, config_files_override FROM endpointman_template_list WHERE id = ".$phone_info['template_id'];

                $phone_info['template_data_info'] =& $this->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);

            }

            $sql = "SELECT endpointman_line_list.*, sip.data as secret, devices.* FROM endpointman_line_list, sip, devices WHERE endpointman_line_list.ext = devices.id AND endpointman_line_list.ext = sip.id AND sip.keyword = 'secret' AND mac_id = ".$mac_id." ORDER BY endpointman_line_list.line ASC";
            $lines_info =& $this->db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
            foreach($lines_info as $line) {
                $phone_info['line'][$line['line']] = $line;
            }


        } else {
            $sql = "SELECT id, mac, ext FROM endpointman_mac_list WHERE id =".$mac_id;
            //Phone is unknown, we need to display this to the end user so that they can make corrections
            $row =& $this->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);

            $brand = $this->get_brand_from_mac($row['mac']);
            if($brand) {
                $phone_info['brand_id'] = $brand['id'];
                $phone_info['name'] = $brand['name'];
            } else {
                $phone_info['brand_id'] = 0;
                $phone_info['name'] = 'Unknown';
            }

            $phone_info['id'] = $mac_id;
            $phone_info['model_id'] = 0;
            $phone_info['product_id'] = 0;
            $phone_info['custom_cfg_template'] = 0;
            $phone_info['mac'] = $row['mac'];
            $sql = "SELECT endpointman_line_list.*, sip.data as secret, devices.* FROM endpointman_line_list, sip, devices WHERE endpointman_line_list.ext = devices.id AND endpointman_line_list.ext = sip.id AND sip.keyword = 'secret' AND mac_id = ".$mac_id;
            $lines_info =& $this->db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
            foreach($lines_info as $line) {
                $phone_info['line'][$line['line']] = $line;
            }
        }

        //Final Output will look something similar to this
        /**Array
            (
                [config_files_override] =>
                [global_user_cfg_data] => N;
                [model_id] => 213
                [brand_id] => 2
                [name] => Grandstream
                [directory] => grandstream
                [model] => GXP2000
                [mac] => 000B820D0050
                [template_id] => 0
                [global_custom_cfg_data] => Serialized Data (Changed Template Values)
                [long_name] => GXP Enterprise IP series [280,1200,2000,2010,2020]
                [product_id] => 21
                [cfg_dir] => gxp
                [cfg_ver] => 1.5
                [template_data] => Serialized Data (The default Template Values)
                [enabled] => 1
                [line] => Array
                    (
                        [1] => Array
                            (
                                [luid] => 2
                                [mac_id] => 2
                                [line] => 1
                                [ext] => 1000
                                [description] => Description
                                [custom_cfg_data] =>
                                [user_cfg_data] =>
                                [secret] => secret
                                [id] => 1000
                                [tech] => sip
                                [dial] => SIP/1000
                                [devicetype] => fixed
                                [user] => 1000
                                [emergency_cid] =>
                            )

                    )

            )
        */
        return $phone_info;
    }

    /**
     * Custom Means specific to that MAC
     * id is either the mac ID (not address) or the template ID
     * @param integer $id
     * @param integer $custom
     */
    function edit_template_display($id, $custom) {
        $alt_configs = NULL;

        if($custom == 0) {
            $sql = "SELECT model_id FROM endpointman_template_list WHERE id=".$id;
        } else {
            $sql = "SELECT model FROM endpointman_mac_list WHERE id=".$id;
        }

        $model_id = $this->db->getOne($sql);

        //Make sure the model data from the local confg files are stored in the database and vice-versa. Serious errors will occur if the database is not in sync with the local file
        if(!$this->sync_model($model_id)) {
            die("unable to sync local template files - TYPE:". $custom);
        }


        //Determine if we are dealing with a general template or a specific [for that phone only] template (custom =0 means general)
        if($custom == 0) {
            $sql = "SELECT endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = ".$id;
        } else {
            $sql = "SELECT endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=".$id." AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
        }

        $row =& $this->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);

        $this->tpl->assign("template_editor_display", 1);

        echo $this->tpl->draw( 'global_header' );
        //Let the template system know if we are working with a general template or a specific [for that phone only] template
        $this->tpl->assign("custom", $custom);
        if($custom) {
            $this->tpl->assign("ext", $row['ext']);
        } else {
            $this->tpl->assign("template_name", $row['name']);
        }
        $this->tpl->assign("product", $row['short_name']);
        $this->tpl->assign("model", $row['model_name']);

        if($ma = $this->models_available($row['model_id'], NULL, $row['product_id'])) {
            $this->tpl->assign("models_ava", $ma);
        }

        $this->tpl->assign("area_ava", $this->areaAvailable($row['model_id']));
        //Start the display of the html file in the product folder
        if($row['config_files_override'] == "") {
            $config_files_saved = "";
        } else {
            $config_files_saved = unserialize($row['config_files_override']);
        }
        $config_files_list = explode(",",$row['config_files']);
        $i = 0;
        $alt = 0;

        $i=0;
        $b=0;
        $only_configs = array();
        foreach($config_files_list as $files) {
            $sql = "SELECT * FROM  endpointman_custom_configs WHERE product_id = '".$row['product_id']."' AND original_name = '".$files."'";
            $alt_configs_list_count =& $this->db->query($sql);
            if($alt_configs_list_count->numRows() > 0) {
                $alt_configs_list =& $this->db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
                $alt_configs[$i]['name'] = $files;
                $files = str_replace(".","_",$files);
                $h=0;
                foreach($alt_configs_list as $ccf) {
                    $alt_configs[$i]['list'][$h]['id'] = $ccf['id'];
                    $cf_key = $files;
                    if((isset($config_files_saved[$cf_key])) AND (is_array($config_files_saved)) AND ($config_files_saved[$cf_key] == $ccf['id'])) {
                        $alt_configs[$i]['list'][$h]['selected'] = 'selected';
                    }
                    $alt_configs[$i]['list'][$h]['name'] = $ccf['name'];
                    $h++;
                }
                $alt = 1;
            } else {
                $only_configs[$b]['name'] = $files;
                $b++;
            }
            $i++;
        }

        $this->tpl->assign("only_configs", $only_configs);
        $this->tpl->assign("alt_configs", $alt_configs);
        $this->tpl->assign("alt", $alt);

        if($row['template_data'] != "") {
            $out = $this->generate_gui_html($row['template_data'],$row['global_custom_cfg_data'],TRUE);
        } else {
            echo "No Template Data has been defined for this Product<br />";
        }
        $this->tpl->assign("web_path", WEB_PATH);
        $this->tpl->assign("template_editor", $out);
        $this->tpl->assign("hidden_id", $row['id']);
        $this->tpl->assign("hidden_custom", $custom);
        echo $this->tpl->draw( 'template_editor' );

        $this->tpl->assign("debug", "");

    }

    /**
     * Generates the Visual Display for the end user
     * @param <type> $cfg_data
     * @param <type> $custom_cfg_data
     * @param <type> $admin
     * @param <type> $user_cfg_data
     * @return <type>
     */
    function generate_gui_html($cfg_data,$custom_cfg_data=NULL, $admin=FALSE, $user_cfg_data=NULL) {
        //take the data out of the database and turn it back into an array for use
        $cfg_data = unserialize($cfg_data);

        $count = count($cfg_data);

        //Check to see if there is a custom template for this phone already listed in the endpointman_mac_list database
        if (isset($custom_cfg_data)) {
            $custom_cfg_data = unserialize($custom_cfg_data);
        } else {
            //No custom template so let's pull the default values for this model into the custom_cfg_data array and populate it from there so that we won't have to make two completely different functions below
            foreach($cfg_data as $key => $data) {
                if(($data['type'] != 'group') && ($data['type'] != 'break') && ($data['type'] != 'loop')) {
                    $key_default = str_replace('$','',$data['variable']);
                    if(!is_array($data['default_value'])) {
                        $custom_cfg_data[$key_default]['value'] = $data['default_value'];
                    } else {
                        $custom_cfg_data[$key_default]['value'] = "";
                    }
                }
            }
        }
        if(isset($user_cfg_data)) {
            $user_cfg_data = unserialize($user_cfg_data);
        }

        $template_variables_array = array();
        $group_count = 0;
        $variables_count = 0;
        foreach($cfg_data as $config_options) {
            if($admin) {
                //Administration View Only
                switch ($config_options['type']) {
                    case "group":
                        //We force the start of a new 'section' by increasing group_count and resetting variables_count to zero
                        $group_count++;
                        $variables_count = 0;
                        $template_variables_array[$group_count]['title'] = $config_options['description'];
                        continue 2;
                    case "loop":
                        //We force the start of a new 'section' by increasing group_count and resetting variables_count to zero
                        $group_count++;
                        $variables_count = 0;
                        $template_variables_array[$group_count]['title'] = $config_options['description'];
                        $loop_start = $config_options['loop_start'];
                        $loop_end = $config_options['loop_end'];
                        for($a=$loop_start;$a<=$loop_end;$a++) {
                            foreach($config_options['data']['item'] as $items) {
                                if(isset($items['description'])) {
                                    $items['description'] = str_replace('{$count}',$a,$items['description']);
                                    $key = str_replace('$','',$items['variable'])."_".$a;
                                    if(array_key_exists($key,$custom_cfg_data)) {
                                        $custom_cfg_data[$key]['value'] = $custom_cfg_data[$key][$a];
                                    } else {
                                        $custom_cfg_data[$key]['value'] = '';
                                    }
                                }
                                $items[$variables_count] = $items;
                                $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count,$items,$key,$custom_cfg_data,$admin,$user_cfg_data);
                                $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                $variables_count++;
                            }
                        }
                        continue 2;
                }
            } else {
                //ARI View Only
                $template_variables_array[$group_count]['title'] = "Your Phone Settings";
                switch ($config_options['type']) {
                    case "loop":
                        $template_variables_array[$group_count]['data'][$variables_count]['type'] = "break";
                        $variables_count++;
                        $loop_start = $config_options['loop_start'];
                        $loop_end = $config_options['loop_end'];
                        for($a=$loop_start;$a<=$loop_end;$a++) {
                            foreach($config_options['data']['item'] as $items) {
                                if(isset($items['description'])) {
                                    $items['description'] = str_replace('{$count}',$a,$items['description']);
                                    $key = str_replace('$','',$items['variable'])."_".$a;
                                    if(array_key_exists($key,$custom_cfg_data)) {
                                        $custom_cfg_data[$key]['value'] = $custom_cfg_data[$key][$a];
                                    } else {
                                        $custom_cfg_data[$key]['value'] = '';
                                    }
                                }
                                if(isset($custom_cfg_data[$key]['ari'])) {
                                    $items[$variables_count] = $items;
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count,$items,$key,$custom_cfg_data,$admin,$user_cfg_data);
                                    $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                    $variables_count++;
                                }
                            }
                        }
                        continue 2;
                }
            }
            //Both Views
            switch ($config_options['type']) {
                default:
                    if(array_key_exists('variable',$config_options)) {
                        $key = str_replace('$','',$config_options['variable']);
                        //TODO: Move this into the sync function
                        //Checks to see if values are defined in the database, if not then we assume this is a new option and we need a default value here!
                        if(!isset($custom_cfg_data[$key]['value'])) {
                            //xml2array will take values that have no data and turn them into arrays, we want to avoid the word 'array' as a default value, so we blank it out here if we are an array
                            if((array_key_exists('default_value',$config_options)) AND (is_array($config_options['default_value']))) {
                                $custom_cfg_data[$key]['value'] = "";
                            } elseif((array_key_exists('default_value',$config_options)) AND (!is_array($config_options['default_value']))) {
                                $custom_cfg_data[$key]['value'] = $config_options['default_value'];
                            }
                        }
                        $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count,$config_options,$key,$custom_cfg_data,$admin,$user_cfg_data);
                        $variables_count++;
                    }
                    break;
            }
            continue;
        }
        return($template_variables_array);
    }

    /**
     * Generate an array that will get parsed as HTML from an array of values from XML
     * @param int $i
     * @param array $cfg_data
     * @param string $key
     * @param array $custom_cfg_data
     * @return array
     */
    function generate_form_data ($i,$cfg_data,$key=NULL,$custom_cfg_data=NULL,$admin=FALSE,$user_cfg_data=NULL) {
        switch ($cfg_data['type']) {
            case "input":
                if((!$admin) && (isset($user_cfg_data[$key]['value']))) {
                    $custom_cfg_data[$key]['value'] = $user_cfg_data[$key]['value'];
                }
                $template_variables_array['type'] = "input";
                $template_variables_array['key'] = $key;
                $template_variables_array['value'] = $custom_cfg_data[$key]['value'];
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "radio":
                if((!$admin) && (isset($user_cfg_data[$key]['value']))) {
                    $custom_cfg_data[$key]['value'] = $user_cfg_data[$key]['value'];
                }
                $num = $custom_cfg_data[$key]['value'];
                $template_variables_array['type'] = "radio";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                $z = 0;
                while($z < count($cfg_data['data'])) {
                    $template_variables_array['data'][$z]['key'] = $key;
                    $template_variables_array['data'][$z]['value'] = $cfg_data['data'][$z]['value'];
                    $template_variables_array['data'][$z]['description'] = $cfg_data['data'][$z]['text'];
                    if ($cfg_data['data'][$z]['value'] == $num) {
                        $template_variables_array['data'][$z]['checked'] = 'checked';
                    }
                    $z++;
                }
                break;
            case "list":
                if((!$admin) && (isset($user_cfg_data[$key]['value']))) {
                    $custom_cfg_data[$key]['value'] = $user_cfg_data[$key]['value'];
                }
                $num = $custom_cfg_data[$key]['value'];
                $template_variables_array['type'] = "list";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                $z = 0;
                while($z < count($cfg_data['data'])) {
                    $template_variables_array['data'][$z]['value'] = $cfg_data['data'][$z]['value'];
                    $template_variables_array['data'][$z]['description'] = $cfg_data['data'][$z]['text'];
                    if ($cfg_data['data'][$z]['value'] == $num) {
                        $template_variables_array['data'][$z]['selected'] = 'selected';
                    }
                    $z++;
                }
                break;
            case "break":
               $template_variables_array['type'] = "break";
               break;
        }

        if(($this->global_cfg['enable_ari']) AND ($admin) AND ($cfg_data['type'] != "break") AND ($cfg_data['type'] != "group")) {
           
            $template_variables_array['aried'] = 1;
            $template_variables_array['ari']['key'] = $key;
            if(isset($custom_cfg_data[$key]['ari'])) {
                $template_variables_array['ari']['checked'] = "checked";
            }
        }
        return($template_variables_array);
    }

    /**
     * Save template from the template view pain
     * @param int $id Either the MAC ID or Template ID
     * @param int $custom Either 0 or 1, it determines if $id is MAC ID or Template ID
     * @param array $variables The variables sent from the form. usually everything in $_REQUEST[]
     * @return string Location of area to return to in Endpoint Manager
     */
    function save_template($id, $custom, $variables) {
        //Custom Means specific to that MAC
        //This function is reversed. Not sure why
        if($custom != "0") {
            $sql = "SELECT endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_product_list.id as product_id, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list WHERE endpointman_mac_list.id=".$id." AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
        } else {
            $sql = "SELECT endpointman_brand_list.directory, endpointman_product_list.cfg_dir, endpointman_product_list.config_files, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_brand_list, endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = ".$id;
        }

        //Load template data
        $row =& $this->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);

        $cfg_data = unserialize($row['template_data']);
        $count = count($cfg_data);
        for($i=0;$i<$count;$i++) {
            if(array_key_exists('variable',$cfg_data[$i])) {
                $temping = str_replace('$','',$cfg_data[$i]['variable']);
                $temping_ari = "ari_" . $temping;
                if(array_key_exists($temping, $_REQUEST)) {
                    $custom_cfg_data[$temping]['value'] = $_REQUEST[$temping];
                    if(array_key_exists($temping_ari, $_REQUEST)) {
                        if($_REQUEST[$temping_ari] == "on") {
                            $custom_cfg_data[$temping]['ari'] = 1;
                        }
                    }
                }
            } elseif ($cfg_data[$i]['type'] == 'loop') {
                $loop_start = $cfg_data[$i]['loop_start'];
                $loop_end = $cfg_data[$i]['loop_end'];
                $variables_count = 0;
                for($a=$loop_start;$a<=$loop_end;$a++) {
                    foreach($cfg_data[$i]['data']['item'] as $items) {
                        if(isset($items['description'])) {
                            $items['description'] = str_replace('{$count}',$a,$items['description']);
                            $temping = str_replace('$','',$items['variable'])."_".$a;
                            $temping_ari = "ari_" . $temping;
                            if(array_key_exists($temping, $_REQUEST)) {
                                $custom_cfg_data[$temping][$a] = $_REQUEST[$temping];
                                if(array_key_exists($temping_ari, $_REQUEST)) {
                                    if($_REQUEST[$temping_ari] == "on") {
                                        $custom_cfg_data[$temping]['ari'] = 1;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $config_files = explode(",",$row['config_files']);

        $i = 0;
        while($i < count($config_files)) {
            $config_files[$i] = str_replace(".","_",$config_files[$i]);
            if(isset($_REQUEST[$config_files[$i]])) {
                $_REQUEST[$config_files[$i]] = explode("_",$_REQUEST[$config_files[$i]], 2);
                $_REQUEST[$config_files[$i]] = $_REQUEST[$config_files[$i]][0];
                if($_REQUEST[$config_files[$i]] > 0) {
                    $config_files_selected[$config_files[$i]] = $_REQUEST[$config_files[$i]];
                }
            }
            $i++;
        }

        if(!isset($config_files_selected)) {
            $config_files_selected = "";
        } else {
            $config_files_selected = serialize($config_files_selected);
        }
        $save = serialize($custom_cfg_data);

        if($custom == "0") {
            $sql = 'UPDATE endpointman_template_list SET config_files_override = \''.addslashes($config_files_selected).'\', global_custom_cfg_data = \''.addslashes($save).'\' WHERE id ='.$id;
            $location = "template_manager";
        } else {
            $sql = 'UPDATE endpointman_mac_list SET config_files_override = \''.addslashes($config_files_selected).'\', template_id = 0, global_custom_cfg_data = \''.addslashes($save).'\' WHERE id ='.$id;
            $location = "devices_manager";
        }
  
        $this->db->query($sql);

        $phone_info = "";

        if($custom != 0) {
            $phone_info = $this->get_phone_info($id);
            $this->prepare_configs($phone_info);

        } else {
            $sql = 'SELECT id FROM endpointman_mac_list WHERE template_id = '.$id;
            $phones = $this->db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
            foreach($phones as $data) {
                $phone_info = $this->get_phone_info($data['id']);
                $this->prepare_configs($phone_info);
            }
        }

        return($location);

    }

    /**
     * Prepare and then send the data that Provisioner expects, then take what provisioner gives us and do what it says
     * @param array $phone_info Everything from get_phone_info
     */
    function prepare_configs($phone_info) {
        if(!class_exists('ProvisionerConfig')) {
            require(PHONE_MODULES_PATH.'setup.php');
        }

        //Load Provisioner
        $class = "endpoint_" . $phone_info['directory'] . "_" . $phone_info['cfg_dir'] . '_phone';
        $provisioner_lib = new $class();

        //Tell the system who we are and were to find the data.
        $provisioner_lib->root_dir = PHONE_MODULES_PATH;
        $provisioner_lib->engine = 'asterisk';
        $provisioner_lib->engine_location = $this->global_cfg['asterisk_location'];
        $provisioner_lib->system = 'unix';

        //have to because of versions less than php5.3
        $provisioner_lib->brand_name = $phone_info['directory'];
        $provisioner_lib->family_line = $phone_info['cfg_dir'];

        //Mac Address
        $provisioner_lib->mac = $phone_info['mac'];

        //Phone Model (Please reference family_data.xml in the family directory for a list of recognized models)
        //This has to match word for word. I really need to fix this....
        $provisioner_lib->model = $phone_info['model'];

        //Timezone
        $provisioner_lib->timezone = $this->global_cfg['tz'];

        //Server IP
        $provisioner_lib->server[1]['ip'] = $this->global_cfg['srvip'];
        $provisioner_lib->server[1]['port'] = 5060;

        //Provide alternate Configuration file instead of the one from the hard drive
        $temp = "";
        if(!empty($phone_info['config_files_override'])) {
            $temp = unserialize($phone_info['config_files_override']);
            foreach($temp as $list) {
                $sql = "SELECT original_name,data FROM endpointman_custom_configs WHERE id = ".$list;
                $res =& $this->db->query($sql);
                if($res->numRows()) {
                    $data = $this->db->getRow($sql, array(),DB_FETCHMODE_ASSOC);
                    $provisioner_lib->config_files_override[$data['original_name']] = $data['data'];
                }
            }
        }

        //Loop through Lines!
        foreach($phone_info['line'] as $line) {
            $provisioner_lib->lines[$line['line']] = array('ext' => $line['ext'], 'secret' => $line['secret'], 'displayname' => $line['description']);
        }
        $template_data = unserialize($phone_info['template_data']);
        $global_user_cfg_data = unserialize($phone_info['global_user_cfg_data']);

        if($phone_info['template_id'] > 0) {
            $global_custom_cfg_data = unserialize($phone_info['template_data_info']['global_custom_cfg_data']);
        } else {
            $global_custom_cfg_data = unserialize($phone_info['global_custom_cfg_data']);
        }

        $new_template_data = array();
        if(is_array($global_custom_cfg_data)) {
            foreach($global_custom_cfg_data as $key => $data) {
                if(array_key_exists('value', $data)) {
                    if((isset($template_data[$key]['ari'])) && (isset($global_user_cfg_data[$key]['value']))) {
                       $new_template_data[$key] = $global_user_cfg_data[$key]['value'];
                    } else {
                       $new_template_data[$key] = $data['value'];
                    }
                } else {
                    //Loop Detected!
                    $full_key = $key;
                    $key = explode('_',$key);
                    if((isset($global_custom_cfg_data[$full_key]['ari'])) && (isset($global_user_cfg_data[$full_key]['value']))) {
                        $new_template_data[$key[0]][$key[2]][$key[1]] = $global_user_cfg_data[$full_key]['value'];
                    } else {
                        $new_template_data[$key[0]][$key[2]][$key[1]] = $data[$key[2]];
                    }
                }
            }
        }

        //Set Variables according to the template_data files included. We can include different template.xml files within family_data.xml also one can create
        //template_data_custom.xml which will get included or template_data_<model_name>_custom.xml which will also get included
        //line 'global' will set variables that aren't line dependant
        $provisioner_lib->options = $new_template_data;

        //Setting a line variable here...these aren't defined in the template_data.xml file yet. however they will still be parsed
        //and if they have defaults assigned in a future template_data.xml or in the config file using pipes (|) those will be used, pipes take precedence
        $provisioner_lib->processor_info = "EndPoint Manager Version ".$this->global_cfg['version'];

        // Because every brand is an extension (eventually) of endpoint, you know this function will exist regardless of who it is
        //Start timer
        $time_start = microtime(true);
        $returned_data = $provisioner_lib->generate_config();
        //End timer
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        if($time > 360) {
            $this->error['generate_time'] = "It's taking a awfully long time to generate configs...(".round($time,2)." seconds)";
        }
        //Create Directory Structure (If needed)
        if(isset($provisioner_lib->directory_structure)) {
            foreach($provisioner_lib->directory_structure as $data) {
                $dir = $this->global_cfg['config_location'] . $data;
                if(!file_exists($dir)) {
                    mkdir($dir, 0777);
                }
            }
        }

        //Copy Files/Directories (If needed)
        if(isset($provisioner_lib->copy_files)) {
            foreach($provisioner_lib->copy_files as $data) {
                if(((file_exists($this->global_cfg['config_location'].$data)) AND (!in_array($data,$provisioner_lib->protected_files))) OR (!file_exists($this->global_cfg['config_location'].$data))) {
                    if(is_dir(PHONE_MODULES_PATH."endpoint/".$phone_info['directory']."/".$phone_info['cfg_dir']."/".$data)) {
                        if(!file_exists($this->global_cfg['config_location'].$data)) {
                            if (!@mkdir($this->global_cfg['config_location'].$data, 0777)) {
                                $this->error['parse_configs'] = "Could Not Create Directory: ".$data;
                            }
                        }
                        $dir_iterator = new RecursiveDirectoryIterator(PHONE_MODULES_PATH."endpoint/".$phone_info['directory']."/".$phone_info['cfg_dir']."/".$data."/");
                        $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
                        // could use CHILD_FIRST if you so wish
                        foreach ($iterator as $file) {
                            if(is_dir($file)) {
                                $dir = str_replace(PHONE_MODULES_PATH."endpoint/".$phone_info['directory']."/".$phone_info['cfg_dir']."/".$data."/", "", $file);
                                if(!file_exists($this->global_cfg['config_location'].$data."/".$dir)) {
                                    if (!@mkdir($this->global_cfg['config_location'].$data."/".$dir, 0777)) {
                                        $this->error['parse_configs'] = "Could Not Create Directory: ".$data."/".$dir;
                                    }
                                }
                            } else {
                                $dir = str_replace(PHONE_MODULES_PATH."endpoint/".$phone_info['directory']."/".$phone_info['cfg_dir']."/".$data."/", "", $file);
                                if(!@copy($file, $this->global_cfg['config_location'].$data."/".$dir)) {
                                   $this->error['parse_configs'] = "Could Not Copy File: ".$data."/".$dir;
                                } else {
                                    chmod($this->global_cfg['config_location'].$data."/".$dir, 0777);
                                }
                            }
                        }
                    } else {
                        copy(PHONE_MODULES_PATH."endpoint/".$phone_info['directory']."/".$phone_info['cfg_dir']."/".$data,$this->global_cfg['config_location'].$data);
                        chmod($this->global_cfg['config_location'].$data, 0777);
                    }
                }
            }
        }

        //Generate Files
        foreach($returned_data as $key => $data) {
            if(((file_exists($this->global_cfg['config_location'].$key)) AND (!in_array($key,$provisioner_lib->protected_files))) OR (!file_exists($this->global_cfg['config_location'].$key))) {
                $fp = fopen($this->global_cfg['config_location'].$key, 'w');
                fwrite($fp, $data);
                fclose($fp);
                if(!file_exists($this->global_cfg['config_location'].$key)) {
                    $this->error['parse_configs'] = "File not written to hard drive!";
                }
            }
        }
        $provisioner_lib->reboot();
    }

    /**
     * Check for new firmware on the servers
     * @param int $id Product ID
     * @return bool True on yes False on no
     */
    function firmware_update_check($id=NULL) {
        $sql = "SELECT * FROM  endpointman_product_list WHERE  id ='". $id."'";
        $row =& $this->db->getRow($sql,array(),DB_FETCHMODE_ASSOC);

        $sql = "SELECT directory FROM  endpointman_brand_list WHERE id =". $row['brand'];
        $brand_directory =& $this->db->getOne($sql);

        //config drive unknown!
        if ($row['cfg_dir'] == "") {
            return FALSE;
        } else {
            $temp = $this->xml2array(PHONE_MODULES_PATH."endpoint/".$brand_directory."/".$row['cfg_dir']."/family_data.xml");
            if((array_key_exists('data',$temp)) AND (!is_array($temp['data']['firmware_ver']))) {
                if($row['firmware_vers'] < $temp['data']['firmware_ver']) {
                    return $temp;
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }

    }

    /**
     * Check to see the status of the firmware locally (installed or not)
     * @param int $id
     * @return string
     */
    function firmware_local_check($id=NULL) {
        $sql = "SELECT * FROM  endpointman_product_list WHERE hidden = 0 AND id ='". $id ."'";
        $res =& $this->db->query($sql);

        if($res->numRows()) {
            $row =& $this->db->getRow($sql,array(),DB_FETCHMODE_ASSOC);

            $sql = "SELECT directory FROM  endpointman_brand_list WHERE hidden = 0 AND id =". $row['brand'];
            $brand_directory =& $this->db->getOne($sql);

            //config drive unknown!
            if ($row['cfg_dir'] == "") {
                return("nothing");
            } else {
                $temp = $this->xml2array(PHONE_MODULES_PATH."endpoint/".$brand_directory."/".$row['cfg_dir']."/family_data.xml");

                if((isset ($temp['data']['firmware_ver'])) AND (!is_array($temp['data']['firmware_ver']))) {
                    if($row['firmware_vers'] == "") {
                        return("install");
                    } else {
                        return("remove");
                    }
                } else {
                    return("nothing");
                }
            }
        } else {
            return("nothing");
        }
    }

    /**
     * Remove firmware from the Hard Drive
     * @param int $id Product ID
     */
    function remove_firmware($id) {
        $sql = "SELECT firmware_files FROM  endpointman_product_list WHERE  id =". $id;
        $files = $this->db->getOne($sql);

        $file_list = explode(",",$files);
        $i = 0;
        foreach($file_list as $file) {
            if(file_exists($this->global_cfg['config_location'].$file)) {
                unlink($this->global_cfg['config_location'].$file);
            }
        }
        $sql = 'UPDATE endpointman_product_list SET firmware_files = "", firmware_vers = "" WHERE id = '.$id;
        $this->db->query($sql);
    }

    /**
     * Install Firmware for the specified Product Line
     * @param <type> $product_id Product ID
     */
    function install_firmware($product_id) {
        $sql = 'SELECT endpointman_product_list.*, endpointman_brand_list.directory FROM endpointman_product_list, endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '.$product_id;
        $row =& $this->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
        $temp = $this->xml2array(PHONE_MODULES_PATH."endpoint/".$row['directory']."/".$row['cfg_dir']."/family_data.xml");
        if($temp['data']['firmware_ver'] > $row['firmware_vers']) {
            echo "<div style='float: left'>Downloading firmware...</div><div style='float: left' id=\"DivExample\">.</div>";
            $this->download_file_with_progress_bar(UPDATE_PATH.$row['directory']."/". $temp['data']['firmware_pkg'], PHONE_MODULES_PATH."temp/".$temp['data']['firmware_pkg']);
            echo "<script type='text/javascript'>document.getElementById('DivExample').innerHTML='Done!';</script><br />";
            if(!file_exists(PHONE_MODULES_PATH."endpoint/".$row['directory']."/".$row['cfg_dir']."/firmware")) {
                mkdir(PHONE_MODULES_PATH."endpoint/".$row['directory']."/".$row['cfg_dir']."/firmware");
            }
            echo "Installing Firmware...";
            exec("rm -Rf ".PHONE_MODULES_PATH."endpoint/".$row['directory']."/".$row['cfg_dir']."/firmware");
            mkdir(PHONE_MODULES_PATH."endpoint/".$row['directory']."/".$row['cfg_dir']."/firmware");
            exec("tar -xvf ".PHONE_MODULES_PATH.'temp/'. $temp['data']['firmware_pkg'] ." -C ".PHONE_MODULES_PATH."endpoint/".$row['directory']."/".$row['cfg_dir']);
            $i = 0;
            foreach (glob(PHONE_MODULES_PATH."endpoint/".$row['directory']."/".$row['cfg_dir']."/firmware/*.*") as $filename) {
                $file = basename($filename);
                $list[$i] = $file;
                if (!@copy($filename, $this->global_cfg['config_location'].$file)) {
                    echo "<BR />--Failed To Copy $file...<BR />";
                    $copy_error = TRUE;
                }
                $i++;
            }
            $list = implode(",", $list);
            $sql = "UPDATE endpointman_product_list SET firmware_vers = '".$temp['data']['firmware_ver']."', firmware_files = '".$list."' WHERE id = ". $row['id'];
            $this->db->query($sql);
            if(isset($copy_error)) {
                echo "Copy Error Detected! Aborting Install!";
                $this->remove_firmware($product_id);
                echo "<BR />Please Check Directory/Permissions";
            } else {
                echo "Done!";
            }
        } else {
            echo "Your Firmware is already up to date";

        }
    }

    /**
     * Fix arrays so that they don't return an empty array if the array is empty
     * Instead we will return an empty string
     * Also we fix the problem of wanting to get single arrays with keys but not getting them
     * @param array $array
     * @return array
     */
    function fix_single_array_keys($array) {
        if((empty($array[0])) AND (!empty($array))) {
            $array_n[0] = $array;
            return($array_n);
        } elseif(!empty($array)) {
            return($array);
        } else {
            return("");
        }
    }

    /**
     * Download the requested XML configuration files from the web
     * @param string $location full URL of the XML file
     * @param string $directory Directory to place the XML file in relation to provisioner.net
     * @return bool True if downloaded, false if failed.
     */
    function download_xml($location,$directory=NULL) {
        //If directory is not set then assume we are working with the master.xml file
        if(!isset($directory)) {
            $destination_file = PHONE_MODULES_PATH.'master.xml';
			$directory = "master";
        } else {
            if(!file_exists(PHONE_MODULES_PATH.'/'.$directory)) {
                mkdir(PHONE_MODULES_PATH.'/'.$directory,0764);
            }
            $destination_file = PHONE_MODULES_PATH.'/'.$directory.'/brand_data.xml';
        }
        $temp_file = PHONE_MODULES_PATH.'temp/'.$directory.'.xml';
        if($this->download_xml_file($location, $temp_file)) {
            $handle = fopen($temp_file, "rb");
            $contents = fread($handle, filesize($temp_file));
            fclose($handle);
            @$a = simplexml_load_string($contents);
            if($a===FALSE) {
                //Error with the internet....ABORRRTTTT THEEEEE DOWNLOAAAAADDDDDDDD! SCOTTYYYY!;
                unlink($temp_file);
                return(FALSE);
            } else {
                rename($temp_file, $destination_file);
                chmod($destination_file,0764);
                return(TRUE);
            }
        } else {
            return(FALSE);
        }
    }

    /**
     * Check for new packges for brands. These packages will include phone models and such which the user can remove if they want
     * This function will alos auto-update the provisioner.net library incase anything has changed
     * @return array An array of all the brands/products/models and information about what's  enabled, installed or otherwise
     */
    function brand_update_check() {
        $master_result = $this->download_xml(UPDATE_PATH . "master.xml");


        if(!$master_result) {
            $this->error['brand_update_check_master'] = "Not able to connect to repository. Using local master file instead.";
        }

        $temp = $this->xml2array(PHONE_MODULES_PATH.'master.xml');

        $endpoint_package = $temp['data']['package'];
        $endpoint_last_mod = $temp['data']['last_modified'];

        $sql = "SELECT value FROM endpointman_global_vars WHERE var_name LIKE 'endpoint_vers'";
        $data =& $this->db->getOne($sql);

        if(($data == "") OR ($data <= $endpoint_last_mod)) {
            if((!$master_result) OR (!$this->download_file_no_progress_bar(UPDATE_PATH.'/'.$endpoint_package, PHONE_MODULES_PATH."temp/".$endpoint_package))) {
                $this->error['brand_update_check_xml'] = "<br/>Not able to connect to repository. Using local Provisioner.net Package";
            } else {
                exec("tar -xvf ".PHONE_MODULES_PATH.'temp/'. $endpoint_package ." -C ".PHONE_MODULES_PATH."temp/");

                if(!file_exists(PHONE_MODULES_PATH."endpoint")) {
                    mkdir(PHONE_MODULES_PATH."endpoint");
                }

                unlink(PHONE_MODULES_PATH."temp/setup.php");
                rename(PHONE_MODULES_PATH."temp/endpoint/base.php", PHONE_MODULES_PATH."endpoint/base.php");

                $sql = "UPDATE endpointman_global_vars SET value = '".$endpoint_last_mod."' WHERE var_name = 'endpoint_vers'";
                $this->db->query($sql);
            }
        }


        $out = $temp['data']['brands'];

        //Assume that if we can't connect and find the master.xml file then why should we try to find every other file.
        if($master_result) {
            $row =& $this->db->getAll('SELECT * FROM  endpointman_brand_list WHERE id > 0', array(), DB_FETCHMODE_ASSOC);

            foreach($out as $data) {
                //TODO: Make this pull from the local directory
                $result = $this->download_xml(UPDATE_PATH .$data['directory']."/".$data['directory'].".xml","endpoint/".$data['directory']);
                if(!$result) {
                    $this->error['brand_update_check'] = "<br/>Not able to connect to repository. Using local brand [".$data['name']."] file instead.";
                }

                if(file_exists(PHONE_MODULES_PATH."endpoint/".$data['directory']."/brand_data.xml")) {
                    $temp = $this->xml2array(PHONE_MODULES_PATH."endpoint/".$data['directory']."/brand_data.xml");

                    $temp = $temp['data']['brands'];

                    $temp['oui_list']['oui'] = $this->fix_single_array_keys($temp['oui_list']['oui']);

                    foreach($temp['oui_list']['oui'] as $oui) {
                        $sql = "INSERT INTO endpointman_oui_list (`oui`, `brand`, `custom`) VALUES ('".$oui."', '".$temp['brand_id']."', '0')";
                        $this->db->query($sql);

                    }

                    $brand_name = $temp['directory'];
                    $version[$brand_name] = $temp['last_modified'];

                    $last_mod = "";

                    $temp['family_list']['family'] = $this->fix_single_array_keys($temp['family_list']['family']);

                    foreach($temp['family_list']['family'] as $list) {
                        $last_mod = max($last_mod, $list['last_modified']);
                    }
                    $last_mod = max($last_mod, $version[$brand_name]);

                    $version[$brand_name] = $last_mod;

                    if(!($this->arraysearchrecursive($brand_name, $row, 'directory'))) {
                        //insert row
                        $sql = "INSERT INTO endpointman_brand_list (id, name, directory, cfg_ver) VALUES ('".$temp['brand_id']."', '".$temp['name']."', '".$temp['directory']."', '".$version[$brand_name]."')";
                        $this->db->query($sql);
                    } else {
                        //in database already!
                    }
                } else {
                    $this->error['brand_update_check_local_file'] = "<br/>Error: No Local File for ".$data['name']."!<br /><br />Learn how to manually upload packages here (it's easy!): <a href='http://projects.colsolgrp.net/documents/28' target='_blank'>Click Here!</a>";
                }
            }

            foreach($row as $ava_brands) {
                $key = $this->arraysearchrecursive($ava_brands['directory'], $out, 'directory');
                if($key === FALSE) {
                    $this->remove_brand($ava_brands['id']);
                } else {
                    $key = $key[0];

                    $brand_name = $ava_brands['directory'];

                    if($ava_brands['cfg_ver'] < $version[$brand_name]) {
                        $out[$key]['update'] = 1;
                        $out[$key]['update_vers'] = $version[$brand_name];
                    } else {
                        $out[$key]['update'] = NULL;
                    }
                }
            }
        } else {
            $this->error['brand_update_check_master_file'] = "<br/>Aborting Brand Downloads. Can't Get Master File, Assuming Timeout Issues!<br /><br />Learn how to manually upload packages here (it's easy!): <a href='http://projects.colsolgrp.net/documents/28' target='_blank'>Click Here!</a>";
        }

        return $out;
    }

    /**
     * This will install or updated a brand package (which is the same thing to this)
     * Still needs way to determine when models move...perhaps another function?
     * @param <type> $id
     */
    function update_brand($id = NULL) {
        $row =& $this->db->getAll('SELECT * FROM  endpointman_brand_list WHERE id ='.$id, array(), DB_FETCHMODE_ASSOC);

        echo "Downloading Brand XML.....";
        $result = $this->download_xml(UPDATE_PATH .$row[0]['directory']. "/".$row[0]['directory'].".xml","endpoint/".$row[0]['directory']);
        if($result) {
            echo "Done!<br/>";

            $temp = $this->xml2array(PHONE_MODULES_PATH.'/endpoint/'.$row[0]['directory'].'/brand_data.xml');

            $brand_name = $temp['data']['brands']['name'];
            $brand_id = $temp['data']['brands']['brand_id'];
            $brand_version = $temp['data']['brands']['version'];
            $brand_last_mod = $temp['data']['brands']['last_modified'];
            $package = $temp['data']['brands']['package'];

            echo "<div style='float: left'>Downloading Brand Package...</div><div style='float: left' id=\"DivExample\">.</div>";
            $this->download_file_with_progress_bar(UPDATE_PATH.$row[0]['directory'].'/'.$package, PHONE_MODULES_PATH."temp/".$package);
            echo "<script type='text/javascript'>document.getElementById('DivExample').innerHTML='Done!';</script><br />";
            
            echo "Extracting Tarball........";
            exec("tar -xvf ".PHONE_MODULES_PATH.'temp/'. $package ." -C ".PHONE_MODULES_PATH."temp/");
            echo "Done!<br />";

            echo "Creating Directory Structure/Moving Files...............";
            $dir_iterator = new RecursiveDirectoryIterator(PHONE_MODULES_PATH."temp/".$row[0]['directory']."/");
            $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
            // could use CHILD_FIRST if you so wish

            foreach ($iterator as $file) {
                if(is_dir($file)) {
                    $dir = str_replace(PHONE_MODULES_PATH."temp/".$row[0]['directory']."/", "", $file);
                    if(!file_exists(PHONE_MODULES_PATH."endpoint/".$row[0]['directory']."/".$dir)) {
                        mkdir(PHONE_MODULES_PATH."endpoint/".$row[0]['directory']."/".$dir,0764);
                    }
                } else {
                    if(basename($file) != "brand_data.xml") {
                        $dir = str_replace(PHONE_MODULES_PATH."temp/".$row[0]['directory']."/", "", $file);
                        rename($file, PHONE_MODULES_PATH."endpoint/".$row[0]['directory']."/".$dir);
                        chmod(PHONE_MODULES_PATH."endpoint/".$row[0]['directory']."/".$dir,0764);
                    }
                }
            }
            echo "Done!<br />";

            echo "Removing Temporary Files..............";
            $this->deltree(PHONE_MODULES_PATH."temp/" .$row[0]['directory']);
            unlink(PHONE_MODULES_PATH.'temp/'. $package);
            echo "Done!<br />";

            $last_mod = "";
            $temp['data']['brands']['family_list']['family'] = $this->fix_single_array_keys($temp['data']['brands']['family_list']['family']);
            foreach($temp['data']['brands']['family_list']['family'] as $family_list) {
                echo "Updating Family Lines.................<br/>";
                $last_mod = max($last_mod, $family_list['last_modified']);

                $family_line_xml = $this->xml2array(PHONE_MODULES_PATH.'/endpoint/'.$row[0]['directory'].'/'.$family_list['directory'].'/family_data.xml');
                $data =& $this->db->getOne("SELECT id FROM endpointman_product_list WHERE id='".$brand_id.$family_line_xml['data']['id']."'", array(), DB_FETCHMODE_ASSOC);
                $short_name = preg_replace("/\[(.*?)\]/si", "", $family_line_xml['data']['name']);
                if($data) {
                    $sql = "UPDATE endpointman_product_list SET short_name = '".$short_name."', long_name = '".$family_line_xml['data']['name']."', cfg_ver = '".$family_line_xml['data']['version']."', config_files='".$family_line_xml['data']['configuration_files']."' WHERE id = '".$brand_id.$family_line_xml['data']['id']."'";
                } else {
                    $sql = "INSERT INTO endpointman_product_list (`id`, `brand`, `short_name`, `long_name`, `cfg_dir`, `cfg_ver`, `config_files`, `hidden`) VALUES ('".$brand_id.$family_line_xml['data']['id']."', '".$brand_id."', '".$short_name."', '".$family_line_xml['data']['name']."', '".$family_line_xml['data']['directory']."', '".$family_line_xml['data']['version']."','".$family_line_xml['data']['configuration_files']."', '0')";
                }

                $this->db->query($sql);
                $family_line_xml['data']['model_list'] = $this->fix_single_array_keys($family_line_xml['data']['model_list']);
                echo "--Updating Model Lines................<br/>";
                foreach($family_line_xml['data']['model_list'] as $model_list) {
                    if(is_array($model_list['template_data']['files'])) {
                        $template_list = implode(",",$model_list['template_data']['files']);
                    } else {
                        $template_list = $model_list['template_data']['files'];
                    }
                    $m_data =& $this->db->getOne("SELECT id FROM endpointman_model_list WHERE id='".$brand_id.$family_line_xml['data']['id'].$model_list['id']."'", array(), DB_FETCHMODE_ASSOC);
                    if($m_data) {
                        $sql = "UPDATE endpointman_model_list SET max_lines = '".$model_list['lines']."', model = '".$model_list['model']."', template_list = '".$template_list."' WHERE id = '".$brand_id.$family_line_xml['data']['id'].$model_list['id']."'";
                    } else {
                        $sql = "INSERT INTO endpointman_model_list (`id`, `brand`, `model`, `max_lines`, `product_id`, `template_list`, `enabled`, `hidden`) VALUES ('".$brand_id.$family_line_xml['data']['id'].$model_list['id']."', '".$brand_id."', '".$model_list['model']."', '".$model_list['lines']."', '".$brand_id.$family_line_xml['data']['id']."', '".$template_list."', '0', '0')";
                    }
                    $this->db->query($sql);
                }
            }

            $brand_version = max($last_mod, $brand_last_mod);
            echo "Updating data..........";
            $sql = "UPDATE endpointman_brand_list SET name = '".$brand_name."', cfg_ver = '".$brand_version."', installed = 1, hidden = 0 WHERE id = ".$id;
            $this->db->query($sql);
            echo "Done!<br/>";
            
        } else {
            echo "<br/>Error Connecting to the Package Repository. Module not installed. Please Try again later.<br/>You Can Also Manually Update The Repository By Downloading Files here: <a href='http://www.provisioner.net/release.html' target='_blank'>Release Repo</a><br />Then Use Manual Upload in Advanced Settings";
        }
    }

    /**
     * Remove the brand
     * @param int $id Brand ID
     */
    function remove_brand($id=NULL) {
        $brand_dir =& $this->db->getOne("SELECT directory FROM endpointman_brand_list WHERE id=".$id);

        $sql = "DELETE FROM endpointman_model_list WHERE brand = '". $id."'";
        $this->db->query($sql);

        $sql = "DELETE FROM endpointman_product_list WHERE brand = '". $id . "'";
        $this->db->query($sql);

        $sql = "DELETE FROM endpointman_oui_list WHERE brand = '". $id . "'";
        $this->db->query($sql);

        $this->deltree(PHONE_MODULES_PATH .$brand_dir);
        $sql = "DELETE FROM endpointman_brand_list WHERE id = ". $id;

        $this->db->query($sql);
    }

    /**
     * Sync the XML files (incuding all template files) from the hard drive with the database
     * @param int $model Model ID
     * @return boolean True on sync completed. False on sync failed
     */
    function sync_model($model=NULL) {

        //TODO: combine these queries

        $sql = "SELECT * FROM  endpointman_model_list WHERE id='".$model."'";

        $model_row =& $this->db->getRow($sql, array(),DB_FETCHMODE_ASSOC);

        $sql = "SELECT * FROM  endpointman_product_list WHERE id='".$model_row['product_id']."'";

        $product_row =& $this->db->getRow($sql, array(),DB_FETCHMODE_ASSOC);

        $sql = "SELECT * FROM  endpointman_brand_list WHERE id=".$model_row['brand'];


        $brand_row =& $this->db->getRow($sql, array(),DB_FETCHMODE_ASSOC);

        $family_line_xml = $this->xml2array(PHONE_MODULES_PATH.'/endpoint/'.$brand_row['directory'].'/'.$product_row['cfg_dir'].'/family_data.xml');

        if($product_row['cfg_ver'] <= $family_line_xml['data']['version']) {
            $key = $this->arraysearchrecursive($model_row['model'], $family_line_xml['data']['model_list'], 'model');

            if($key === FALSE) {
                $this->error['sync_model'] = "Can't locate model in family XML file";
                return(FALSE);
            } else {
                if(is_array($family_line_xml['data']['model_list'][$key[0]]['template_data']['files'])) {
                    $template_list = implode(",",$family_line_xml['data']['model_list'][$key[0]]['template_data']['files']);
                    $template_list_array = $family_line_xml['data']['model_list'][$key[0]]['template_data']['files'];
                } else {
                    $template_list = $family_line_xml['data']['model_list'][$key[0]]['template_data']['files'];
                    $template_list_array[0] = $family_line_xml['data']['model_list'][$key[0]]['template_data']['files'];
                }
            }

            $sql = "UPDATE endpointman_model_list SET max_lines = '".$family_line_xml['data']['model_list'][$key[0]]['lines']."', template_list = '".$template_list."' WHERE id = '".$model."'";
            $this->db->query($sql);

            $version = $family_line_xml['data']['version'];
            $long_name = $family_line_xml['data']['name'];
            $short_name = preg_replace("/\[(.*?)\]/si", "", $family_line_xml['data']['name']);
            $configuration_files = $family_line_xml['data']['configuration_files'];

            $sql = "UPDATE endpointman_product_list SET long_name = '".$template_list."', short_name = '".$short_name."' , cfg_ver = '".$version."', WHERE id = '".$product_row['id']."'";
            $this->db->query($sql);

            $template_data_array = array();
            foreach($template_list_array as $data) {
                if(file_exists(PHONE_MODULES_PATH.'/endpoint/'.$brand_row['directory'].'/'.$product_row['cfg_dir'].'/'.$data)) {
                    $template_data_xml = $this->xml2array(PHONE_MODULES_PATH.'/endpoint/'.$brand_row['directory'].'/'.$product_row['cfg_dir'].'/'.$data);
                    $template_data_xml = $this->fix_single_array_keys($template_data_xml['template_data']['item']);
                    $template_data_array = array_merge($template_data_array, $template_data_xml);
                }
            }

            if (file_exists(PHONE_MODULES_PATH.'/endpoint/'.$brand_row['directory'].'/'.$product_row['cfg_dir'].'/template_data_custom.xml')) {
                $template_data_multi = $this->xml2array(PHONE_MODULES_PATH.'/endpoint/'.$brand_row['directory'].'/'.$product_row['cfg_dir'].'/template_data_custom.xml');
                if($template_data_multi) {
                    $template_data_multi = $this->fix_single_array_keys($template_data_multi['template_data']['item']);
                    $template_data_array = array_merge($template_data_array, $template_data_multi);
                }
            }

            if (file_exists(PHONE_MODULES_PATH.'/endpoint/'.$brand_row['directory'].'/'.$product_row['cfg_dir'].'/template_data_' . $model_row['model'] . '_custom.xml')) {
                $template_data_multi = $this->xml2array(self::$modules_path . $this->brand_name . "/" . $this->family_line . "/template_data_" . $this->model . "_custom.xml");
                if($template_data_multi) {
                    $template_data_multi = $this->fix_single_array_keys($template_data_multi['template_data']['item']);
                    $template_data_array = array_merge($template_data_array, $template_data_multi);
                }
            }

            if(empty($template_data_array)) {
                $this->error['sync_model'] = "No Template Data Found";
                return(FALSE);
            }

            $sql = "UPDATE endpointman_model_list SET template_data = '".serialize($template_data_array)."' WHERE id = '".$model."'";
            $this->db->query($sql);
        }

        return(TRUE);
    }

    /**
     * Taken from http://www.php.net/manual/en/function.array-search.php#69232
     * search haystack for needle and return an array of the key path, FALSE otherwise.
     * if NeedleKey is given, return only for this key mixed ArraySearchRecursive(mixed Needle,array Haystack[,NeedleKey[,bool Strict[,array Path]]])
     * @author ob (at) babcom (dot) biz
     * @param mixed $Needle
     * @param array $Haystack
     * @param mixed $NeedleKey
     * @param bool $Strict
     * @param array $Path
     * @return array
     */
    function arraysearchrecursive($Needle,$Haystack,$NeedleKey="",$Strict=false,$Path=array()) {
        if(!is_array($Haystack))
            return false;
        foreach($Haystack as $Key => $Val) {
            if(is_array($Val)&&
                    $SubPath=$this->arraysearchrecursive($Needle,$Val,$NeedleKey,$Strict,$Path)) {
                $Path=array_merge($Path,Array($Key),$SubPath);
                return $Path;
            }
            elseif((!$Strict&&$Val==$Needle&&
                            $Key==(strlen($NeedleKey)>0?$NeedleKey:$Key))||
                    ($Strict&&$Val===$Needle&&
                            $Key==(strlen($NeedleKey)>0?$NeedleKey:$Key))) {
                $Path[]=$Key;
                return $Path;
            }
        }
        return false;
    }

    /**
     * cURL function to download files with a progress bar and echo output while downloading to the screen
     * @global <type> $ch
     * @global <type> $fout
     * @global <type> $file_size
     * @global <type> $downloaded
     * @global <type> $pkg_interface
     * @global <type> $progress_bar
     * @param <type> $url_file
     * @param <type> $destination_file
     * @return <type>
     */
    function download_file_with_progress_bar($url_file, $destination_file) {
        global $ch, $fout, $file_size, $downloaded, $pkg_interface, $progress_bar;
        set_time_limit(0);
        $progress_bar = 1;
        $file_size  = 1;
        $downloaded = 1;
        echo "&nbsp;";
        /* open destination file */
        $fout = fopen($destination_file, "wb");

        /*
		 *	Originally by Author: Keyvan Minoukadeh
		 *	Modified by Scott Ullrich to return Content-Length size
        */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_file);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'endpointmanager_read_header');
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'endpointmanager_read_body');
        curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($fout)
            fclose($fout);
        curl_close($ch);
        return ($http_code == 200) ? true : $http_code;
    }

    function download_file_no_progress_bar($url_file, $destination_file) {
        global $ch, $fout, $file_size, $downloaded, $pkg_interface, $progress_bar;
        set_time_limit(0);
        $progress_bar = 0;
        $file_size  = 1;
        $downloaded = 1;
        /* open destination file */
        $fout = fopen($destination_file, "wb");

        /*
		 *	Originally by Author: Keyvan Minoukadeh
		 *	Modified by Scott Ullrich to return Content-Length size
        */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_file);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'endpointmanager_read_header');
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'endpointmanager_read_body');
        curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($fout)
            fclose($fout);
        curl_close($ch);
        return ($http_code == 200) ? true : $http_code;
    }

    function download_xml_file($url_file, $destination_file) {
        global $ch, $fout, $file_size, $downloaded, $pkg_interface, $progress_bar;
        set_time_limit(0);
        $progress_bar = 0;
        $file_size  = 1;
        $downloaded = 1;
        /* open destination file */
        $fout = fopen($destination_file, "wb");

        /*
		 *	Originally by Author: Keyvan Minoukadeh
		 *	Modified by Scott Ullrich to return Content-Length size
        */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_file);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'endpointmanager_read_header');
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'endpointmanager_read_body');
        curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($fout)
            fclose($fout);
        curl_close($ch);
        return ($http_code == 200) ? true : $http_code;
    }

    //This function looks in common linux directories for system executable files. Like ARP & NMAP
    function find_exec($exec) {
        $usr_bin = glob("/usr/bin/".$exec);
        $usr_sbin = glob("/usr/sbin/".$exec);
        $sbin = glob("/sbin/".$exec);
        $bin = glob("/bin/".$exec);
        $etc = glob("/etc/".$exec);
        if(isset($usr_bin[0])) {
            return("/usr/bin/".$exec);
        } elseif(isset($usr_sbin[0])) {
            return("/usr/sbin/".$exec);
        } elseif(isset($sbin[0])) {
            return("/sbin/".$exec);
        } elseif(isset($bin[0])) {
            return("/bin/".$exec);
        } elseif(isset($etc[0])) {
            return("/etc/".$exec);
        } else {
            return($exec);
        }
    }

    /**
     * Delete Directory Tree.
     * @deprecated In favor of rmrf
     * @param string $dir Full Directory path to delete
     */
    function deltree($dir) {
        $this->rmrf($dir);
    }

    /**
     * A function to quickly remove a directory (works in seconds for a hundred thousand files)
     * @author http://www.php.net/manual/en/function.glob.php#93669
     * @param string $dir Full Directory path to delete
     */
    function rmrf($dir) {
        foreach (glob($dir) as $file) {
            if (is_dir($file)) {
                $this->rmrf("$file/*");
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }

    /**
     * Only used once in all of Endpoint Manager to determine if a table exists
     * @param <type> $table
     * @return <type>
     */
    function table_exists($table) {
        $sql = "SHOW TABLES FROM asterisk";
        $result = $this->db->getAll($sql);

        foreach($result as $row) {
            if ($row[0] == $table) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * xml2array() will convert the given XML text to an array in the XML structure.
     * @author http://www.php.net/manual/en/function.xml-parse.php#87920
     * @param sting $url the XML url (usually a local file)
     * @param boolean $get_attributes 1 or 0. If this is 1 the function will get the attributes as well as the tag values - this results in a different array structure in the return value.
     * @param string $priority Can be 'tag' or 'attribute'. This will change the way the resulting array sturcture. For 'tag', the tags are given more importance.
     * @return array The parsed XML in an array form.
     */
    function xml2array($url, $get_attributes = 1, $priority = 'tag') {
        $contents = "";
        if (!function_exists('xml_parser_create')) {
            return array ();
        }
        $parser = xml_parser_create('');
        if(!($fp = @ fopen($url, 'rb'))) {
            return array ();
        }
        while(!feof($fp)) {
            $contents .= fread($fp, 8192);
        }
        fclose($fp);
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);
        if(!$xml_values) {
            return; //Hmm...
        }
        $xml_array = array ();
        $parents = array ();
        $opened_tags = array ();
        $arr = array ();
        $current = & $xml_array;
        $repeated_tag_index = array ();
        foreach ($xml_values as $data) {
            unset ($attributes, $value);
            extract($data);
            $result = array ();
            $attributes_data = array ();
            if (isset ($value)) {
                if($priority == 'tag') {
                    $result = $value;
                }
                else {
                    $result['value'] = $value;
                }
            }
            if(isset($attributes) and $get_attributes) {
                foreach($attributes as $attr => $val) {
                    if($priority == 'tag') {
                        $attributes_data[$attr] = $val;
                    }
                    else {
                        $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
                    }
                }
            }
            if ($type == "open") {
                $parent[$level -1] = & $current;
                if(!is_array($current) or (!in_array($tag, array_keys($current)))) {
                    $current[$tag] = $result;
                    if($attributes_data) {
                        $current[$tag . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    $current = & $current[$tag];
                }
                else {
                    if (isset ($current[$tag][0])) {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                        $repeated_tag_index[$tag . '_' . $level]++;
                    }
                    else {
                        $current[$tag] = array($current[$tag],$result);
                        $repeated_tag_index[$tag . '_' . $level] = 2;
                        if(isset($current[$tag . '_attr'])) {
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset ($current[$tag . '_attr']);
                        }
                    }
                    $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                    $current = & $current[$tag][$last_item_index];
                }
            }
            else if($type == "complete") {
                if(!isset ($current[$tag])) {
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if($priority == 'tag' and $attributes_data) {
                        $current[$tag . '_attr'] = $attributes_data;
                    }
                }
                else {
                    if (isset ($current[$tag][0]) and is_array($current[$tag])) {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                        if ($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag . '_' . $level]++;
                    }
                    else {
                        $current[$tag] = array($current[$tag],$result);
                        $repeated_tag_index[$tag . '_' . $level] = 1;
                        if ($priority == 'tag' and $get_attributes) {
                            if (isset ($current[$tag . '_attr'])) {
                                $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                                unset ($current[$tag . '_attr']);
                            }
                            if ($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                    }
                }
            }
            else if($type == 'close') {
                $current = & $parent[$level -1];
            }
        }
        return ($xml_array);
    }

    /**
     * This function takes a string and tries to determine if it's a valid mac addess, return FALSE if invalid
     * @param string $mac The full mac address
     * @return mixed The cleaned up MAC is it was a MAC or False if not a mac
     */
    function mac_check_clean($mac) {
        if ((strlen($mac) == "17") OR (strlen($mac) == "12")) {
            //It might be better to use switch here instead of these IF statements...

            //Is the mac separated by colons(:)?
            if (preg_match("/[0-9a-f][0-9a-f][:-]".
            "[0-9a-f][0-9a-f][:-]".
            "[0-9a-f][0-9a-f][:-]".
            "[0-9a-f][0-9a-f][:-]".
            "[0-9a-f][0-9a-f][:-]".
            "[0-9a-f][0-9a-f]/i", $mac)) {
                return(strtoupper(str_replace(":", "", $mac)));
                //Is the string exactly 12 characters?
            } elseif(strlen($mac) == "12") {
                //Now is the string a valid HEX mac address?
                if (preg_match("/[0-9a-f][0-9a-f]".
                "[0-9a-f][0-9a-f]".
                "[0-9a-f][0-9a-f]".
                "[0-9a-f][0-9a-f]".
                "[0-9a-f][0-9a-f]".
                "[0-9a-f][0-9a-f]/i", $mac)) {
                    return(strtoupper($mac));
                } else {
                    return(FALSE);
                }
                //Is the mac separated by whitespaces?
            } elseif(preg_match("/[0-9a-f][0-9a-f][\s]".
            "[0-9a-f][0-9a-f][\s]".
            "[0-9a-f][0-9a-f][\s]".
            "[0-9a-f][0-9a-f][\s]".
            "[0-9a-f][0-9a-f][\s]".
            "[0-9a-f][0-9a-f]/i", $mac)) {
                return(strtoupper(str_replace(" ", "", $mac)));
            } else {
                return(FALSE);
            }
        } else {
            return(FALSE);
        }
    }

    /**
     * Check for valid netmast to avoid security issues
     * @param string $mask the complete netmask, eg [1.1.1.1/24]
     * @return boolean True if valid, False if not
     */
    function validate_netmask($mask) {
        if (preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/", $mask)) {
            return(TRUE);
        } else {
            return(FALSE);
        }
    }

    /**
     * Discover New Device/Hardware
     * nmap will actually discover 'unseen' devices that the VoIP server hasn't heard from
     * If the user just wishes to use the local arp cache they can tell the function to not use nmap
     * This results in a speed increase from 60 seconds to less than one second.
     * @author tm1000
     * @version 1.5
     * @param mixed $netmask The netmask, eg [1.1.1.1/24]
     * @param boolean $use_nmap True use nmap, false don't use it
     * @return array List of devices found on the network
     */
    function discover_new($netmask, $use_nmap=TRUE) {

        if (($use_nmap) AND (file_exists($this->global_cfg['nmap_location'])) AND ($this->validate_netmask($netmask))) {
            shell_exec($this->global_cfg['nmap_location'].' -v -sP '. $netmask);
        } elseif(!$this->validate_netmask($netmask)) {
            $this->error['discover_new'] = "Invalid Netmask";
            return(FALSE);
        } elseif(!file_exists($this->global_cfg['nmap_location'])) {
            $this->error['discover_new'] = "Could Not Find NMAP, Using ARP Only";
            //return(FALSE);
        }
        //Get arp list
        $arp_list = shell_exec($this->global_cfg['arp_location'] . " -an");

        //Throw arp list into an array, break by new lines
        $arp_array = explode("\n", $arp_list);


        //Find all references to active computers by searching out mac addresses.
        $temp = array_values(array_unique(preg_grep("/[0-9a-f][0-9a-f][:-]".
                "[0-9a-f][0-9a-f][:-]".
                "[0-9a-f][0-9a-f][:-]".
                "[0-9a-f][0-9a-f][:-]".
                "[0-9a-f][0-9a-f][:-]".
                "[0-9a-f][0-9a-f]/i", $arp_array)));

        //Go through each row of valid arp entries and pull out the information and add it into a nice array!
        foreach ($temp as $key => &$value) {

            //Pull out the IP address from row. It's always the first entry in the row and it can only be a max of 15 characters with the delimiters
            preg_match_all("/\((.*?)\)/",$value,$matches);
            $ip = $matches[1];
            $ip = $ip[0];

            //Pull out the mac address by looking for the delimiter
            $mac = substr($value, (strpos($value, ":") -2), 17);

            //Get rid of the delimiter
            $mac_strip = strtoupper(str_replace(":", "", $mac));

            //arp -n will return a MAC address of 000000000000 if no hardware was found, so we need to ignore it
            if($mac_strip != "000000000000") {
                //only use the first 6 characters for the oui: http://en.wikipedia.org/wiki/Organizationally_Unique_Identifier
                $oui = substr($mac_strip,0,6);

                //Find the matching brand model to the oui
                $oui_sql = "SELECT endpointman_brand_list.name, endpointman_brand_list.id FROM endpointman_oui_list, endpointman_brand_list WHERE oui LIKE '%". $oui ."%' AND endpointman_brand_list.id = endpointman_oui_list.brand AND endpointman_brand_list.installed = 1 LIMIT 1";

                $brand =& $this->db->getRow($oui_sql, array(), DB_FETCHMODE_ASSOC);
                
                $res =& $this->db->query($oui_sql);
                $brand_count = $res->numRows();
                
                if (!$brand_count) {
                    //oui doesn't have a matching mysql reference, probably a PC/router/wap/printer of some sort.
                    $brand['name'] = FALSE;
                    $brand['id'] = NULL;
                }

                //Find out if endpoint has already been configured for this mac address
                $epm_sql = "SELECT * FROM endpointman_mac_list WHERE mac LIKE  '%". $mac_strip ."%'";
                $epm_row =& $this->db->getRow($epm_sql, array(), DB_FETCHMODE_ASSOC);

                $res =& $this->db->query($epm_sql);
                $epm_count = $res->numRows();

                if ($epm_count) {
                    $epm = TRUE;
                } else {
                    $epm = FALSE;
                }

                //Add into a final array
                $final[$key] = array("ip" => $ip, "mac" => $mac, "mac_strip" => $mac_strip, "oui" => $oui, "brand" => $brand['name'], "brand_id" => $brand['id'], "endpoint_managed" => $epm);
            }
        }

        $final = array_values($final);

        if(!is_array($final)) {
            return(FALSE);
        } else {
            return ($final);
        }
    }

    function areaAvailable($model,$area=NULL) {

        $temp[0]['value'] = 'GLOBAL';
        $temp[0]['text'] = 'GLOBAL';
        $temp[0]['selected'] = 0;

        $temp[1]['value'] = 'ALL';
        $temp[1]['text'] = 'ALL LINES';
        $temp[1]['selected'] = 0;

        $sql = "SELECT max_lines FROM endpointman_model_list WHERE id = '". $model."'";
        $count = $this->db->getOne($sql);

        //TODO: FIX this
        /**
        if($count == 0) {
           $this->sync_model($model);
           $sql = "SELECT max_lines FROM endpointman_model_list WHERE id = '". $model."'";
           $count = $this->db->getOne($sql);
           if($count == 0) {
               die("Can't Reset Lines to more than 0! ['areaAvailble']");
           }
        }
         * 
         */
        for($z=0;$z<$count;$z++) {
            $result[$z]['id'] = $z + 1;
            $result[$z]['model'] = $z + 1;
        }

        $i = 2;
        foreach($result as $row) {
            if ($row['id'] == $model) {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = 'Line '.$row['model'];
                $temp[$i]['selected'] = 'selected';
            }else {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = 'Line '.$row['model'];
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        return($temp);
    }

    /**
     *
     * @param integer $model model ID
     * @param integer $brand brand ID
     * @param integer $product product ID
     * @return array
     */
    function models_available($model=NULL, $brand=NULL, $product=NULL) {
        
        if ((!isset($oui)) && (!isset($brand)) && (!isset($model))) {
            $result1 =& $this->endpoint_data->all_models();
        }elseif((isset($brand)) && ($brand !=0)) {
            $result1 =& $this->endpoint_data->all_models_by_brand($brand);
        }elseif((isset($product)) && ($product !=0)) {
            $result1 =& $this->endpoint_data->all_models_by_product($product);
        } else {
            $result1 =& $this->endpoint_data->all_models();
        }

        $i = 1;
        foreach($result1 as $row) {
            if ($row['id'] == $model) {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 'selected';
            }else {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        if(!isset($temp)) {
            $this->error['modelsAvailable'] = "You need to enable at least ONE model";
            return(FALSE);
        } else {
            return($temp);
        }
    }

    function in_array_recursive($needle, $haystack) {

        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack));

        foreach($it AS $element) {
            if($element == $needle) {
                return TRUE;
            }
        } 
        return FALSE;
    }

    function linesAvailable($lineid=NULL,$macid=NULL) {
        if(isset($lineid)) {
            $sql="SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT endpointman_mac_list.model FROM endpointman_mac_list, endpointman_line_list WHERE endpointman_line_list.luid = ".$lineid." AND endpointman_line_list.mac_id = endpointman_mac_list.id)";

            $sql_l = "SELECT line, mac_id FROM `endpointman_line_list` WHERE luid = ".$lineid;
            $line =& $this->db->getRow($sql_l, array(), DB_FETCHMODE_ASSOC);

            $sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = ".$line['mac_id'];
        } elseif(isset($macid)) {
            $sql="SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT model FROM endpointman_mac_list WHERE id =".$macid.")";
            $sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = ".$macid;
            
            $line['line'] = 0;
        }

        $max_lines = $this->db->getOne($sql);
        $lines_used =& $this->db->getAll($sql_lu);

        for($i = 1; $i <= $max_lines; $i++) {    
            if($i == $line['line']) {
                $temp[$i]['value'] = $i;
                $temp[$i]['text'] = $i;
                $temp[$i]['selected'] = "selected";
            } else {
                if(!$this->in_array_recursive($i,$lines_used)) {
                    $temp[$i]['value'] = $i;
                    $temp[$i]['text'] = $i;
                }
            }
        }
        if(isset($temp)) {
            return($temp);
        } else {
            return FALSE;
        }
    }

    /**
     * Display all unused registrations from whatever manager we are using!
     * @return <type>
     */
    function display_registration_list($line_id=NULL) {

        if(isset($line_id)) {
            $result =& $this->endpoint_data->all_unused_registrations();
            $line_data =& $this->endpoint_data->get_line_information($line_id);
        } else {
            $result =& $this->endpoint_data->all_unused_registrations();
            $line_data = NULL;
        }

        $i = 1;
        $temp = array();
        foreach($result as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['id'] . " --- " . $row['description'];
            $i++;
        }

        if(isset($line_data)) {
            $temp[$i]['value'] = $line_data['ext'];
            $temp[$i]['text'] = $line_data['ext'] . " --- " . $line_data['description'];
            $temp[$i]['selected'] = "selected";
        }

        return($temp);

    }
    /**
     * Returns list of Brands that are installed and not hidden and that have at least one model enabled under them
     * @param integer $selected ID Number of the brand that is supposed to be selected in a drop-down list box
     * @return array Number array used to generate a select box
     */
    function brands_available ($selected = NULL,$show_blank=TRUE) {
        $data =& $this->endpoint_data->all_active_brands();
        if($show_blank) {
            $temp[0]['value'] = "";
            $temp[0]['text'] = "";
            $i = 1;
        } else {
            $i = 0;
        }
        foreach($data as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['name'];
            if ($row['id'] == $selected) {
                $temp[$i]['selected'] = TRUE;
            } else {
                $temp[$i]['selected'] = NULL;
            }
            $i++;
        }
        return($temp);
    }

    function display_templates($product_id,$temp_select = NULL) {
        $i = 0;
        $sql="SELECT id FROM  endpointman_product_list WHERE endpointman_product_list.id ='".$product_id."'";

        $id =& $this->db->getOne($sql);

        $sql="SELECT * FROM  endpointman_template_list WHERE  product_id = '".$id."'";

        $data =& $this->db->getAll($sql,array(), DB_FETCHMODE_ASSOC);
        foreach($data as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['name'];
            if ($row['id'] == $temp_select) {
                $temp[$i]['selected'] = "selected";
            }
            $i++;
        }
        $temp[$i]['value'] = 0;
        if ($temp_select == 0) {
            $temp[$i]['text'] = "Custom...";
            $temp[$i]['selected'] = "selected";
        } else {
            $temp[$i]['text'] = "Custom...";
        }

        return($temp);
    }

    function listTZ($selected) {
        $sql="SELECT tz FROM endpointman_time_zones";
        $data =& $this->db->getAll($sql,array(), DB_FETCHMODE_ASSOC);
        $i = 0;
        foreach($data as $row) {
            if ($row['tz'] == $selected) {
                $temp[$i]['value'] = $row['tz'];
                $temp[$i]['text'] = $row['tz'];
                $temp[$i]['selected'] = 1;
            }else {
                $temp[$i]['value'] = $row['tz'];
                $temp[$i]['text'] = $row['tz'];
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }
        return($temp);
    }

}

function endpointman_flush_buffers(){
    ob_end_flush();
    //ob_flush();
    flush();
    ob_start();
}

function endpointman_update_progress_bar($out) {
    echo '<script type="text/javascript">document.getElementById(\'DivExample\').innerHTML="%'.$out.'";</script>';
}

function endpointmanager_read_header($ch, $string) {
    global $file_size, $fout;
    $length = strlen($string);
    $regs = "";
    preg_match("/(Content-Length:) (.*)/i", $string, $regs);
    if((isset($regs[2])) AND ($regs[2] <> "")) {
        $file_size = intval($regs[2]);
    }
    //ob_flush();
    endpointman_flush_buffers();
    return $length;
}

function endpointmanager_read_body($ch, $string) {
    global $fout, $file_size, $downloaded, $lastseen, $progress_bar;
    $length = strlen($string);
    $downloaded += intval($length);
    $downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
    $downloadProgress = 100 - $downloadProgress;
    if($lastseen <> $downloadProgress and $downloadProgress < 101) {
        if($progress_bar) {
            endpointman_update_progress_bar($downloadProgress);
        }
        $lastseen = $downloadProgress;
    }
    if($fout)
        fwrite($fout, $string);
    //ob_flush();
    endpointman_flush_buffers();
    return $length;
}