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
<?php
	if( $var["no_add"] == FALSE ){
?>
<center>
	<h3><?=_('Add New Template')?>:</h3><form action="config.php?type=tool&amp;display=epm_templates&amp;Submit=EditTemplate" method="POST">
	  <?=_('Template Name')?>:
	  <label>
	    <input type="text" name="template_name" id="template_name">
		<input type="hidden" name="page" value="template_manager">
	  </label>
	<?=_('Product Select')?>: 
	<label>
	  <select name="model_class" id="model_class">
		<?php
		if( isset( $var["class_list"] ) && is_array( $var["class_list"] ) ){
			$counter1 = 0;
			foreach( $var["class_list"] as $key1 => $value1 ){ 
?>
	    <option value="<?php echo $value1["value"];?>"><?php echo $value1["text"];?></option>
		<?php
				$counter1++;
			}
		}
?>
	  </select>
	</label>
        Clone Template From:
        <label>
	  <select name="model_clone" id="model_clone">
	  </select>
	</label>
	<label>
	  <input type="submit" name="button_save" value="<?=_('Save')?>">
	</label>
	</form>
<table width="80%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td><h3><?=_('Template Name')?></h3></td>
    <td><h3><?=_('Model Classification')?></h3></td>
    <td><h3><?=_('Model Clone')?></h3></td>
    <td align='center'><h3><?=_('Edit')?></h3></td>
    <td align='center'><h3><?=_('Delete')?></h3></td>
  </tr>
<?php
		if( isset( $var["templates_list"] ) && is_array( $var["templates_list"] ) ){
			$counter1 = 0;
			foreach( $var["templates_list"] as $key1 => $value1 ){ 
?>
  <tr>
    <td><?php echo $value1["name"];?></td>
    <td><?php echo $value1["model_class"];?></td>
    <td><?php echo $value1["model_clone"];?></td>

	<td align='center' width='9%'><a href="config.php?type=tool&amp;edit_template=true&amp;display=epm_templates&amp;custom=<?php echo $value1["custom"];?>&amp;id=<?php echo $value1["id"];?>"><img src='assets/endpointman/images/edit.png' ALT='<?=_('Edit')?>' border='0'></a></td>
	<td align='center' width='5%'><?php
			if( $value1["custom"] == 0 ){
?>
	<a href="config.php?type=tool&amp;delete_template=true&amp;display=epm_templates&amp;id=<?php echo $value1["id"];?>"><img src='assets/endpointman/images/delete.png' ALT='<?=_('Delete')?>' border='0'></a><?php
			}
?></td>
  </tr>
<?php
				$counter1++;
			}
		}
?>
</table>
</center>
<?php
	}
?>
<?php
	$tpl = new RainTPL( RainTPL::$tpl_dir . dirname("global_footer"));
	$tpl->assign( $var );
		$tpl->draw(basename("global_footer"));
?>