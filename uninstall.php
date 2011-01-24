<?PHP
/**
 * Endpoint Manager Uninstaller
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */
require dirname($_SERVER["SCRIPT_FILENAME"]). "/modules/endpointman/includes/functions.inc";

global $endpoint;

$endpoint = new endpointmanager();

global $db;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

out("Removing Phone Modules Directory");
$endpoint->rmrf(PHONE_MODULES_PATH);
exec("rm -R ". PHONE_MODULES_PATH);

out("Dropping all relevant tables");
$sql = "DROP TABLE `endpointman_brand_list`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_global_vars`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_mac_list`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_line_list`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_model_list`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_oui_list`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_product_list`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_template_list`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_time_zones`";
$result = $db->query($sql);

$sql = "DROP TABLE `endpointman_custom_configs`";
$result = $db->query($sql);

out("Removing ARI Module");		
unlink($amp_conf['AMPWEBROOT']."/recordings/modules/phonesettings.module");

unlink($amp_conf['AMPWEBROOT']."/recordings/theme/js/jquery.coda-slider-2.0.js");

unlink($amp_conf['AMPWEBROOT']."/recordings/theme/js/jquery.easing.1.3.js");

unlink($amp_conf['AMPWEBROOT']."/recordings/theme/coda-slider-2.0a.css");

out("Removing Symbolic Links for Images");
foreach (glob(LOCAL_PATH."templates/images/*.*") as $filename) {
    //echo "$filename size " . filesize($filename) . "<br />";
    $newloc = str_replace(stristr($filename, 'admin/'), '', $filename) . "admin/images/".basename($filename);
    //echo "\t". $newloc ."<br />";
    if((file_exists($newloc)) && (is_link($newloc))) {
        unlink($newloc);
    }
}

out("Removing Symbolic Links for Javascripts");
foreach (glob(LOCAL_PATH."templates/freepbx/javascript/*.*") as $filename) {
    //echo "$filename size " . filesize($filename) . "<br />";
    $newloc = str_replace(stristr($filename, 'admin/'), '', $filename) . "admin/common/".basename($filename);
    //echo "\t". $newloc ."<br />";
    if((file_exists($newloc)) && (is_link($newloc))) {
        unlink($newloc);
    }
}

out("Removing Symbolic Links for Stylesheets");
foreach (glob(LOCAL_PATH."templates/freepbx/stylesheets/*.*") as $filename) {
    //echo "$filename size " . filesize($filename) . "<br />";
    $newloc = str_replace(stristr($filename, 'admin/'), '', $filename) . "admin/common/".basename($filename);
    //echo "\t". $newloc ."<br />";
    if((file_exists($newloc)) && (is_link($newloc))) {
        unlink($newloc);
    }
}