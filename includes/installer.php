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

if(!function_exists("out")) {
    function out($text){
        echo $text."<br />";
    }
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
        case "export_brand":
            $sql = 'SELECT `name`, `directory` FROM `endpointman_brand_list` WHERE `id` = '.$_REQUEST['package'].'';
            $row = $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
            echo "Exporting ". $row['name']."<br/>";
            if(!file_exists(PHONE_MODULES_PATH."/temp/export/")) {
                mkdir(PHONE_MODULES_PATH."/temp/export/");
            }
            $time = time();
            exec("tar zcf ".PHONE_MODULES_PATH."temp/export/".$row['directory']."-".$time.".tgz --exclude .svn --exclude firmware -C ".PHONE_MODULES_PATH."/endpoint ".$row['directory']);
            echo "Done! Click this link to download:<a href='modules/_ep_phone_modules/temp/export/".$row['directory']."-".$time.".tgz'>Here</a>";
            break;
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

                rename(PHONE_MODULES_PATH."temp/endpoint/base.php", PHONE_MODULES_PATH."endpoint/base.php");

                echo "Updating Last Modified <br />";
                $sql = "UPDATE endpointman_global_vars SET value = '".$endpoint_last_mod."' WHERE var_name = 'endpoint_vers'";
                $endpoint->db->query($sql);
            }
            break;
        case "upload_brand":
            if (file_exists(PHONE_MODULES_PATH."temp/".$_REQUEST['package'])) {
                echo "Extracting Tarball........";
                exec("tar -xvf ".PHONE_MODULES_PATH.'temp/'. $_REQUEST['package'] ." -C ".PHONE_MODULES_PATH."temp/");
                echo "Done!<br />";

                $package = basename($_REQUEST['package'], ".tgz");                
                $package = explode("-",$package);

                if(file_exists(PHONE_MODULES_PATH."temp/".$package[0])) {
                    $endpoint->update_brand($package[0],FALSE);
                    unlink(PHONE_MODULES_PATH.'temp/'. $_REQUEST['package']);
                } else {
                    echo "Please name the Package the same name as your brand!";
                }       
            } else {
                $endpoint->error['upload'] = "No File Provided";
            }
            break;
    }
}

echo "<hr>\n\t<a href=\"#\" onclick=\"parent.close_module_actions(true);\" style=\"text-decoration:none\" />"._("Return")."</a></body></html>";