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