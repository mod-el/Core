<div>
	<div class="versione"><?= entities($module->version) ?></div>
	<b><?= entities($module->name) ?></b>
</div>
<?php if ($module->description) { ?><p><i><?= entities($module->description, true) ?></i></p><?php } ?>
<p>
	<?php
	if ($module->new_version) {
		?>
		<b style="color: #0C0">New version <?= $module->new_version !== true ? ': ' . $module->new_version : '' ?></b> [
		<a href="#" onclick="event.stopPropagation(); queueModuleUpdate('<?= $module->folder_name ?>'); return false"> update </a>]
		<?php
	} elseif ($module->corrupted) {
		?>
		<b style="color: #F00">Edited!</b> [
		<a href="#" onclick="event.stopPropagation(); queueModuleUpdate('<?= $module->folder_name ?>'); return false"> restore </a>]
		<br/><?php
	}
	?>
</p>

<div class="md5"><?= entities($module->version_md5) ?></div>