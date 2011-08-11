<?PHP
/**
 * Endpoint Manager Installer
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */
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

function epm_rmrf($dir) {
    if(file_exists($dir)) {
        $iterator = new RecursiveDirectoryIterator($dir);
        foreach (new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        //Remove parent path as the last step
        @rmdir($dir);
    }
}

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

global $db;

out("Endpoint Manager Installer");

define("PHONE_MODULES_PATH", $amp_conf['AMPWEBROOT'].'/admin/modules/_ep_phone_modules/');
define("LOCAL_PATH", $amp_conf['AMPWEBROOT'].'/admin/modules/endpointman/');


if(!file_exists(PHONE_MODULES_PATH)) {
    mkdir(PHONE_MODULES_PATH, 0764);
    out("Creating Phone Modules Directory");
}

if(!file_exists(PHONE_MODULES_PATH."setup.php")) {
    copy(LOCAL_PATH."install/setup.php",PHONE_MODULES_PATH."setup.php");
    out("Moving Auto Provisioner Class");
}

if(!file_exists(PHONE_MODULES_PATH."temp/")) {
    mkdir(PHONE_MODULES_PATH."temp/", 0764);
    out("Creating temp folder");
}
//Detect Version

function ep_table_exists ($table) {
    global $amp_conf,$db;
    $sql = "SHOW TABLES FROM ".$amp_conf['AMPDBNAME'];
    $result = $db->getAll($sql);

    foreach($result as $row) {
        if ($row[0] == $table) {
            return TRUE;
        }
    }
    return FALSE;
}

$epm_module_xml = epm_install_xml2array(LOCAL_PATH."module.xml");

$version = $epm_module_xml['module']['version'];

$sql = 'SELECT `version` FROM `modules` WHERE `modulename` = CONVERT(_utf8 \'endpointman\' USING latin1) COLLATE latin1_swedish_ci';

$db_version = $db->getOne($sql);

if($db_version) {
    $global_cfg =& $db->getAssoc("SELECT var_name, value FROM endpointman_global_vars");
    $global_cfg['version'] = $db_version;
} else {
    $global_cfg['version'] = '?';
}
$new_install = FALSE;
if($global_cfg['version'] != "?") {
    $ver = $global_cfg['version'];
} else {
    $ver = "1000";
    $new_install = TRUE;
}

if($new_install) {
    out('New Installation Detected!');
} else {
    out('Version Identified as '. $ver);
}
if(!$new_install) {

    if(($ver < "1.9.0") AND ($ver > 0)) {
        out("Please Wait While we upgrade your old setup");
        //Expand the value option
        $sql = 'ALTER TABLE `endpointman_global_vars` CHANGE `value` `value` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT \'Data\'';
        $db->query($sql);

        out("Locating NMAP + ARP + ASTERISK Executables");

        $nmap = find_exec("nmap");
        $arp = find_exec("arp");
        $asterisk = find_exec("asterisk");

        out("Updating Global Variables table");
        //Add new Vars into database
        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES
		(5, 'config_location', '/tftpboot/'),
		(6, 'update_server', 'http://mirror.freepbx.org/provisioner/'),
		(7, 'version', '2.0.0'),
		(8, 'enable_ari', '0'),
		(9, 'debug', '0'),
		(10, 'arp_location', '".$arp."'),
		(11, 'nmap_location', '".$nmap."'),
		(12, 'asterisk_location', '".$asterisk."'),
                (13, 'language', ''),
                (14, 'check_updates', '1'),
                (15, 'disable_htaccess', ''),
                (16, 'endpoint_vers', '0'),
                (17, 'disable_help', '0')";
        $db->query($sql_update_vars);

        out("Updating Mac List table");
        $sql = 'ALTER TABLE `endpointman_mac_list` DROP `map`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `custom_cfg_template` INT(11) NOT NULL AFTER `description`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `custom_cfg_data` TEXT NOT NULL AFTER `custom_cfg_template`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `user_cfg_data` TEXT NOT NULL AFTER `custom_cfg_data`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `config_files_override` TEXT NOT NULL AFTER `user_cfg_data`';
        $db->query($sql);

        out("Updating Brands table");
        $sql = 'DROP TABLE endpointman_brand_list';
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_brand_list` (
		  `id` int(11) NOT NULL auto_increment,
		  `name` varchar(255) NOT NULL,
		  `directory` varchar(255) NOT NULL,
		  `cfg_ver` varchar(255) NOT NULL,
		  `installed` int(1) NOT NULL default '0',
		  `hidden` int(1) NOT NULL default '0',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=22";
        $db->query($sql);

        out("Updating Models table");
        $sql = 'DROP TABLE endpointman_model_list';
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_model_list` (
		  `id` int(11) NOT NULL auto_increment COMMENT 'Key ',
		  `brand` int(11) NOT NULL COMMENT 'Brand',
		  `model` varchar(25) NOT NULL COMMENT 'Model',
		  `product_id` int(11) NOT NULL,
		  `enabled` int(1) NOT NULL default '0',
		  `hidden` int(1) NOT NULL default '0',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=48";
        $db->query($sql);

        out("Updating OUI table");

        $sql = 'DROP TABLE endpointman_oui_list';
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_oui_list` (
		  `id` int(30) NOT NULL auto_increment,
		  `oui` varchar(30) default NULL,
		  `brand` int(11) default NULL,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=57";
        $db->query($sql);

        out("Updating Products table");

        $sql = 'DROP TABLE IF EXISTS endpointman_product_list';
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_product_list` (
		  `id` int(11) NOT NULL auto_increment,
		  `brand` int(11) NOT NULL,
		  `long_name` varchar(255) NOT NULL,
		  `cfg_dir` varchar(255) NOT NULL,
		  `cfg_ver` varchar(255) NOT NULL,
		  `xml_data` varchar(255) NOT NULL,
		  `cfg_data` text NOT NULL,
		  `installed` int(1) NOT NULL default '0',
		  `hidden` int(1) NOT NULL default '0',
		  `firmware_vers` varchar(255) NOT NULL,
		  `firmware_files` text NOT NULL,
		  `config_files` text,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8";
        $db->query($sql);

        out("Updating templates table");

        $sql = 'DROP TABLE IF EXISTS endpointman_template_list';
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_template_list` (
		  `id` int(11) NOT NULL auto_increment,
		  `product_id` int(11) NOT NULL,
		  `name` varchar(255) NOT NULL,
		  `custom_cfg_data` text,
		  `config_files_override` text,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8";
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_custom_configs` (
		  `id` int(11) NOT NULL auto_increment,
		  `name` varchar(255) NOT NULL,
		  `original_name` varchar(255) NOT NULL,
		  `product_id` int(11) NOT NULL,
		  `data` longtext NOT NULL,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=11";
        $db->query($sql);

        $old_models = array(
                "57iCT" => array("brand" => 1, "model" => 2, "product" => 7),
                "57i" => array("brand" => 1, "model" => 3, "product" => 7),
                "330" => array("brand" => 4, "model" => 6, "product" => 4),
                "560" => array("brand" => 4, "model" => 7, "product" => 4),
                "300" => array("brand" => 6, "model" => 8, "product" => 8),
                "320" => array("brand" => 6, "model" => 9, "product" => 8),
                "360" => array("brand" => 6, "model" => 10, "product" => 8),
                "370" => array("brand" => 6, "model" => 11, "product" => 8),
                "820" => array("brand" => 6, "model" => 12, "product" => 8),
                "M3" => array("brand" => 6, "model" => 13, "product" => 8),
                "GXP-2000" => array("brand" => 2, "model" => 15, "product" => 1),
                "BT200_201" => array("brand" => 2, "model" => 27, "product" => 2),
                "spa941" => array("brand" => 0, "model" => 0, "product" => 0),
                "spa942" => array("brand" => 0, "model" => 0, "product" => 0),
                "spa962" => array("brand" => 0, "model" => 0, "product" => 0),
                "55i" => array("brand" => 1, "model" => 4, "product" => 7)
        );

        out("Migrating Old Devices");
        $sql = "SELECT * FROM endpointman_mac_list";
        $result = $db->query($sql);
        while($row =& $result->fetchRow(DB_FETCHMODE_ASSOC)) {
            $id = $row['model'];
            $new_model = $old_models[$id]['model'];
            $sql = "UPDATE endpointman_mac_list SET model = ".$new_model." WHERE id =" . $row['id'];
            $db->query($sql);
        }
        out("Old Devices Migrated, You must install the phone modules from within endpointmanager to see your old devices!");

        $sql = 'ALTER TABLE endpointman_mac_list CHANGE model model INT NOT NULL';
        $db->query($sql);

        $sql = "ALTER TABLE endpointman_mac_list CHANGE custom_cfg_data custom_cfg_data TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL";
        $db->query($sql);

        out("DONE! You can now use endpoint manager!");
    }

    if ($ver <= "1.9.0") {
        out("Locating NMAP + ARP + ASTERISK Executables");

        $nmap = find_exec("nmap");
        $arp = find_exec("arp");
        $asterisk = find_exec("asterisk");

        out("Updating Global Variables table");
        //Add new Vars into database

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (8, 'enable_ari', '0')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (9, 'debug', '0')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (10, 'arp_location', '".$arp."')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (11, 'nmap_location', '".$nmap."')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (12, 'asterisk_location', '".$asterisk."')";
        $db->query($sql_update_vars);

        out("Updating Mac List Table");
        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `user_cfg_data` TEXT NOT NULL AFTER `custom_cfg_data`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `config_files_override` TEXT NOT NULL AFTER `user_cfg_data`';
        $db->query($sql);

        out("Updating OUI Table");
        $sql = 'ALTER TABLE `endpointman_oui_list` DROP model';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_oui_list` CHANGE `brand` `brand` INT( 11 ) NULL DEFAULT NULL';
        $db->query($sql);

        out("Updating Product List");
        $sql = 'ALTER TABLE `endpointman_product_list` ADD `firmware_vers` TEXT NULL AFTER `hidden`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_product_list` ADD `firmware_files` VARCHAR( 255 ) NOT NULL AFTER `firmware_vers`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_product_list` ADD `config_files_override` TEXT NULL AFTER `firmware_files`';
        $db->query($sql);

        out("Updating Template List");
        $sql = 'ALTER TABLE `endpointman_template_list` ADD `config_files_override` TEXT NULL AFTER `custom_cfg_data`';

        out("Updating Version Number");
        $sql = "UPDATE  endpointman_global_vars SET  value =  '2.0.0' WHERE  var_name = 'version'";

        out("Creating Custom Configs Table");
        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_custom_configs` (
		  `id` int(11) NOT NULL auto_increment,
		  `name` varchar(255) NOT NULL,
		  `original_name` varchar(255) NOT NULL,
		  `product_id` int(11) NOT NULL,
		  `data` longtext NOT NULL,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=11";
        $db->query($sql);

        out('Alter custom_cfg_data');
        $sql = "ALTER TABLE endpointman_mac_list CHANGE custom_cfg_data custom_cfg_data TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL";
        $db->query($sql);
    }
    if ($ver <= "1.9.1") {
        out("Create Custom Configs Table");
        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_custom_configs` (
	  `id` int(11) NOT NULL auto_increment,
	  `name` varchar(255) NOT NULL,
	  `original_name` varchar(255) NOT NULL,
	  `product_id` int(11) NOT NULL,
	  `data` longtext NOT NULL,
	  PRIMARY KEY  (`id`)
	) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=11";
        $db->query($sql);

        out("Locating NMAP + ARP + ASTERISK Executables");

        $nmap = find_exec("nmap");
        $arp = find_exec("arp");
        $asterisk = find_exec("asterisk");

        out('Updating Global Variables');

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (8, 'enable_ari', '0')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (9, 'debug', '0')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (10, 'arp_location', '".$arp."')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (11, 'nmap_location', '".$nmap."')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (12, 'asterisk_location', '".$asterisk."')";
        $db->query($sql_update_vars);

        out("Update Mac List Table");
        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `config_files_override` TEXT NOT NULL AFTER `user_cfg_data`';
        $db->query($sql);

        out("Update Product List Table");
        $sql = 'ALTER TABLE `endpointman_product_list` ADD `config_files` TEXT NOT NULL AFTER `firmware_files`';
        $db->query($sql);

        out("Update Template List Table");
        $sql = 'ALTER TABLE `endpointman_template_list` ADD `config_files_override` TEXT NOT NULL AFTER `custom_cfg_data`';
        $db->query($sql);

        out("Update Version Number");
        $sql = 'UPDATE endpointman_global_vars SET value = \'2.0.0\' WHERE var_name = "version"';
        $db->query($sql);

        out('Alter custom_cfg_data');
        $sql = "ALTER TABLE endpointman_mac_list CHANGE custom_cfg_data custom_cfg_data TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL";
        $db->query($sql);
    }
    if ($ver <= "1.9.2") {
        out('Updating Global Variables');
    }

    if ($ver <= "1.9.9") {
        out("Adding Custom Field to OUI List");
        $sql = 'ALTER TABLE `endpointman_oui_list` ADD `custom` INT(1) NOT NULL DEFAULT \'0\'';
        $db->query($sql);

        out("Increase value Size in global Variables Table");
        $sql = 'ALTER TABLE `endpointman_global_vars` CHANGE `value` `value` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL COMMENT \'Data\'';
        $db->query($sql);

        out("Update global variables to include future language support");
        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (\'13\', \'temp_amp\', \'\');';
        $db->query($sql);

        $sql = "UPDATE endpointman_global_vars SET var_name = 'language' WHERE var_name = 'temp_amp'";
        $db->query($sql);

        out("Changing all 'LONG TEXT' or 'TEXT' to 'BLOB'");
        $sql = 'ALTER TABLE `endpointman_product_list` CHANGE `cfg_data` `cfg_data` BLOB NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_template_list` CHANGE `custom_cfg_data` `custom_cfg_data` BLOB NULL DEFAULT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` CHANGE `custom_cfg_data` `custom_cfg_data` BLOB NOT NULL, CHANGE `user_cfg_data` `user_cfg_data` BLOB NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_custom_configs` CHANGE `data` `data` LONGBLOB NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_product_list` ADD `special_cfgs` BLOB NOT NULL;';
        $db->query($sql);

        out("Inserting Check for Updates Command");
        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (\'14\', \'check_updates\', \'1\');';
        $db->query($sql);

        out("Inserting Disable .htaccess command");
        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (\'15\', \'disable_htaccess\', \'0\');';
        $db->query($sql);

        out("Add Automatic Update Check [Can be Disabled]");
        $sql = "INSERT INTO cronmanager (module, id, time, freq, lasttime, command) VALUES ('endpointman', 'UPDATES', '23', '24', '0', 'php ".LOCAL_PATH. "includes/update_check.php')";
        $db->query($sql);
    }
    if($ver <= "2.0.0") {
        out("Locating NMAP + ARP + ASTERISK Executables");
        $nmap = find_exec("nmap");
        $arp = find_exec("arp");
        $asterisk = find_exec("asterisk");

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (11, 'nmap_location', '".$asterisk."')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (11, 'nmap_location', '".$nmap."')";
        $db->query($sql_update_vars);

        $sql_update_vars = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (12, 'asterisk_location', '".$asterisk."')";
        $db->query($sql_update_vars);

        out("Add Unique to Global Variables Table");
        $sql = 'ALTER TABLE `endpointman_global_vars` ADD UNIQUE(`var_name`)';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_custom_configs` CHANGE `product_id` `product_id` VARCHAR(11) NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` CHANGE `model` `model` VARCHAR(11) NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_model_list` ADD `template_list` TEXT NOT NULL AFTER `model`, ADD `template_data` BLOB NOT NULL AFTER `template_list`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_model_list` CHANGE `product_id` `product_id` VARCHAR(11) NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_model_list` CHANGE `id` `id` VARCHAR(11) NOT NULL COMMENT \'Key \'';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_product_list` CHANGE `id` `id` VARCHAR(11) NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_product_list` ADD `short_name` VARCHAR(255) NOT NULL AFTER `long_name`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_product_list` DROP `installed`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_product_list` DROP `xml_data`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_template_list` ADD `model_id` VARCHAR(10) NOT NULL AFTER `product_id`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_template_list` CHANGE `product_id` `product_id` VARCHAR(11) NOT NULL';
        $db->query($sql);

        $sql = "UPDATE endpointman_brand_list SET cfg_ver = '0', installed = '0' WHERE installed = '1'";
        $db->query($sql);

        $sql = "TRUNCATE TABLE `endpointman_product_list`";
        $db->query($sql);

        $sql = "TRUNCATE TABLE `endpointman_oui_list`";
        $db->query($sql);

        $sql = "TRUNCATE TABLE `endpointman_brand_list`";
        $db->query($sql);

        $sql = "TRUNCATE TABLE `endpointman_model_list`";
        $db->query($sql);

        $data =& $db->getAll("SELECT * FROM `endpointman_mac_list",array(), DB_FETCHMODE_ASSOC);

        $new_model_list = array(
                "2" => "1-2-11",
                "3" => "1-2-10",
                "4" => "1-2-9",
                "6" => "4-2-3",
                "7" => "4-3-7",
                "8" => "6-1-1",
                "9" => "6-1-2",
                "10" => "6-1-3",
                "11" => "6-1-4",
                "12"  => "6-1-5",
                "13"  => "6-1-6",
                "15" => "2-1-3",
                "22"  => "4-2-4",
                "23" => "2-1-2",
                "24" => "2-1-1",
                "25" => "2-1-4",
                "26" => "2-1-5",
                "27" => "2-2-1",
                "28" => "2-2-2",
                "29" => "4-2-1",
                "30" => "4-2-5",
                "31" => "4-2-6",
                "32" => "4-2-7",
                "33" => "4-2-2",
                "34" => "4-3-1",
                "35" => "4-3-2",
                "36" => "4-3-3",
                "37" => "4-3-4",
                "38" => "4-3-5",
                "39" => "4-3-6",
                "40" => "4-3-8",
                "41" => "4-3-9",
                "42" => "4-3-10",
                "43" => "4-3-11",
                "44" => "4-3-12",
                "45" => "4-1-1",
                "46" => "4-1-2",
                "47" => "1-2-1",
                "48" => "1-2-2",
                "49" => "1-1-1",
                "50" => "1-1-2",
                "51" => "1-2-3",
                "52" => "1-2-4",
                "53" => "1-2-5",
                "54" => "1-2-6",
                "55" => "1-2-7",
                "56" => "1-2-8",
                "57" => "",
                "58" => "",
                "59" => "",
                "60" => "7-1-1",
                "61" => "7-1-2",
                "62" => "8-1-1",
                "63" => "8-1-2",
                "64" => "8-1-3",
                "65" => "8-1-4",
                "67" => "7-2-1",
                "68" => "7-2-2",
                "69" => "7-2-3",
                "70" => "7-2-4",
                "71" => "7-2-5",
                "72" => "7-2-6"
        );

        foreach($data as $list) {
            $sql = "UPDATE endpointman_mac_list SET model = '".$new_model_list[$list['model']]."' WHERE id = ". $list['id'];
            $db->query($sql);
        }



        $new_product_list = array(
                "6" => array("product_id" => "1-1", "model_id" => "1-1-1"),
                "7" => array("product_id" => "1-2", "model_id" => "1-2-1"),
                "1" => array("product_id" => "2-1", "model_id" => "2-1-1"),
                "2" => array("product_id" => "2-2", "model_id" => "2-2-1"),
                "3" => array("product_id" => "4-2", "model_id" => "4-2-1"),
                "5" => array("product_id" => "4-1", "model_id" => "4-1-1"),
                "4" => array("product_id" => "4-3", "model_id" => "4-3-1"),
                "8" => array("product_id" => "6-1", "model_id" => "6-1-1"),
                "9" => array("product_id" => "7-1", "model_id" => "7-1-1"),
                "11" => array("product_id" => "7-2", "model_id" => "7-2-1"),
                "10" => array("product_id" => "8-1", "model_id" => "8-1-1")
        );

        $data = array();
        $data =& $db->getAll("SELECT * FROM endpointman_custom_configs",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $sql = "UPDATE endpointman_custom_configs SET product_id = '".$new_product_list[$list['product_id']]['product_id']."' WHERE id = ". $list['id'];
            $db->query($sql);
        }

        $data = array();
        $data =& $db->getAll("SELECT * FROM endpointman_template_list",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $sql = "UPDATE endpointman_template_list SET model_id = '".$new_product_list[$list['product_id']]['model_id']."', product_id = '".$new_product_list[$list['product_id']]['product_id']."' WHERE id = ". $list['id'];
            $db->query($sql);
        }

        out('WARNING: Config Files have changed MUCH. We have to remove all of your old custom config files. Sorry :-(');
        $db->query('TRUNCATE TABLE `endpointman_custom_configs`');


        exec("rm -Rf ".PHONE_MODULES_PATH);

        if(!file_exists(PHONE_MODULES_PATH)) {
            mkdir(PHONE_MODULES_PATH, 0764);
            out("Creating Phone Modules Directory");
        }

        if(!file_exists(PHONE_MODULES_PATH."setup.php")) {
            copy(LOCAL_PATH."install/setup.php",PHONE_MODULES_PATH."setup.php");
            out("Moving Auto Provisioner Class");
        }

        if(!file_exists(PHONE_MODULES_PATH."temp/")) {
            mkdir(PHONE_MODULES_PATH."temp/", 0764);
            out("Creating temp folder");
        }
    }
    if ($ver <= "2.2.1") {
    }

    if ($ver <= "2.2.2") {

        out("Remove all Dashes in IDs");
        $data = array();
        $data =& $db->getAll("SELECT * FROM `endpointman_model_list",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $new_model_id = str_replace("-", "", $list['id']);
            $sql = "UPDATE endpointman_model_list SET id = '".$new_model_id."' WHERE id = ". $list['id'];
            $db->query($sql);
        }

        $data = array();
        $data =& $db->getAll("SELECT * FROM `endpointman_product_list",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $new_product_id = str_replace("-", "", $list['id']);
            $sql = "UPDATE endpointman_product_list SET id = '".$new_product_id."' WHERE id = ". $list['id'];
            $db->query($sql);
        }

        $data = array();
        $data =& $db->getAll("SELECT * FROM `endpointman_mac_list",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $new_model_id = str_replace("-", "", $list['model']);
            $sql = "UPDATE endpointman_mac_list SET model = '".$new_model_id."' WHERE id = ". $list['id'];
            $db->query($sql);
        }

        $data = array();
        $data =& $db->getAll("SELECT * FROM endpointman_template_list",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $new_model_id = str_replace("-", "", $list['model_id']);
            $new_product_id = str_replace("-", "", $list['product_id']);
            $sql = "UPDATE endpointman_template_list SET model_id = '".$new_model_id."', product_id = '".$new_product_id."' WHERE id = ". $list['id'];
            $db->query($sql);
        }

        $data = array();
        $data =& $db->getAll("SELECT * FROM endpointman_custom_configs",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $new_product_id = str_replace("-", "", $list['product_id']);
            $sql = "UPDATE endpointman_custom_configs SET product_id = '".$new_product_id."' WHERE id = ". $list['id'];
            $db->query($sql);
        }
    }
    if ($ver <= "2.2.3") {
        $sql = "UPDATE endpointman_global_vars SET value = 'http://www.provisioner.net/release/' WHERE var_name = 'update_server'";
        $db->query($sql);
    }

    if ($ver <= "2.2.4") {

    }

    if ($ver <= "2.2.5") {
        out("Fixing Permissions of Phone Modules Directory");
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PHONE_MODULES_PATH), RecursiveIteratorIterator::SELF_FIRST);
        foreach($iterator as $item) {
            chmod($item, 0764);
        }

        out("Creating Endpoint Version Row");
        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (NULL, \'endpoint_vers\', \'\');';
        $db->query($sql);
    }

    if ($ver <= "2.2.6") {
        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_line_list` (
              `luid` int(11) NOT NULL AUTO_INCREMENT,
              `mac_id` int(11) NOT NULL,
              `line` smallint(2) NOT NULL,
              `ext` varchar(15) NOT NULL,
              `description` varchar(20) NOT NULL,
              `custom_cfg_data` longblob NOT NULL,
              `user_cfg_data` longblob NOT NULL,
              PRIMARY KEY (`luid`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;";
        $db->query($sql);

        $data = array();
        $data =& $db->getAll("SELECT * FROM endpointman_mac_list",array(), DB_FETCHMODE_ASSOC);
        foreach($data as $list) {
            $sql = "INSERT INTO endpointman_line_list (mac_id, line, ext, description) VALUES ('".$list['id']."', '1', '".$list['ext']."', '".$list['description']."')";
            $db->query($sql);
        }

        $sql = 'ALTER TABLE `endpointman_custom_configs` CHANGE `data` `data` LONGBLOB NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` DROP `description`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` DROP `ext`';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` CHANGE `custom_cfg_template` `template_id` INT(11) NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` CHANGE `cfg_template_data` `global_template_id` LONGBLOB NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` CHANGE `user_cfg_data` `global_user_cfg_data` LONGBLOB NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_model_list` ADD `max_lines` SMALLINT(2) NOT NULL AFTER `model`;';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_model_list` CHANGE `template_data` `template_data` LONGBLOB NOT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_template_list` CHANGE `custom_cfg_data` `global_custom_cfg_data` LONGBLOB NULL DEFAULT NULL';
        $db->query($sql);

        $sql = 'ALTER TABLE `endpointman_mac_list` CHANGE `custom_cfg_data` `global_custom_cfg_data` LONGBLOB NOT NULL';
        $db->query($sql);

    }

    if ($ver <= "2.2.7") {

    }

    if ($ver <= "2.2.8") {
        out("Fix Debug Left on Error, this turns off debug.");
        $sql = "UPDATE endpointman_global_vars SET value = '0' WHERE var_name = 'debug'";
        $db->query($sql);

        $sql = 'ALTER TABLE  endpointman_mac_list CHANGE global_user_cfg_data  global_user_cfg_data LONGBLOB NOT NULL';
        $db->query($sql);
    }

    if ($ver <= "2.4.0") {
        out("Uninstalling All Installed Brands (You'll just simply have to update again, no loss of data)");
        $db->query("UPDATE endpointman_brand_list SET  installed =  '0'");
        out("Changing update server");
        $sql = "UPDATE endpointman_global_vars SET value = 'http://mirror.freepbx.org/provisioner/' WHERE var_name ='update_server'";
        $db->query($sql);
        $sql = "UPDATE  endpointman_model_list SET  enabled =  '0', template_data = '".serialize(array())."'";
        $db->query($sql);

        exec("rm -Rf ".PHONE_MODULES_PATH);

        if(!file_exists(PHONE_MODULES_PATH)) {
            mkdir(PHONE_MODULES_PATH, 0764);
            out("Creating Phone Modules Directory");
        }

        if(!file_exists(PHONE_MODULES_PATH."setup.php")) {
            copy(LOCAL_PATH."install/setup.php",PHONE_MODULES_PATH."setup.php");
            out("Moving Auto Provisioner Class");
        }

        if(!file_exists(PHONE_MODULES_PATH."temp/")) {
            mkdir(PHONE_MODULES_PATH."temp/", 0764);
            out("Creating temp folder");
        }
    }

    if ($ver <= "2.9.0.2") {
        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (NULL, \'disable_help\', \'0\');';
        $db->query($sql);
    }

    if($ver <= "2.9.0.3") {
        $sql = 'ALTER TABLE  `endpointman_custom_configs` CHANGE  `data`  `data` LONGBLOB NOT NULL';
        $db->query($sql);
    }

    if($ver <= "2.9.0.4") {
        out("Adding 'local' column to brand_list");
        $sql = 'ALTER TABLE  `endpointman_brand_list` ADD  `local` INT( 1 ) NOT NULL DEFAULT  \'0\' AFTER  `cfg_ver`';
        $db->query($sql);
    }

    if($ver <= "2.9.0.7") {
        out("Adding UNIQUE key to table global_vars for var_name");
        $sql = "ALTER TABLE `asterisk`.`endpointman_global_vars` ADD UNIQUE `unique` (`var_name`)";
        $db->query($sql);

        out("Adding show_all_registrations to global_vars table");
        $sql = 'INSERT INTO asterisk.endpointman_global_vars (idnum, var_name, value) VALUES (NULL, "show_all_registrations", "0")';
        $db->query($sql);
    }

    if($ver <= "2.9.0.9") {
        out("Successfully  Migrated to the new Installer!");
        $sql = "UPDATE endpointman_global_vars SET value = 'http://mirror.freepbx.org/provisioner/' WHERE var_name ='update_server'";
        $db->query($sql);
        out("Mirgrated to FreePBX Mirror");
    }

    if($ver <= "2.9.1.0") {
        out("Fix again to the 'Allow Duplicate Extensions' Error");
        $sql = 'ALTER TABLE `endpointman_global_vars` ADD UNIQUE `var_name` (`var_name`)';
        $db->query($sql);
        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (NULL, \'show_all_registrations\', \'0\');';
        $db->query($sql);
    }

    if($ver <= "2.9.2.0") {
        out("Adding new Network Time Protocol Setting");
        $sql = "INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (NULL, 'ntp', '".$_SERVER["SERVER_ADDR"]."')";
        $db->query($sql);
        out("Upgrading all timezone data to new improved simplified system");

        $sql = 'ALTER TABLE `endpointman_mac_list` ADD `global_settings_override` LONGBLOB NULL;';
        $db->query($sql);
        $sql = 'ALTER TABLE `endpointman_template_list` ADD `global_settings_override` LONGBLOB NULL;';
        $db->query($sql);

        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES (NULL, \'server_type\', \'file\');';
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_time_zones_desc` (
  `id` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=32";
        $db->query($sql);

        $sql = "INSERT INTO `endpointman_time_zones_desc` (`id`, `tid`, `name`, `description`) VALUES
(1, 1, 'UTC', 'Universal Coordinated Time (and Greenwich Mean Time)'),
(2, 2, 'ECT', 'European Central Time'),
(3, 3, 'EET', 'Eastern European Time'),
(4, 3, 'ART', '(Arabic) Egypt Standard Time'),
(5, 4, 'EAT', 'Eastern African Time'),
(6, 5, 'MET', 'Middle East Time'),
(7, 6, 'NET', 'Near East Time'),
(8, 7, 'PLT', 'Pakistan Lahore Time'),
(9, 8, 'IST', 'India Standard Time'),
(10, 9, 'BST', 'Bangladesh Standard Time'),
(11, 10, 'VST', 'Vietnam Standard Time'),
(12, 11, 'CTT', 'China Taiwan Time'),
(13, 12, 'JST', 'Japan Standard Time'),
(14, 13, 'ACT', 'Australia Central Time'),
(15, 14, 'AET', 'Australia Eastern Time'),
(16, 15, 'SST', 'Solomon Standard Time'),
(17, 16, 'NST', 'New Zealand Standard Time'),
(18, 17, 'MIT', 'Midway Islands Time'),
(19, 18, 'HST', 'Hawaii Standard Time'),
(20, 19, 'AST', 'Alaska Standard Time'),
(21, 20, 'PST', 'Pacific Standard Time'),
(22, 21, 'PNT', 'Phoenix Standard Time'),
(23, 21, 'MST', 'Mountain Standard Time'),
(24, 22, 'CST', 'Central Standard Time'),
(25, 23, 'EST', 'Eastern Standard Time'),
(26, 23, 'IET', 'Indiana Eastern Standard Time'),
(27, 24, 'PRT', 'Puerto Rico and US Virgin Islands Time'),
(28, 25, 'CNT', 'Canada Newfoundland Time'),
(29, 26, 'AGT', 'Argentina Standard Time'),
(30, 26, 'BET', 'Brazil Eastern Time'),
(31, 27, 'CAT', 'Central African Time')";
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_time_zones_new` (
  `id` int(11) NOT NULL auto_increment,
  `gmt` varchar(255) NOT NULL,
  `offset` int(11) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=28";
        $db->query($sql);

        $sql = "INSERT INTO `endpointman_time_zones_new` (`id`, `gmt`, `offset`) VALUES
(1, 'GMT', 0),
(2, 'GMT+1:00', 3600),
(3, 'GMT+2:00', 7200),
(4, 'GMT+3:00', 10800),
(5, 'GMT+3:30', 12600),
(6, 'GMT+4:00', 14400),
(7, 'GMT+5:00', 18000),
(8, 'GMT+5:30', 19800),
(9, 'GMT+6:00', 21600),
(10, 'GMT+7:00', 25200),
(11, 'GMT+8:00', 28800),
(12, 'GMT+9:00', 32400),
(13, 'GMT+9:30', 34200),
(14, 'GMT+10:00', 36000),
(15, 'GMT+11:00', 39600),
(16, 'GMT+12:00', 43200),
(17, 'GMT-11:00', -39600),
(18, 'GMT-10:00', -36000),
(19, 'GMT-9:00', -32400),
(20, 'GMT-8:00', -28800),
(21, 'GMT-7:00', -25200),
(22, 'GMT-6:00', -21600),
(23, 'GMT-5:00', -18000),
(24, 'GMT-4:00', -14400),
(25, 'GMT-3:30', -12600),
(26, 'GMT-3:00', -10800),
(27, 'GMT-1:00', -3600)";
        $db->query($sql);

        out('Creating symlink to web provisioner');
        if(!symlink(LOCAL_PATH."provisioning",$amp_conf['AMPWEBROOT']."/provisioning")) {
            out("<strong>Your permissions are wrong on ".$amp_conf['AMPWEBROOT'].", web provisioning link not created!</strong>");
        }

        $sql = 'SELECT `value` FROM `endpointman_global_vars` WHERE `var_name` = CONVERT(_utf8 \'gmthr\' USING latin1) COLLATE latin1_swedish_ci';
        $old_tz_gmt = $db->getOne($sql);

        $sql = "SELECT id FROM `endpointman_time_zones_new` WHERE `gmt` LIKE '".$old_tz_gmt."'";
        $new_tz_id = $db->getOne($sql);

        $sql = "UPDATE endpointman_global_vars SET value = '".$new_tz_id.".0' WHERE var_name = 'tz'";
        $db->query($sql);

        $sql = 'INSERT INTO `asterisk`.`endpointman_global_vars` (`var_name`, `value`) VALUES (\'allow_hdfiles\', \'0\');';
        $db->query($sql);

	$sql = 'ALTER TABLE `endpointman_mac_list` ADD `specific_settings` LONGBLOB NULL;';
	$db->query($sql);
    }

}


if ($new_install) {

    out("Creating Brand List Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_brand_list` (
                  `id` varchar(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `directory` varchar(255) NOT NULL,
                  `cfg_ver` varchar(255) NOT NULL,
                  `installed` int(1) NOT NULL DEFAULT '0',
                    `local` int(1) NOT NULL DEFAULT '0',
                  `hidden` int(1) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1";
    $db->query($sql);

    out("Creating Line List Table");

    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_line_list` (
  `luid` int(11) NOT NULL AUTO_INCREMENT,
  `mac_id` int(11) NOT NULL,
  `line` smallint(2) NOT NULL,
  `ext` varchar(15) NOT NULL,
  `description` varchar(20) NOT NULL,
  `custom_cfg_data` longblob NOT NULL,
  `user_cfg_data` longblob NOT NULL,
  PRIMARY KEY (`luid`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;";
    $db->query($sql);

    out("Creating Global Variables Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_global_vars` (
                  `idnum` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Index',
                  `var_name` varchar(25) NOT NULL COMMENT 'Variable Name',
                  `value` text NOT NULL COMMENT 'Data',
                  PRIMARY KEY (`idnum`),
                  UNIQUE KEY `var_name` (`var_name`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17";
    $db->query($sql);

    out("Locating NMAP + ARP + ASTERISK Executables");
    $nmap = find_exec("nmap");
    $arp = find_exec("arp");
    $asterisk = find_exec("asterisk");

    out("Inserting data into the global vars Table");
    $sql = "INSERT INTO `endpointman_global_vars` (`idnum`, `var_name`, `value`) VALUES
            (1, 'srvip', ''),
            (2, 'tz', ''),
            (3, 'gmtoff', ''),
            (4, 'gmthr', ''),
            (5, 'config_location', '/tftpboot/'),
            (6, 'update_server', 'http://mirror.freepbx.org/provisioner/'),
            (7, 'version', '".$version."'),
            (8, 'enable_ari', '0'),
            (9, 'debug', '0'),
            (10, 'arp_location', '".$arp."'),
            (11, 'nmap_location', '".$nmap."'),
            (12, 'asterisk_location', '".$asterisk."'),
            (13, 'language', ''),
            (14, 'check_updates', '0'),
            (15, 'disable_htaccess', ''),
            (16, 'endpoint_vers', '0'),
            (17, 'disable_help', '0'),
            (18, 'show_all_registrations', '0'),
            (19, 'ntp', ''),
            (20, 'server_type, 'file'";
    $db->query($sql);

    out("Creating mac list Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_mac_list` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `mac` varchar(12) DEFAULT NULL,
  `model` varchar(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `global_custom_cfg_data` longblob NOT NULL,
  `global_user_cfg_data` longblob NOT NULL,
  `config_files_override` text NOT NULL,
  `global_settings_override` longblob,
    `specific_settings` longblob,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mac` (`mac`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
    $db->query($sql);

    out("Creating model List Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_model_list` (
  `id` varchar(11) NOT NULL COMMENT 'Key ',
  `brand` int(11) NOT NULL COMMENT 'Brand',
  `model` varchar(25) NOT NULL COMMENT 'Model',
  `max_lines` smallint(2) NOT NULL,
  `template_list` text NOT NULL,
  `template_data` longblob NOT NULL,
  `product_id` varchar(11) NOT NULL,
  `enabled` int(1) NOT NULL DEFAULT '0',
  `hidden` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1";
    $db->query($sql);

    out("Creating oui List Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_oui_list` (
          `id` int(30) NOT NULL AUTO_INCREMENT,
          `oui` varchar(30) DEFAULT NULL,
          `brand` int(11) NOT NULL,
          `custom` int(1) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`),
          UNIQUE KEY `oui` (`oui`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
    $db->query($sql);

    out("Creating product List Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_product_list` (
  `id` varchar(11) NOT NULL,
  `brand` int(11) NOT NULL,
  `long_name` varchar(255) NOT NULL,
  `short_name` varchar(255) NOT NULL,
  `cfg_dir` varchar(255) NOT NULL,
  `cfg_ver` varchar(255) NOT NULL,
  `hidden` int(1) NOT NULL DEFAULT '0',
  `firmware_vers` varchar(255) NOT NULL,
  `firmware_files` text NOT NULL,
  `config_files` text,
  `special_cfgs` blob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1";
    $db->query($sql);

    out("Creating Template List Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_template_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` varchar(11) NOT NULL,
  `model_id` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `global_custom_cfg_data` longblob,
  `config_files_override` text,
  `global_settings_override` longblob,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
    $db->query($sql);

    out("Creating Time Zone List Tables");
        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_time_zones_desc` (
  `id` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=32";
        $db->query($sql);

        $sql = "INSERT INTO `endpointman_time_zones_desc` (`id`, `tid`, `name`, `description`) VALUES
(1, 1, 'UTC', 'Universal Coordinated Time (and Greenwich Mean Time)'),
(2, 2, 'ECT', 'European Central Time'),
(3, 3, 'EET', 'Eastern European Time'),
(4, 3, 'ART', '(Arabic) Egypt Standard Time'),
(5, 4, 'EAT', 'Eastern African Time'),
(6, 5, 'MET', 'Middle East Time'),
(7, 6, 'NET', 'Near East Time'),
(8, 7, 'PLT', 'Pakistan Lahore Time'),
(9, 8, 'IST', 'India Standard Time'),
(10, 9, 'BST', 'Bangladesh Standard Time'),
(11, 10, 'VST', 'Vietnam Standard Time'),
(12, 11, 'CTT', 'China Taiwan Time'),
(13, 12, 'JST', 'Japan Standard Time'),
(14, 13, 'ACT', 'Australia Central Time'),
(15, 14, 'AET', 'Australia Eastern Time'),
(16, 15, 'SST', 'Solomon Standard Time'),
(17, 16, 'NST', 'New Zealand Standard Time'),
(18, 17, 'MIT', 'Midway Islands Time'),
(19, 18, 'HST', 'Hawaii Standard Time'),
(20, 19, 'AST', 'Alaska Standard Time'),
(21, 20, 'PST', 'Pacific Standard Time'),
(22, 21, 'PNT', 'Phoenix Standard Time'),
(23, 21, 'MST', 'Mountain Standard Time'),
(24, 22, 'CST', 'Central Standard Time'),
(25, 23, 'EST', 'Eastern Standard Time'),
(26, 23, 'IET', 'Indiana Eastern Standard Time'),
(27, 24, 'PRT', 'Puerto Rico and US Virgin Islands Time'),
(28, 25, 'CNT', 'Canada Newfoundland Time'),
(29, 26, 'AGT', 'Argentina Standard Time'),
(30, 26, 'BET', 'Brazil Eastern Time'),
(31, 27, 'CAT', 'Central African Time')";
        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS `endpointman_time_zones_new` (
  `id` int(11) NOT NULL auto_increment,
  `gmt` varchar(255) NOT NULL,
  `offset` int(11) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=28";
        $db->query($sql);

        $sql = "INSERT INTO `endpointman_time_zones_new` (`id`, `gmt`, `offset`) VALUES
(1, 'GMT', 0),
(2, 'GMT+1:00', 3600),
(3, 'GMT+2:00', 7200),
(4, 'GMT+3:00', 10800),
(5, 'GMT+3:30', 12600),
(6, 'GMT+4:00', 14400),
(7, 'GMT+5:00', 18000),
(8, 'GMT+5:30', 19800),
(9, 'GMT+6:00', 21600),
(10, 'GMT+7:00', 25200),
(11, 'GMT+8:00', 28800),
(12, 'GMT+9:00', 32400),
(13, 'GMT+9:30', 34200),
(14, 'GMT+10:00', 36000),
(15, 'GMT+11:00', 39600),
(16, 'GMT+12:00', 43200),
(17, 'GMT-11:00', -39600),
(18, 'GMT-10:00', -36000),
(19, 'GMT-9:00', -32400),
(20, 'GMT-8:00', -28800),
(21, 'GMT-7:00', -25200),
(22, 'GMT-6:00', -21600),
(23, 'GMT-5:00', -18000),
(24, 'GMT-4:00', -14400),
(25, 'GMT-3:30', -12600),
(26, 'GMT-3:00', -10800),
(27, 'GMT-1:00', -3600)";
        $db->query($sql);

    out("Create Custom Configs Table");
    $sql = "CREATE TABLE IF NOT EXISTS `endpointman_custom_configs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `original_name` varchar(255) NOT NULL,
          `product_id` varchar(11) NOT NULL,
          `data` longblob NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
    $db->query($sql);

    out('Creating symlink to web provisioner');
    if(!symlink(LOCAL_PATH."provisioning",$amp_conf['AMPWEBROOT']."/provisioning")) {
        out("<strong>Your permissions are wrong on ".$amp_conf['AMPWEBROOT'].", web provisioning link not created!</strong>");
    }
}

out("Update Version Number to ".$version);
$sql = "UPDATE endpointman_global_vars SET value = '".$version."' WHERE var_name = 'version'";
$db->query($sql);

$sql = "UPDATE endpointman_global_vars SET value = 'http://mirror.freepbx.org/provisioner/' WHERE var_name = 'update_server'";
$db->query($sql);

$sql = 'SELECT value FROM `admin` WHERE `variable` LIKE CONVERT(_utf8 \'version\' USING latin1) COLLATE latin1_swedish_ci';
$amp_version = $db->getOne($sql);

if(file_exists($amp_conf['AMPWEBROOT']."/recordings/modules/phonesettings.module")) {
    unlink($amp_conf['AMPWEBROOT']."/recordings/modules/phonesettings.module");
}

if(file_exists($amp_conf['AMPWEBROOT']."/recordings/theme/js/jquery.coda-slider-2.0.js")) {
    unlink($amp_conf['AMPWEBROOT']."/recordings/theme/js/jquery.coda-slider-2.0.js");
}

if(file_exists($amp_conf['AMPWEBROOT']."/recordings/theme/js/jquery.easing.1.3.js")) {
    unlink($amp_conf['AMPWEBROOT']."/recordings/theme/js/jquery.easing.1.3.js");
}

if(file_exists($amp_conf['AMPWEBROOT']."/recordings/theme/coda-slider-2.0a.css")) {
    unlink($amp_conf['AMPWEBROOT']."/recordings/theme/coda-slider-2.0a.css");
}

if($amp_version < "2.9.0") {
    //Do symlinks ourself because retrieve_conf is OLD

    //images
    $dir = $amp_conf['AMPWEBROOT'].'/admin/assets/endpointman/images';
    if(!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    foreach (glob(LOCAL_PATH."assets/images/*.*") as $filename) {
        if(file_exists($dir.'/'.basename($filename))) {
            unlink($dir.'/'.basename($filename));
            symlink($filename, $dir.'/'.basename($filename));
        } else {
            symlink($filename, $dir.'/'.basename($filename));
        }
    }

    //javascripts
    $dir = $amp_conf['AMPWEBROOT'].'/admin/assets/endpointman/js';
    if(!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    foreach (glob(LOCAL_PATH."assets/js/*.*") as $filename) {
        if(file_exists($dir.'/'.basename($filename))) {
            unlink($dir.'/'.basename($filename));
            symlink($filename, $dir.'/'.basename($filename));
        } else {
            symlink($filename, $dir.'/'.basename($filename));
        }
    }

    //theme (css/stylesheets)
    $dir = $amp_conf['AMPWEBROOT'].'/admin/assets/endpointman/theme';
    if(!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    foreach (glob(LOCAL_PATH."assets/theme/*.*") as $filename) {
        if(file_exists($dir.'/'.basename($filename))) {
            unlink($dir.'/'.basename($filename));
            symlink($filename, $dir.'/'.basename($filename));
        } else {
            symlink($filename, $dir.'/'.basename($filename));
        }
    }

    //ari-theme (css/stylesheets)
    $dir = $amp_conf['AMPWEBROOT'].'/recordings/theme';
    if(!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    foreach (glob(LOCAL_PATH."ari/theme/*.*") as $filename) {
        if(file_exists($dir.'/'.basename($filename))) {
            unlink($dir.'/'.basename($filename));
            symlink($filename, $dir.'/'.basename($filename));
        } else {
            symlink($filename, $dir.'/'.basename($filename));
        }
    }

    //ari-images
    $dir = $amp_conf['AMPWEBROOT'].'/recordings/theme/images';
    if(!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    foreach (glob(LOCAL_PATH."ari/images/*.*") as $filename) {
        if(file_exists($dir.'/'.basename($filename))) {
            unlink($dir.'/'.basename($filename));
            symlink($filename, $dir.'/'.basename($filename));
        } else {
            symlink($filename, $dir.'/'.basename($filename));
        }
    }

    //ari-js
    $dir = $amp_conf['AMPWEBROOT'].'/recordings/theme/js';
    if(!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    foreach (glob(LOCAL_PATH."ari/js/*.*") as $filename) {
        if(file_exists($dir.'/'.basename($filename))) {
            unlink($dir.'/'.basename($filename));
            symlink($filename, $dir.'/'.basename($filename));
        } else {
            symlink($filename, $dir.'/'.basename($filename));
        }
    }

    //ari-modules
    $dir = $amp_conf['AMPWEBROOT'].'/recordings/modules';
    if(!file_exists($dir)) {
        mkdir($dir, 0777, TRUE);
    }
    foreach (glob(LOCAL_PATH."ari/modules/*.*") as $filename) {
        if(file_exists($dir.'/'.basename($filename))) {
            unlink($dir.'/'.basename($filename));
            symlink($filename, $dir.'/'.basename($filename));
        } else {
            symlink($filename, $dir.'/'.basename($filename));
        }
    }
}

function epm_install_xml2array($url, $get_attributes = 1, $priority = 'tag') {
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
