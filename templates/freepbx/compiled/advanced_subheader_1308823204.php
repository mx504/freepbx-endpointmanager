<?php if(!defined('IN_RAINTPL')){exit('Hacker attempt');}?><table align='center' width='90%'>
<tr>
    <td align='center'><?php
	if( $var["subhead_area"] == 'settings' ){
?><h4 style="color:#ff9933;"><?=_('Settings')?></h4><?php
	}
	else{
?><h4><a href='config.php?type=tool&amp;display=epm_advanced&amp;subpage=settings'><?=_('Settings')?></a></h4><?php
	}
?></td>
    <td align='center'><?php
	if( $var["subhead_area"] == 'oui_manager' ){
?><h4 style="color:#ff9933;"><?=_('OUI Manager')?></h4><?php
	}
	else{
?><h4><a href='config.php?type=tool&amp;display=epm_advanced&amp;subpage=oui_manager'><?=_('OUI Manager')?></a></h4><?php
	}
?></td>
    <td align='center'><?php
	if( $var["subhead_area"] == 'poce' ){
?><h4 style="color:#ff9933;"><?=_('Product Options/Configuration Editor')?></h4><?php
	}
	else{
?><h4><a href='config.php?type=tool&amp;display=epm_advanced&amp;subpage=poce'><?=_('Product Options/Configuration Editor')?></a></h4><?php
	}
?></td>
</tr>
<tr>
    <td align='center'><?php
	if( $var["subhead_area"] == 'iedl' ){
?><h4 style="color:#ff9933;"><?=_('Import/Export My Devices List')?></h4><?php
	}
	else{
?><h4><a href='config.php?type=tool&amp;display=epm_advanced&amp;subpage=iedl'><?=_('Import/Export My Devices List')?></a></h4><?php
	}
?></td>
<td align='center'><?php
	if( $var["subhead_area"] == 'manual_upload' ){
?><h4 style="color:#ff9933;"><?=_('Manual Endpoint Modules Upload/Export')?></h4><?php
	}
	else{
?><h4><a href='config.php?type=tool&amp;display=epm_advanced&amp;subpage=manual_upload'><?=_('Manual Endpoint Modules Upload/Export')?></a></h4><?php
	}
?></td>
<td align='center'><?php
	if( $var["subhead_area"] == 'sh_manager' ){
?><h4 style="color:#ff9933;"><?=_('Show/Hide Brands/Models')?></h4><?php
	}
	else{
?><h4><a href='config.php?type=tool&amp;display=epm_advanced&amp;subpage=sh_manager'><?=_('Show/Hide Brands/Models')?></a></h4><?php
	}
?></td>
</tr>
</table>
<hr width='90%'>