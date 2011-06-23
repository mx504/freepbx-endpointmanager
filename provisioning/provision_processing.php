<?PHP

require('/etc/freepbx.conf');
require('/var/www/html/admin/modules/endpointman/includes/functions.inc');

$endpoint = new endpointmanager();

$path_parts = explode(".", $_REQUEST['request']);
$path_parts2 = explode("_", $path_parts[0]);

$mac = $path_parts2[0];

$sql = 'SELECT id FROM `endpointman_mac_list` WHERE `mac` LIKE CONVERT(_utf8 \'%'.$mac.'%\' USING latin1) COLLATE latin1_swedish_ci';

$mac_id = $endpoint->db->getOne($sql);

if(!$mac_id) {
	switch($_REQUEST['request']) {
		case "y000000000004.cfg":
			echo "#left blank";
			break;
		case "aastra.cfg":
			echo "#left blank";
			break;
                case "security.tuz":
                    if(file_exists("/var/www/html/admin/modules/_ep_phone_modules/endpoint/aastra/security.tuz")) {
                        $handle = fopen("/var/www/html/admin/modules/_ep_phone_modules/endpoint/aastra/security.tuz", "rb");
                        $contents = stream_get_contents($handle);
                        fclose($handle);
                        echo $contents;
                    }else {
                        header("HTTP/1.0 404 Not Found");
                    }
                    break;
                case "aastra.tuz":
                    if(file_exists("/var/www/html/admin/modules/_ep_phone_modules/endpoint/aastra/aastra.tuz")) {
                        $handle = fopen("/var/www/html/admin/modules/_ep_phone_modules/endpoint/aastra/aastra.tuz", "rb");
                        $contents = stream_get_contents($handle);
                        fclose($handle);
                        echo $contents;
                    } else {
                        header("HTTP/1.0 404 Not Found");
                    }
                    break;
                default:
                    header("HTTP/1.0 404 Not Found");
                    break;

	}
} else {
        
	$phone_info = $endpoint->get_phone_info($mac_id);
	$files = $endpoint->prepare_configs($phone_info,FALSE,FALSE);
        if(key_exists($_REQUEST['request'], $files)) {
            echo $files[$_REQUEST['request']];
        } else {
           header("HTTP/1.0 404 Not Found");
        }
}
?>