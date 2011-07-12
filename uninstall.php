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

$sql = 'SELECT value FROM `admin` WHERE `variable` LIKE CONVERT(_utf8 \'version\' USING latin1) COLLATE latin1_swedish_ci';
$amp_version = $db->getOne($sql);

//Do unlinks ourself because retrieve_conf doesn't always remove stuff...

//images
$dir = $amp_conf['AMPWEBROOT'].'/admin/assets/endpointman/images';
foreach (glob(LOCAL_PATH."assets/images/*.*") as $filename) {
    if(file_exists($dir.'/'.basename($filename)) && (readlink($dir.'/'.basename($filename)) == $filename)) {
        unlink($dir.'/'.basename($filename));
    }
}
if(is_link($dir)) {
    unlink($dir);
}


//javascripts
$dir = $amp_conf['AMPWEBROOT'].'/admin/assets/endpointman/js';
foreach (glob(LOCAL_PATH."assets/js/*.*") as $filename) {
    if(file_exists($dir.'/'.basename($filename)) && (readlink($dir.'/'.basename($filename)) == $filename)) {
        unlink($dir.'/'.basename($filename));
    }
}
if(is_link($dir)) {
    unlink($dir);
}

//theme
$dir = $amp_conf['AMPWEBROOT'].'/admin/assets/endpointman/theme';
foreach (glob(LOCAL_PATH."assets/theme/*.*") as $filename) {
    if(file_exists($dir.'/'.basename($filename)) && (readlink($dir.'/'.basename($filename)) == $filename)) {
        unlink($dir.'/'.basename($filename));
    }
}
if(is_link($dir)) {
    unlink($dir);
}

//ari-modules
$dir = $amp_conf['AMPWEBROOT'].'/recordings/modules';
foreach (glob(LOCAL_PATH."ari/modules/*.*") as $filename) {
    if(file_exists($dir.'/'.basename($filename)) && (readlink($dir.'/'.basename($filename)) == $filename)) {
        unlink($dir.'/'.basename($filename));
    }
}

//ari-images
$dir = $amp_conf['AMPWEBROOT'].'/recordings/theme/images';
foreach (glob(LOCAL_PATH."ari/images/*.*") as $filename) {
    if(file_exists($dir.'/'.basename($filename)) && (readlink($dir.'/'.basename($filename)) == $filename)) {
        unlink($dir.'/'.basename($filename));
    }
}

//ari-js
$dir = $amp_conf['AMPWEBROOT'].'/recordings/theme/js';
foreach (glob(LOCAL_PATH."ari/js/*.*") as $filename) {
    if(file_exists($dir.'/'.basename($filename)) && (readlink($dir.'/'.basename($filename)) == $filename)) {
        unlink($dir.'/'.basename($filename));
    }
}

//ari-theme
$dir = $amp_conf['AMPWEBROOT'].'/recordings/theme';
foreach (glob(LOCAL_PATH."ari/theme/*.*") as $filename) {
    if(file_exists($dir.'/'.basename($filename)) && (readlink($dir.'/'.basename($filename)) == $filename)) {
        unlink($dir.'/'.basename($filename));
    }
}
if(!is_link($amp_conf['AMPWEBROOT'].'/admin/assets/endpointman')) {
    $endpoint->rmrf($amp_conf['AMPWEBROOT'].'/admin/assets/endpointman');
}