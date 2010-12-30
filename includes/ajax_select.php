<?php
/**
 * Ajax Select File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
include 'jsonwrapper.php';
//ini_set('display_errors', 1);
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
    global $db;
    if(isset($lineid)) {
        $sql="SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT endpointman_mac_list.model FROM endpointman_mac_list, endpointman_line_list WHERE endpointman_line_list.luid = ".$lineid." AND endpointman_line_list.mac_id = endpointman_mac_list.id)";

        $sql_l = "SELECT line, mac_id FROM `endpointman_line_list` WHERE luid = ".$lineid;
        $line =& $db->getRow($sql_l, array(), DB_FETCHMODE_ASSOC);

        $sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = ".$line['mac_id'];
    } elseif(isset($macid)) {
        $sql="SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT model FROM endpointman_mac_list WHERE id =".$macid.")";
        $sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = ".$macid;

        $line['line'] = 0;
    }
    $max_lines = $db->getOne($sql);
    $lines_used =& $db->getAll($sql_lu);

    for($i = 1; $i <= $max_lines; $i++) {
        if($i == $line['line']) {
            $temp[$i]['value'] = $i;
            $temp[$i]['text'] = $i;
            $temp[$i]['selected'] = "selected";
        } else {
            if(!in_array_recursive($i,$lines_used)) {
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

if(($_REQUEST['id'] == "") OR ($_REQUEST['id'] == "0")) {
	$out[0]['optionValue'] = "";
	$out[0]['optionDisplay'] = "";
	echo json_encode($out);
	die();
}

if($_REQUEST['atype'] == "model") {
	$sql = "SELECT * FROM endpointman_model_list WHERE enabled = 1 AND brand =". $_GET['id'];
} elseif ($_REQUEST['atype'] == "template") {
	$sql = "SELECT id, name as model FROM  endpointman_template_list WHERE  product_id = '". $_GET['id']."'";
} elseif ($_REQUEST['atype'] == "template2") {
	$sql = "SELECT DISTINCT endpointman_template_list.id, endpointman_template_list.name as model FROM endpointman_template_list, endpointman_model_list, endpointman_product_list WHERE endpointman_template_list.product_id = endpointman_model_list.product_id AND endpointman_model_list.product_id = endpointman_product_list.id AND endpointman_model_list.id = '". $_GET['id']."'";
} elseif ($_REQUEST['atype'] == "model_clone") {
        $sql = "SELECT endpointman_model_list.id, endpointman_model_list.model as model FROM endpointman_model_list, endpointman_product_list WHERE endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_model_list.enabled = 1 AND endpointman_model_list.hidden = 0 AND product_id = '". $_GET['id']."'";
}   elseif ($_REQUEST['atype'] == "lines") {
        if(isset($_REQUEST['macid'])) {
            //die();
        } else {
            $sql = "SELECT max_lines FROM endpointman_model_list WHERE id = '". $_GET['id']."'";
        }
}

if (($_REQUEST['atype'] == "template") OR ($_REQUEST['atype'] == "template2")) {
	$out[0]['optionValue'] = 0;
	$out[0]['optionDisplay'] = "Custom...";
	$i=1;
} elseif ($_REQUEST['atype'] == "model") {
	$out[0]['optionValue'] = 0;
	$out[0]['optionDisplay'] = "";
	$i=1;
} else {
	$i=0;
}

if(($_REQUEST['atype'] == "lines") && (!isset($_REQUEST['macid']))) {
    $count = $db->getOne($sql);
    for($z=0;$z<$count;$z++) {
        $result[$z]['id'] = $z + 1;
        $result[$z]['model'] = $z + 1;
    }
} elseif(isset($_REQUEST['macid'])) {
    $result = linesAvailable($_REQUEST['macid']);
} else {
    $result = $db->getAll($sql,array(), DB_FETCHMODE_ASSOC);
}

foreach($result as $row) {
        if(isset($_REQUEST['macid'])) {
            $out[$i]['optionValue'] = $row['value'];
            $out[$i]['optionDisplay'] = $row['text'];
        } else {
            $out[$i]['optionValue'] = $row['id'];
            $out[$i]['optionDisplay'] = $row['model'];
        }
	$i++;
}


echo json_encode($out);