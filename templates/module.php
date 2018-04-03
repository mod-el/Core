<?php
if(!isset($m))
	$m = $this->options['module'];
?>
<div>
    <div class="versione"><?=entities($m->version)?></div>
    <b><?=entities($m->name)?></b>
</div>
<?php if($m->description){ ?><p><i><?=entities($m->description, true)?></i></p><?php } ?>
<p>
	<?php
	if($m->new_version){ ?><b style="color: #0C0">New version <?=$m->new_version!==true ? ': '.$m->new_version : ''?></b> [<a href="#" onclick="event.stopPropagation(); updateModule('<?=$m->folder_name?>'); return false"> update </a>]<?php }
    elseif($m->corrupted){ ?><b style="color: #F00">Edited!</b> [<a href="#" onclick="event.stopPropagation(); updateModule('<?=$m->folder_name?>'); return false"> restore </a>]<br /><?php }
	?>
</p>

<div class="md5"><?=entities($m->version_md5)?></div>