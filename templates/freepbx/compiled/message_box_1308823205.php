<?php if(!defined('IN_RAINTPL')){exit('Hacker attempt');}?><div style="background-color:#f8f8ff; border: 1px solid #aaaaff; padding:10px;font-family:arial;color:<?php
	if( $var["fatal_error"] == 1 ){
?>red<?php
	}
	else{
?>grey<?php
	}
?>;font-size:20px;text-align:center"><b><?php echo $var["error_message"];?></b><?php
	if( $var["advanced_debug"] == 1 ){
?><br /><h5 style="font-family:arial;color:black;font-size:12px;text-align:left"><pre><u>Backtrace</u><br /><?=debug_print_backtrace();?><br /><u>Extended Backtrace</u><br /><?=var_dump(debug_backtrace());?><br /><u>Last Error</u><br /><?=print_r(error_get_last());?></pre></h5><?php
	}
?></div>