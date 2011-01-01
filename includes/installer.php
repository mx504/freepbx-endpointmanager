<?PHP
/**
 * Endpoint Manager Install File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
require 'functions.inc';

$endpoint = new endpointmanager();

echo "<html><head><title>Installer</title></head><body>";

function out($text){
    echo $text."<br />";
}

if($_REQUEST['type'] == "brand") {
    $endpoint->download_brand($_REQUEST['id']);
} elseif($_REQUEST['type'] == "js-multiple") {
    $list = explode(",",$_REQUEST['id']);
    sort($list,SORT_STRING);

    foreach($list as $data) {

    }
} elseif($_REQUEST['type'] == "firmware") {
    $endpoint->install_firmware($_REQUEST['id']);
} elseif($_REQUEST['type'] == "manual_install") {
    switch($_REQUEST['install_type']) {
        case "upload_master_xml":
            if (file_exists(PHONE_MODULES_PATH."temp/master.xml")) {
                $handle = fopen(PHONE_MODULES_PATH."temp/master.xml", "rb");
                $contents = stream_get_contents($handle);
                fclose($handle);
                @$a = simplexml_load_string($contents);
                if($a===FALSE) {
                    echo "Not a valid xml file";
                    break;
                } else {
                    rename(PHONE_MODULES_PATH."temp/master.xml", PHONE_MODULES_PATH."master.xml");
                    echo "Move Successful<br />";
                    $endpoint->brand_update_check();
                    echo "Updating Brands<br />";
                }
            } else {
            }
            break;
        case "upload_provisioner":
            if (file_exists(PHONE_MODULES_PATH."temp/".$_REQUEST['package'])) {
                echo "Extracting Provisioner Package <br />";
                exec("tar -xvf ".PHONE_MODULES_PATH.'temp/'. $_REQUEST['package'] ." -C ".PHONE_MODULES_PATH."temp/");

                if(!file_exists(PHONE_MODULES_PATH."endpoint")) {
                    echo "Creating Provisioner Directory <br />";
                    mkdir(PHONE_MODULES_PATH."endpoint");
                }

                $endpoint_last_mod = filemtime(PHONE_MODULES_PATH."temp/endpoint/base.php");

                //rename(PHONE_MODULES_PATH."temp/setup.php", PHONE_MODULES_PATH."setup.php");

                rename(PHONE_MODULES_PATH."temp/endpoint/base.php", PHONE_MODULES_PATH."endpoint/base.php");

                echo "Updating Last Modified <br />";
                $sql = "UPDATE endpointman_global_vars SET value = '".$endpoint_last_mod."' WHERE var_name = 'endpoint_vers'";
                $endpoint->db->query($sql);
            }
            break;
        case "upload_brand":
            if ((file_exists(PHONE_MODULES_PATH."temp/".$_REQUEST['package'])) AND (file_exists(PHONE_MODULES_PATH."temp/".$_REQUEST['xml']))) {
                $temp = $endpoint->xml2array(PHONE_MODULES_PATH."temp/".$_REQUEST['xml']);
                $this->update_brand($temp);
            }
            break;
    }
}

echo "<hr>\n\t<a href=\"#\" onclick=\"parent.close_module_actions(true);\" style=\"text-decoration:none\" />"._("Return")."</a></body></html>";