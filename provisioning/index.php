<?PHP

require('/etc/freepbx.conf');
require('/var/www/html/admin/modules/endpointman/includes/functions.inc');

$endpoint = new endpointmanager();

if((!isset($endpoint->global_cfg['server_type'])) OR ($endpoint->global_cfg['server_type'] != 'http')) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

if((isset($_SERVER["PATH_INFO"])) && ($_SERVER["PATH_INFO"] != '/') && (!empty($_SERVER["PATH_INFO"]))) {
	$requested_file = substr($_SERVER["PATH_INFO"], 1);
} elseif(isset($_REQUEST['request'])) {
	$requested_file = $_REQUEST['request'];
}

$path_parts = explode(".", $requested_file);
$path_parts2 = explode("_", $path_parts[0]);

$mac = $path_parts2[0];



define("PHONE_MODULES_DIR", "/var/www/html/admin/modules/_ep_phone_modules/");

require(PHONE_MODULES_DIR."servers/http_server.php");

$http_provisioner = new provisioner_http();

$http_provisioner->get($requested_file);
$http_provisioner->provisioner_path = PHONE_MODULES_DIR;

if(isset($http_provisioner->mac_address)) {
	$sql = 'SELECT id FROM `endpointman_mac_list` WHERE `mac` LIKE CONVERT(_utf8 \'%'.$http_provisioner->mac_address.'%\' USING latin1) COLLATE latin1_swedish_ci';

	$mac_id = $endpoint->db->getOne($sql);
	$phone_info = $endpoint->get_phone_info($mac_id);

        if($http_provisioner->load_provisioner($phone_info['directory'],$phone_info['cfg_dir'])) {
            //Determine if global settings have been overridden
            $settings = '';
            if($phone_info['template_id'] > 0) {
                if(isset($phone_info['template_data_info']['global_settings_override'])) {
                    $settings = unserialize($phone_info['template_data_info']['global_settings_override']);
                } else {
                    $settings['srvip'] = $endpoint->global_cfg['srvip'];
                    $settings['ntp'] = $endpoint->global_cfg['ntp'];
                    $settings['config_location'] = $endpoint->global_cfg['config_location'];
                    $settings['tz'] = $endpoint->global_cfg['tz'];
                }
            } else {
                if(isset($phone_info['global_settings_override'])) {
                    $settings = unserialize($phone_info['global_settings_override']);
                } else {
                    $settings['srvip'] = $endpoint->global_cfg['srvip'];
                    $settings['ntp'] = $endpoint->global_cfg['ntp'];
                    $settings['config_location'] = $endpoint->global_cfg['config_location'];
                    $settings['tz'] = $endpoint->global_cfg['tz'];
                }
            }

            //Tell the system who we are and were to find the data.
            $http_provisioner->provisioner_libary->root_dir = PHONE_MODULES_PATH;
            $http_provisioner->provisioner_libary->engine = 'asterisk';
            $http_provisioner->provisioner_libary->engine_location = $endpoint->global_cfg['asterisk_location'];
            $http_provisioner->provisioner_libary->system = 'unix';

            //have to because of versions less than php5.3
            $http_provisioner->provisioner_libary->brand_name = $phone_info['directory'];
            $http_provisioner->provisioner_libary->family_line = $phone_info['cfg_dir'];

            //Mac Address
            $http_provisioner->provisioner_libary->mac = $phone_info['mac'];

            //Phone Model (Please reference family_data.xml in the family directory for a list of recognized models)
            //This has to match word for word. I really need to fix this....
            $http_provisioner->provisioner_libary->model = $phone_info['model'];

            //Timezone
            $timezone_array = $endpoint->timezone_array();
            $tz = explode(".", $settings['tz']);
            $tz_key = $tz[0];
            $tz_subkey = $tz[1];
            $http_provisioner->provisioner_libary->timezone = $timezone_array[$tz_key]['offset'];

            //Network Time Server
            $http_provisioner->provisioner_libary->ntp = $settings['ntp'];

            //Server IP
            $http_provisioner->provisioner_libary->server[1]['ip'] = $settings['srvip'];
            $http_provisioner->provisioner_libary->server[1]['port'] = 5060;

            $temp = "";
            $template_data = unserialize($phone_info['template_data']);
            $global_user_cfg_data = unserialize($phone_info['global_user_cfg_data']);
            if($phone_info['template_id'] > 0) {
                $global_custom_cfg_data = unserialize($phone_info['template_data_info']['global_custom_cfg_data']);
                //Provide alternate Configuration file instead of the one from the hard drive
                if(!empty($phone_info['template_data_info']['config_files_override'])) {
                    $temp = unserialize($phone_info['template_data_info']['config_files_override']);
                    foreach($temp as $list) {
                        $sql = "SELECT original_name,data FROM endpointman_custom_configs WHERE id = ".$list;
                        $res = $endpoint->db->query($sql);
                        if($res->numRows()) {
                            $data = $endpoint->db->getRow($sql, array(),DB_FETCHMODE_ASSOC);
                            $http_provisioner->provisioner_libary->config_files_override[$data['original_name']] = $data['data'];
                        }
                    }
                }
            } else {
                $global_custom_cfg_data = unserialize($phone_info['global_custom_cfg_data']);
                //Provide alternate Configuration file instead of the one from the hard drive
                if(!empty($phone_info['config_files_override'])) {
                    $temp = unserialize($phone_info['config_files_override']);
                    foreach($temp as $list) {
                        $sql = "SELECT original_name,data FROM endpointman_custom_configs WHERE id = ".$list;
                        $res = $endpoint->db->query($sql);
                        if($res->numRows()) {
                            $data = $endpoint->db->getRow($sql, array(),DB_FETCHMODE_ASSOC);
                            $http_provisioner->provisioner_libary->config_files_override[$data['original_name']] = $data['data'];
                        }
                    }
                }
            }

            if (!empty($global_custom_cfg_data)) {
                if(array_key_exists('data', $global_custom_cfg_data)) {
                    $global_custom_cfg_ari = $global_custom_cfg_data['ari'];
                    $global_custom_cfg_data = $global_custom_cfg_data['data'];
                } else {
                    $global_custom_cfg_data = array();
                    $global_custom_cfg_ari = array();
                }
            }

            $new_template_data = array();
            $line_ops = array();
            if(is_array($global_custom_cfg_data)) {
                foreach($global_custom_cfg_data as $key => $data) {
                    $full_key = $key;
                    $key = explode('|',$key);
                    $count = count($key);
                    switch($count) {
                        case 1:
                            if(($endpoint->global_cfg['enable_ari'] == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
                                $new_template_data[$full_key] = $global_user_cfg_data[$full_key];
                            } else {
                                $new_template_data[$full_key] = $global_custom_cfg_data[$full_key];
                            }
                            break;
                        case 2:
                            $breaks = explode('_',$key[1]);
                            if(($endpoint->global_cfg['enable_ari'] == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
                                $new_template_data[$breaks[0]][$breaks[2]][$breaks[1]] = $global_user_cfg_data[$full_key];
                            } else {
                                $new_template_data[$breaks[0]][$breaks[2]][$breaks[1]] = $global_custom_cfg_data[$full_key];
                            }
                            break;
                        case 3:
                            if(($endpoint->global_cfg['enable_ari'] == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
                                $line_ops[$key[1]][$key[2]] = $global_user_cfg_data[$full_key];
                            } else {
                                $line_ops[$key[1]][$key[2]] = $global_custom_cfg_data[$full_key];
                            }
                            break;
                    }
                }
            }

            //Loop through Lines!
            foreach($phone_info['line'] as $line) {
                $http_provisioner->provisioner_libary->lines[$line['line']] = array('ext' => $line['ext'], 'secret' => $line['secret'], 'displayname' => $line['description']);
            }

            //testing this out
            foreach($line_ops as $key => $data) {
                if(isset($line_ops[$key])) {
                    $http_provisioner->provisioner_libary->lines[$key]['options'] = $line_ops[$key];
                }
            }

            $http_provisioner->provisioner_libary->server_type = 'dynamic';
            $http_provisioner->provisioner_libary->provisioning_type = 'http';
            $new_template_data['provisioning_path'] = "provisioning";

            //Set Variables according to the template_data files included. We can include different template.xml files within family_data.xml also one can create
            //template_data_custom.xml which will get included or template_data_<model_name>_custom.xml which will also get included
            //line 'global' will set variables that aren't line dependant
            $http_provisioner->provisioner_libary->options = $new_template_data;

            //Setting a line variable here...these aren't defined in the template_data.xml file yet. however they will still be parsed
            //and if they have defaults assigned in a future template_data.xml or in the config file using pipes (|) those will be used, pipes take precedence
            $http_provisioner->provisioner_libary->processor_info = "EndPoint Manager Version ".$endpoint->global_cfg['version'];

            if(!$http_provisioner->generate_config_data($requested_file)) {
                header("HTTP/1.0 404 Not Found");
            }
        }
}

if(isset($http_provisioner->final_data)) {
	echo $http_provisioner->final_data;
} else {
    if(file_exists($endpoint->global_cfg['config_location'].$requested_file)) {
        $handle = fopen($endpoint->global_cfg['config_location'].$requested_file, "rb");
        echo stream_get_contents($handle);
        fclose($handle);
    } else {
        header("HTTP/1.0 404 Not Found");
    }
}