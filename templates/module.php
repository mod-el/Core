<div<?= ($module->isConfigurable()) ? ' class="clickable" onclick="document.location.href=\'' . PATH . 'zk/modules/config/' . entities($module->folder_name) . '\'"' : '' ?>>
	<div>
		<div class="module-version"><?= entities($module->version) ?></div>
		<b><?= entities($module->name) ?></b>
	</div>
	<?php if ($module->description) { ?><p><i><?= entities($module->description, true) ?></i></p><?php } ?>
</div>

<div>
	<div class="md5"><?= entities($module->version_md5) ?></div>
	<div class="module-selector" onclick="toggleModuleSelection('<?= $module->folder_name ?>')"></div>
	<?php
	if ($module->new_version) {
		?>
		<b style="color: #0C0">New version <?= $module->new_version !== true ? ': ' . $module->new_version : '' ?></b>
		<?php /*[<a href="#" onclick="event.stopPropagation(); queueModuleUpdate('<?= $module->folder_name ?>'); return false"> update </a>] */ ?>
		<?php
	} elseif ($module->corrupted) {
		?>
		<b style="color: #F00">Edited!</b>
		<?php /*[<a href="#" onclick="event.stopPropagation(); queueModuleUpdate('<?= $module->folder_name ?>'); return false"> restore </a>] */ ?>
		<?php
	}
	?>
</div>