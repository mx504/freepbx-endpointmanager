<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require 'functions.inc';

$endpoint = new endpointmanager();

if($_REQUEST['pop_type'] == 'edit_template') {
    if(empty($_REQUEST['edit_id'])) {
        $message = _("No Device Selected to Edit!")."!";
        if(isset($message)) {
            $endpoint->display_message_box($message,$endpoint->tpl,0);
        }
        if(isset($error_message)) {
            $endpoint->display_message_box($error_message,$endpoint->tpl,1);
        }
    } else {
        $template_editor = TRUE;
        $sql = "UPDATE  endpointman_mac_list SET  model =  '".$_REQUEST['model_list']."' WHERE  id =".$_REQUEST['edit_id'];
        $endpoint->db->query($sql);
                        $endpoint->tpl->assign("silent_mode", 1);

        if ($_REQUEST['template_list'] == 0) {
            $endpoint->edit_template_display($_REQUEST['edit_id'],1);
        } else {
            $endpoint->edit_template_display($_REQUEST['template_list'],0);
        }
    }
}

if($_REQUEST['pop_type'] == "alt_cfg_edit") {
        if($_REQUEST['custom'] == 0) {
            $res = explode("_", $_REQUEST['value'],2);
            if($res[0] != 0) {
                if(isset($_REQUEST['button_save'])) {
                    $sql = "UPDATE endpointman_custom_configs SET data = '".addslashes($_REQUEST['config_text'])."' WHERE id = ".$res[0];
                    $endpoint->db->query($sql);
                    $message = "Saved to Database!";
                }
                $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $res[0];
                $row =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
                $endpoint->tpl->assign("save_as_name_value", $row['name']);
                $endpoint->tpl->assign("filename", $row['original_name']);
                $endpoint->tpl->assign("config_data", $row['data']);
            } else {
                $sql = "SELECT endpointman_brand_list.directory, endpointman_product_list.cfg_dir FROM endpointman_brand_list, endpointman_product_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = (SELECT product_id FROM endpointman_template_list WHERE id = ".$_REQUEST['tid'].")";
                $row =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
                //$res[1] = escapeshellcmd($res[1]);
                $file=PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$res[1];

                if(isset($_REQUEST['button_save'])) {
                    $wfh=fopen($file,'w');
                    fwrite($wfh,$_REQUEST['config_text']);
                    fclose($wfh);
                    $message = "Saved to Hard Drive!";
                }

                $handle = fopen($file, "rb");
                $contents = fread($handle, filesize($file));
                fclose($handle);
                $endpoint->tpl->assign("config_data", $contents);
            }
        } else {
            $res = explode("_", $_REQUEST['value'],2);
            if($res[0] != 0) {
                if(isset($_REQUEST['button_save'])) {
                    $sql = "UPDATE endpointman_custom_configs SET data = '".addslashes($_REQUEST['config_text'])."' WHERE id = ".$res[0];
                    $endpoint->db->query($sql);
                    $message = "Saved to Database!";
                }
                $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $res[0];
                $row =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
                $endpoint->tpl->assign("save_as_name_value", $row['name']);
                $endpoint->tpl->assign("filename", $row['original_name']);
                $endpoint->tpl->assign("config_data", $row['data']);
            } else {
                $sql = "SELECT endpointman_brand_list.directory, endpointman_product_list.cfg_dir FROM endpointman_brand_list, endpointman_product_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = (SELECT endpointman_model_list.product_id FROM endpointman_model_list, endpointman_mac_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_mac_list.id = ".$_REQUEST['tid'].")";
                $row =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
                //$res[1] = escapeshellcmd($res[1]);
                $file=PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$res[1];

                if(isset($_REQUEST['button_save'])) {
                    $wfh=fopen($file,'w');
                    fwrite($wfh,$_REQUEST['config_text']);
                    fclose($wfh);
                    $message = "Saved to Hard Drive!";
                }

                $handle = fopen($file, "rb");
                $contents = fread($handle, filesize($file));
                fclose($handle);
                $endpoint->tpl->assign("config_data", $contents);
            }
        }

        if(isset($_REQUEST['cfg_file'])) {
            $sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '". $_REQUEST['product_select'] ."'";
            $row =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);

            $config_files = explode(",",$row['config_files']);
            $file=PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$config_files[$_REQUEST['cfg_file']];
            if(isset($_REQUEST['config_text'])) {
                if(isset($_REQUEST['button_save'])) {
                    $wfh=fopen($file,'w');
                    fwrite($wfh,$_REQUEST['config_text']);
                    fclose($wfh);
                    $message = "Saved to Hard Drive!";
                }elseif(isset($_REQUEST['button_save_as'])) {
                    $sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES ("'.addslashes($_REQUEST['save_as_name']).'","'.addslashes($config_files[$_REQUEST['cfg_file']]).'","'.$_REQUEST['product_select'].'","'.addslashes($_REQUEST['config_text']).'")';
                    $endpoint->db->query($sql);
                    $message = "Saved to Database!";
                }
            }

            $handle = fopen($file, "rb");
            $contents = fread($handle, filesize($file));
            fclose($handle);

            if(isset($_REQUEST['sendid'])) {
                $endpoint->submit_config($row['directory'],$row['cfg_dir'],$config_files[$_REQUEST['cfg_file']],$contents);
                $message = 'Sent! Thanks :-)';
            }

            $endpoint->tpl->assign("save_as_name_value", $config_files[$_REQUEST['cfg_file']]);
            $endpoint->tpl->assign("config_data", $contents);
            $endpoint->tpl->assign("filename", $config_files[$_REQUEST['cfg_file']]);
            $endpoint->tpl->assign('sendid', $_REQUEST['cfg_file']);
            $endpoint->tpl->assign("type", 'file');
            $endpoint->tpl->assign("location", $file);

        } elseif(isset($_REQUEST['sql'])) {
            if(isset($_REQUEST['config_text'])) {
                if(isset($_REQUEST['button_save'])) {
                    $sql = "UPDATE endpointman_custom_configs SET data = '".addslashes($_REQUEST['config_text'])."' WHERE id = ".$_REQUEST['sql'];
                    $endpoint->db->query($sql);
                    $message = "Saved to Database!";
                }elseif(isset($_REQUEST['button_save_as'])) {
                    $sql = 'SELECT original_name FROM endpointman_custom_configs WHERE id = '.$_REQUEST['sql'];
                    $file_name = $endpoint->db->getOne($sql);

                    $sql = "INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES ('".addslashes($_REQUEST['save_as_name'])."','".addslashes($file_name)."','".$_REQUEST['product_select']."','".addslashes($_REQUEST['config_text'])."')";
                    $endpoint->db->query($sql);
                    $message = "Saved to Database!";
                }
            }
            if(isset($_REQUEST['sendid'])) {
                $sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '". $_REQUEST['product_select'] ."'";
                $row22 =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
                $endpoint->submit_config($row22['directory'],$row22['cfg_dir'],$config_files[$_REQUEST['cfg_file']],$contents);
                $message = 'Sent! Thanks! :-)';
            }
            $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $_REQUEST['sql'];
            $row =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
            $endpoint->tpl->assign("save_as_name_value", $row['name']);
            $endpoint->tpl->assign("filename", $row['original_name']);
            $endpoint->tpl->assign('sendid', $_REQUEST['sql']);
            $endpoint->tpl->assign("type", 'sql');
            $endpoint->tpl->assign("config_data", $row['data']);
        }
        if(isset($_REQUEST['product_select'])) {
            $sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id ='" . $_REQUEST['product_select'] . "'";

            $row =& $endpoint->db->getRow($sql, array(), DB_FETCHMODE_ASSOC);
            $config_files = explode(",",$row['config_files']);
            $i = 0;
            foreach($config_files as $config_files_data) {
                $file_list[$i]['value'] = $i;
                $file_list[$i]['text'] = $config_files_data;
                $i++;
            }
            $sql = "SELECT * FROM endpointman_custom_configs WHERE product_id = '". $_REQUEST['product_select'] . "'";
            $res =& $endpoint->db->query($sql);
            $i = 0;
            if($res->numRows()) {
                $data =& $endpoint->db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
                foreach($data as $row2) {
                    $sql_file_list[$i]['value'] = $row2['id'];
                    $sql_file_list[$i]['text'] = $row2['name'];
                    $sql_file_list[$i]['ref'] = $row2['original_name'];
                    $i++;
                }
            } else {
                $sql_file_list = NULL;
            }

            require(PHONE_MODULES_PATH.'setup.php');

            $class = "endpoint_" . $row['directory'] . "_" . $row['cfg_dir'] . '_phone';

            $phone_config = new $class();

            if((method_exists($phone_config,'display_options')) AND (method_exists($phone_config,'process_options'))) {
                if(isset($_REQUEST['phone_options'])) {
                    $endpoint->tpl->assign("options", $phone_config->process_options());
                } else {
                    $endpoint->tpl->assign("options", $phone_config->display_options());
                }
            }



            $template_file_list[0]['value'] = "template_data_custom.xml";
            $template_file_list[0]['text'] = "template_data_custom.xml";

            $sql = 'SELECT model FROM `endpointman_model_list` WHERE `product_id` LIKE CONVERT(_utf8 \'1-2\' USING latin1) COLLATE latin1_swedish_ci AND `enabled` = 1 AND `hidden` = 0';
            $data =& $endpoint->db->getAll($sql, array(), DB_FETCHMODE_ASSOC);
            $i = 1;
            foreach($data as $list) {
                $template_file_list[$i]['value'] = "template_data_" . $list['model'] . "_custom.xml";
                $template_file_list[$i]['text'] = "template_data_" . $list['model'] . "_custom.xml";
            }

            $endpoint->tpl->assign("template_file_list",$template_file_list);
            if(isset($_REQUEST['temp_file'])) {
                $endpoint->tpl->assign("temp_file",1);
            } else {
                $endpoint->tpl->assign("temp_file",NULL);
            }

            $endpoint->tpl->assign("file_list", $file_list);
            $endpoint->tpl->assign("sql_file_list", $sql_file_list);
            $endpoint->tpl->assign("product_selected", $_REQUEST['product_select']);
        }

        $error_message = NULL;
        foreach($endpoint->error as $key => $error) {
            $error_message .= $error;
            if($endpoint->global_cfg['debug']) {
                $error_message .= " Function: [".$key."]";
            }
            $error_message .= "<br />";
        }
        if(isset($message)) {
            $endpoint->display_message_box($message,$endpoint->tpl,0);
        }
        if(isset($error_message)) {
            $endpoint->display_message_box($error_message,$endpoint->tpl,1);
        }

        echo $endpoint->tpl->draw( 'alt_config_popup' );
}