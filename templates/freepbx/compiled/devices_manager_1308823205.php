<?php if(!defined('IN_RAINTPL')){exit('Hacker attempt');}?><?php
	$tpl = new RainTPL( RainTPL::$tpl_dir . dirname("global_header"));
	$tpl->assign( $var );
		$tpl->draw(basename("global_header"));
?>
<?php
	if( isset($var["show_error_box"]) ){
?>
<?php
		$tpl = new RainTPL( RainTPL::$tpl_dir . dirname("message_box"));
		$tpl->assign( $var );
				$tpl->draw(basename("message_box"));
?>
<?php
	}
?>
<br>
<script type="text/javascript" charset="utf-8"> 
    function add() {
        $('#adding').append('<input type="hidden" name="sub_type" value="add"/>');
        document.adding.submit();
    }
    function submit_go() {
        $('#spinner').toggle();
        $('#go').append('<input type="hidden" name="sub_type" value="go"/>');
        document.go.submit();
    }
    function submit_managed(type) {
        $('#managed').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.managed.submit();
    }
    function submit_wtype_managed(type,id) {
        $('#managed').append('<input type="hidden" name="edit_id" value="'+ id +'"/><input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.managed.submit();
    }
    function submit_unmanaged() {
        $('#unmanaged').append('<input type="hidden" name="sub_type" value="add_selected_phones"/>');
        document.unmanaged.submit();
    }
    function submit_global(type) {
        $('#globalmanaged').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.globalmanaged.submit();
    }
    function submit_global2(type) {
        $('#globalmanaged2').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.globalmanaged2.submit();
    }
    function submit_global3(type) {
        $('#globalmanaged3').append('<input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.globalmanaged3.submit();
    }
    function submit_wtype(type,id) {
        $('#adding').append('<input type="hidden" name="edit_id" value="'+ id +'"/><input type="hidden" name="sub_type" value="'+ type +'"/>');
        document.adding.submit();
    }
    function submit_wtype_sub(type,id,sub) {
        $('#adding').append('<input type="hidden" name="edit_id" value="'+ id +'"/><input type="hidden" name="sub_type" value="'+ type +'"/><input type="hidden" name="sub_type_sub" value="'+ sub +'"/>');
        document.adding.submit();
    }
    function delete_device(id) {
        if (confirm('Are you sure you want to delete this device?')) {
            submit_wtype('delete_device',id);
        }
    }
    function popitup(url, name) {
        newwindow=window.open(url + '&model_list=' + document.getElementById('model_new').value + '&template_list=' + document.getElementById('template_list').value + '&rand=' + new Date().getTime(),'name2','height=1000,width=950');
        if (window.focus) {newwindow.focus()}
        return false;
    }
</script>

<center>
    <?php
	if( $var["no_add"] == FALSE ){
?>
    <form id='go' action='config.php?type=tool&amp;display=epm_devices' method='POST'>
        <label><?=_('Search for new devices in netmask')?>
            <input name="netmask" type="text" value="<?php echo $var["srvip"];?>/24">
            <input type="Submit" name="button_go" id="button_go" onclick="submit_go();" value="<?=_('Go')?>">
        </label>
        <label>
            <input name="nmap" type="checkbox" value="1" checked><?=_('Use NMAP')?>
        </label>
    </form>
    <?php
	}
?>
</center>
<table align='center' width='90%'>
    <tr>
        <td align='center' width='45'></td>
        <td width="157" align='center'><h3><?=_('Mac Address')?></h3></td>
        <td width="187" align='center'><h3><?=_('Brand')?></h3></td>
        <td width="216" align='center'><h3><?=_('Model of Phone')?></h3></td>
        <td width="172" align='center'><h3><?=_('Line')?></h3></td>
        <td width="275" align='center'><h3><?=_('Extension Number')?></h3></td>
        <td width="154" align='center'><h3><?=_('Template')?></h3></td>

        <td width="234" align='center'></td>
        <td align='center'></td>
    </tr>
        <form name="adding" id="adding" action='config.php?type=tool&amp;display=epm_devices' method='POST' />

	<?php
	if( $var["no_add"] == FALSE ){
?>
    <tr>
    <td align='center' width='2%'>&nbsp;</td>

    <td align='center'>
	<?php
		if( $var["mode"] == 'EDIT' ){
?>
	<?php echo $var["mac"];?>
	<?php
		}
		else{
?>
        <input name='mac' type='text' tabindex='1' size="17" maxlength="17">
	<?php
		}
?></td>
    <td align='center'>  <label>
	<?php
		if( $var["mode"] == 'EDIT' ){
?>
            <?php echo $var["name"];?>
	<?php
		}
		else{
?>
            <select name="brand_list" id="brand_edit">
                <?php
			if( isset( $var["brand_ava"] ) && is_array( $var["brand_ava"] ) ){
				$counter1 = 0;
				foreach( $var["brand_ava"] as $key1 => $value1 ){ 
?>
                <option value="<?php echo $value1["value"];?>" <?php
				if( isset($value1["selected"]) ){
?>selected<?php
				}
?>><?php echo $value1["text"];?></option>
                <?php
					$counter1++;
				}
			}
?>
            </select></label>
	<?php
		}
?>
    </td>
    <td align='center'>
        <label>
	<?php
		if( $var["mode"] == 'EDIT' ){
?>
            <input name="display" type="hidden" value="epm_devices">
            <select name="model_list" id="model_new">
                <?php
			if( isset( $var["models_ava"] ) && is_array( $var["models_ava"] ) ){
				$counter1 = 0;
				foreach( $var["models_ava"] as $key1 => $value1 ){ 
?>
                <option value="<?php echo $value1["value"];?>" <?php
				if( !empty($value1["selected"]) ){
?>selected<?php
				}
?>><?php echo $value1["text"];?></option>
                <?php
					$counter1++;
				}
			}
?>
            </select>
	<?php
		}
		else{
?>
            <select name="model_list" id="model_new"><option></option></select>
	<?php
		}
?>
        </label></td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'>  <div id="demo"><select name="template_list" id="template_list">
                <?php
		if( isset( $var["display_templates"] ) && is_array( $var["display_templates"] ) ){
			$counter1 = 0;
			foreach( $var["display_templates"] as $key1 => $value1 ){ 
?>
                <option value="<?php echo $value1["value"];?>" <?php
			if( isset($value1["selected"]) ){
?>selected<?php
			}
?>><?php echo $value1["text"];?></option>
                <?php
				$counter1++;
			}
		}
?>
            </select>
            <a href="#" onclick="return popitup('config.php?display=epm_config&amp;quietmode=1&amp;handler=file&amp;file=popup.html.php&amp;module=endpointman&amp;pop_type=edit_template&amp;edit_id=<?php echo $var["edit_id"];?>', 'Template Editor')"><img src='assets/endpointman/images/edit.png' title="Edit Template Selected on the Left" border="0"></a></div>
        </label></td>
    <td align='center'>
	<?php
		if( $var["mode"] == 'EDIT' ){
?>
        <input type='Submit' name='button_save' onclick="submit_wtype_sub('edit',<?php echo $var["edit_id"];?>,'button_save');" value='<?=_('Save')?>'>
	<?php
		}
		else{
?>
               <input type='Submit' name='button_add' onclick="add();" value='<?=_('Add')?>'>
	<?php
		}
?>
    </td>

    <td align='center'><?php
		if( $var["mode"] != 'EDIT' ){
?><input type='reset' value='<?=_('Reset')?>'><?php
		}
?></td>
</tr>
<?php
		if( isset( $var["line_list_edit"] ) && is_array( $var["line_list_edit"] ) ){
			$counter1 = 0;
			foreach( $var["line_list_edit"] as $key1 => $value1 ){ 
?>
<tr>
    <td align='center' width='2%'>&nbsp;</td>

    <td align='center'></td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'>
        <label>
            <select name="line_list_<?php echo $value1["luid"];?>" id="line_list" >
                <?php
			if( isset( $value1["line_list"] ) && is_array( $value1["line_list"] ) ){
				$counter2 = 0;
				foreach( $value1["line_list"] as $key2 => $value2 ){ 
?>
                <option value="<?php echo $value2["value"];?>" <?php
				if( isset($value2["selected"]) ){
?>selected<?php
				}
?>><?php echo $value2["text"];?></option>
                <?php
					$counter2++;
				}
			}
?>
            </select>
        </label></td>
    <td align='center'>
        <select name="ext_list_<?php echo $value1["luid"];?>" id="select">
            <?php
			if( isset( $value1["reg_list"] ) && is_array( $value1["reg_list"] ) ){
				$counter2 = 0;
				foreach( $value1["reg_list"] as $key2 => $value2 ){ 
?>
            <option value="<?php echo $value2["value"];?>" <?php
				if( isset($value2["selected"]) ){
?>selected<?php
				}
?>><?php echo $value2["text"];?></option>
            <?php
					$counter2++;
				}
			}
?>
        </select>
        </label>
    </td>
    <td align='center'></td>
    <td align='center'><?php
			if( !isset($var["disabled_delete_line"]) ){
?><div id="demo"><a href="#" onclick="submit_wtype_sub('edit',<?php echo $value1["luid"];?>,'delete');"><img src="assets/endpointman/images/delete.png" title="Delete Line from Device to the Left"></div><?php
			}
?></a></td>
    <td align='center'></td>
</tr>
<?php
				$counter1++;
			}
		}
?>
<?php
		if( $var["mode"] != 'EDIT' ){
?>
<tr>
    <td align='center' width='2%'>&nbsp;</td>

    <td align='center'></td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'>
        <label>
            <select name="line_list" id="line_list" >
                <option></option>
            </select>
        </label></td>
    <td align='center'>
        <select name="ext_list" id="select">
            <?php
			if( isset( $var["display_ext"] ) && is_array( $var["display_ext"] ) ){
				$counter1 = 0;
				foreach( $var["display_ext"] as $key1 => $value1 ){ 
?>
            <option value="<?php echo $value1["value"];?>"><?php echo $value1["text"];?></option>
            <?php
					$counter1++;
				}
			}
?>
        </select>
        </label>
    </td>
    <td align='center'></td>
    <td align='center'></a></td>
    <td align='center'></td>
</tr>
<?php
		}
?>
<?php
		if( $var["mode"] == 'EDIT' ){
?>
<tr>
    <td align='center'>&nbsp;</td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'>&nbsp;</td>
    <td align='center'>&nbsp;</td>
    <td align='center'></td>
    <td align='center'><div id="demo"><a href="#" onclick="submit_wtype_sub('edit',<?php echo $var["edit_id"];?>,'add_line_x');"><img src="assets/endpointman/images/add.png" alt="Add Line" border="0" width="24" height="24" title="Add a Line to the device currently being edited"></a></div></td>
    <td align='center'></td>
</tr> 
<?php
		}
		else{
?>
<tr>
    <td align='center'>&nbsp;</td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'></td>
    <td align='center'>&nbsp;</td>
    <td align='center'>&nbsp;</td>
    <td align='center'></td>
    <td align='center'><a href="#" onclick="add_line();"><img src="assets/endpointman/images/add.png" alt="Add Line" border="0" width="24" height="24"></a></td>
    <td align='center'></td>
</tr>
<?php
		}
?>
<?php
	}
?>
</table>
</form>

<?php
	if( $var["searched"] == 1 ){
?>
<table width='90%' align='center'>
    <tr>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td colspan="3" align='center'><h3><?=_('Unmanaged Extensions')?></h3></td>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
    </tr>
	<?php
		if( is_array($var["unmanaged"]) ){
?>
    <form id="unmanaged" action='' method='POST'>
		<?php
			if( isset( $var["unmanaged"] ) && is_array( $var["unmanaged"] ) ){
				$counter1 = 0;
				foreach( $var["unmanaged"] as $key1 => $value1 ){ 
?>
        <input name="mac_<?php echo $value1["id"];?>" type="hidden" value="<?php echo $value1["mac_strip"];?>">
        <input name="brand_<?php echo $value1["id"];?>" type="hidden" value="<?php echo $value1["brand_id"];?>">
        <tr>
            <td align='center' width='20'><input type="checkbox" name="add[]" value="<?php echo $value1["id"];?>"></td>
            <td align='center' width='148'><?php echo $value1["mac_strip"];?><br />(<?php echo $value1["ip"];?>)</td>
            <td width="188" align='center'><?php echo $value1["brand"];?></td>
            <td width="216" align='center'>

                <select name="model_list_<?php echo $value1["id"];?>">

	    <?php
				if( isset( $value1["list"] ) && is_array( $value1["list"] ) ){
					$counter2 = 0;
					foreach( $value1["list"] as $key2 => $value2 ){ 
?>

                    <option value="<?php echo $value2["id"];?>"><?php echo $value2["model"];?></option>

	      <?php
						$counter2++;
					}
				}
?>

                </select></td>
            <td width="141" align='center'>

            </td>

            <td width="276" align='center'>
                <select name="ext_list_<?php echo $value1["id"];?>" id="ext">

	    <?php
				if( isset( $var["display_ext"] ) && is_array( $var["display_ext"] ) ){
					$counter2 = 0;
					foreach( $var["display_ext"] as $key2 => $value2 ){ 
?>

                    <option value="<?php echo $value2["value"];?>"><?php echo $value2["text"];?></option>

	      <?php
						$counter2++;
					}
				}
?>

                </select></td>
            <td align='center' width='220'>&nbsp;</td>
            <td align='center' width='154'></td>
            <td align='center' width='73'>&nbsp;</td>
        </tr>
		<?php
					$counter1++;
				}
			}
?>
        <tr>
        <table width="90%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td><center><input type="submit" name="button_add_selected_phones" onclick="submit_unmanaged();" value="<?=_('Add Selected Phones')?>"><br /><input type="checkbox" name="reboot_sel">Reboot Phones</center></td>
            </tr>
        </table>
        </tr>
    </form>
	<?php
		}
?>
</table>
<?php
	}
?>

<table width='90%' align='center' id='devList'>
    <tr class="headerRow">
        <td width="3%" align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td align='center'>&nbsp;</td>
        <td colspan="3" style='vertical-align:text-bottom;text-align:center;'><h3><?=_('Current Managed Extensions')?></h3></td>
        <td align='center'>&nbsp;</td>
        <td align='center'></td>
        <td align='center'></td>
    </tr>
    <tr class="headerRow">
        <td width="3%" align='center'>&nbsp;</td>
        <td align='center'><h3>Mac Address</h3></td>
        <td align='center'><h3>Brand</h3></td>
        <td align='center'><h3>Model of Phone</h3></td>
        <td align='center'><h3>Line</h3></td>
        <td align='center'><h3>Extension Number</h3></td>
        <td align='center'><h3>Template</h3></td>
        <td align='center'><h3>Edit</h3></td>
        <td align='center'><h3>Delete</h3></td>
    </tr>
    <form id="managed" action='config.php?type=tool&amp;display=epm_devices' method='POST'>
	<?php
	if( isset( $var["list"] ) && is_array( $var["list"] ) ){
		$counter1 = 0;
		foreach( $var["list"] as $key1 => $value1 ){ 
?>
        <tr class="headerRow">
            <td align='center' ><div id="demo"><a><img src="assets/endpointman/images/bullet_plus.png" id="imgrowGroup<?php echo $value1["master_id"];?>" alt="" onclick="toggleDisplay(document.getElementById('devList'),'headerRow','rowGroup<?php echo $value1["master_id"];?>')" title="Click to Expand Line Information"><input type="checkbox" name="selected[]" value="<?php echo $value1["id"];?>"></a></div></td>
            <td align='center' width='11%'><?php echo $value1["mac"];?></td>
            <td width="13%" align='center'><?php echo $value1["name"];?></td>
            <td width="14%" align='center'><?php echo $value1["model"];?></td>
            <td width="10%" align='center'><div id="demo"><a><img src="assets/endpointman/images/expand.png" id="img2rowGroup<?php echo $value1["master_id"];?>" onclick="toggleDisplay(document.getElementById('devList'),'headerRow','rowGroup<?php echo $value1["master_id"];?>')" title="Click to Expand Line Information"></a></div></td>
            <td width="19%" align='center'><div id="demo"><a><img src="assets/endpointman/images/expand.png" id="img3rowGroup<?php echo $value1["master_id"];?>" onclick="toggleDisplay(document.getElementById('devList'),'headerRow','rowGroup<?php echo $value1["master_id"];?>')" title="Click to Expand Line Information"></a></div></td>
            <td align='center' width='15%'><?php echo $value1["template_name"];?></td>
            <td align='center' width='9%'><div id="demo"><a href="#" onclick="submit_wtype('edit',<?php echo $value1["id"];?>);"><img src='assets/endpointman/images/edit.png' ALT='<?=_('Edit')?>' title='Edit Device' border='0'></a></div></td>
            <td align='center' width='7%'><div id="demo"><a href="#" onclick="delete_device(<?php echo $value1["id"];?>);"><img src='assets/endpointman/images/delete.png' ALT='<?=_('Delete')?>' title='Delete Device' border='0'></a></div></td>
        </tr>
        <?php
		if( isset( $value1["line"] ) && is_array( $value1["line"] ) ){
			$counter2 = 0;
			foreach( $value1["line"] as $key2 => $value2 ){ 
?>
        <tr class="rowGroup<?php echo $value2["master_id"];?>" style="display:none;">
            <td align='center' ></td>
            <td align='center' width='11%'></td>
            <td width="13%" align='center'></td>
            <td width="14%" align='center'></td>
            <td width="10%" align='center'><?php echo $value2["line"];?></td>
            <td width="19%" align='center'><?php echo $value2["ext"];?> - <?php echo $value2["description"];?></td>
            <td align='center' width='15%'></td>
            <td align='center' width='9%'></td>
            <td align='center' width='7%'><div id="demo"><a href="#" onclick="submit_wtype('delete_line',<?php echo $value2["luid"];?>);"><img src='assets/endpointman/images/delete.png' ALT='<?=_('Delete')?>' title='Delete Line' border='0'></a></div></td>
        </tr>
        <?php
				$counter2++;
			}
		}
?>
	<?php
			$counter1++;
		}
	}
?>
</table>

<hr>
<center>
    <h4>
        <?=_('Selected Phone(s) Options')?>
    </h4>
</center>
<table width='90%' align='center'>
    <tr>
        <td width="26%" align='center'><input type="submit" name="button_delete_selected_phones" onclick="submit_managed('delete_selected_phones');" value="<?=('Delete Selected Phones')?>"></td>
        <td width="26%" align='center'><input type="submit" name="button_rebuild_selected" onclick="submit_managed('rebuild_selected_phones');" value="<?=_('Rebuild Configs for Selected Phones')?>"><br /><input type="checkbox" name="reboot" checked>Reboot Phones</td>
        <td width="32%" align='center'><?=_('Change Selected Phones to')?>: <br /><?=_('Brand')?>:<select name="brand_list_selected" id="brand_list_selected">
                <?php
	if( isset( $var["brand_ava"] ) && is_array( $var["brand_ava"] ) ){
		$counter1 = 0;
		foreach( $var["brand_ava"] as $key1 => $value1 ){ 
?>
                <option value="<?php echo $value1["value"];?>" <?php
		if( isset($value1["selected"]) ){
?>selected<?php
		}
?>><?php echo $value1["text"];?></option>
                <?php
			$counter1++;
		}
	}
?>
            </select> <?=_('Model')?>: <select name="model_list_selected" id="model_list_selected"><option></option></select><br /><input type="submit" name="button_update_phones" onclick="submit_managed('change_brand');" value="<?=_('Update Phones')?>"><br /><input type="checkbox" name="reboot_change" checked>Reboot Phones</td>
    </tr>
</table>
</form>
<hr>
<center>
    <h4><?=_('Global Phone Options')?></h4>
</center>
<table width='90%' align='center'>
    <tr>
        <td width="26%" align='center'><form action='' name='globalmanaged' id='globalmanaged' method='POST'><input type='Submit' name='button_rebuild_configs_for_all_phones' onclick="submit_global('rebuild_configs_for_all_phones');" value='<?=_('Rebuild Configs for All Phones')?>'><br /><input type="checkbox" name="reboot" checked>Reboot Phones</form></td>
        <td width="32%" align='center'><form action='' name='globalmanaged2' id='globalmanaged2' method='POST'><select name="rb_brand">
	<?php
	if( isset( $var["brand_ava"] ) && is_array( $var["brand_ava"] ) ){
		$counter1 = 0;
		foreach( $var["brand_ava"] as $key1 => $value1 ){ 
?>
                    <option value="<?php echo $value1["value"];?>"><?php echo $value1["text"];?></option>
	<?php
			$counter1++;
		}
	}
?>
                </select>
                <input type='Submit' name='button_reboot_this_brand' onclick="submit_global2('reboot_brand');" value='<?=_('Reboot This Brand')?>'></form></td>
        <td width="42%" align='center'><form action='' name='globalmanaged3' id='globalmanaged3' method='POST'><?=_('Reconfigure all')?> <select name="product_select" id="product_select">
	<?php
	if( isset( $var["product_list"] ) && is_array( $var["product_list"] ) ){
		$counter1 = 0;
		foreach( $var["product_list"] as $key1 => $value1 ){ 
?>
                    <option value="<?php echo $value1["value"];?>"><?php echo $value1["text"];?></option>
	<?php
			$counter1++;
		}
	}
?>
                </select> <?=_('with')?>
                <label>
                    <select name="template_selector" id="template_selector">
                        <option></option>
                    </select>
                    <input type="submit" name="button_rebuild_reboot" onclick="submit_global3('rebuild_reboot');" value="<?=_('Rebuild')?>"><br /><input type="checkbox" name="reboot" checked>Reboot Phones
                </label></td>
    </tr>
</table>
</form>

<?php
	if( !isset($var["disable_help"]) ){
?>
<script>
    $("#demo img[title]").tooltip();
</script>
<?php
	}
?>
<?php
	$tpl = new RainTPL( RainTPL::$tpl_dir . dirname("global_footer"));
	$tpl->assign( $var );
		$tpl->draw(basename("global_footer"));
?>