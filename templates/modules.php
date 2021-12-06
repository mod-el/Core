<?php
$toBeUpdated = false;

foreach ($modules as $module) {
	if ($module->new_version or $module->corrupted) {
		$toBeUpdated = true;
		break;
	}
}

?>
<div id="header-right">
	[<a href="#" onclick="removeModules(); return false"> remove selected </a>]
	<?php
	if ($toBeUpdated) {
		?>
		[<a href="#" onclick="updateSelectedModules(); return false"> update selected </a>]
		                                                                                  [
		<a href="#" onclick="updateAllModules(); return false"> update all </a>]
		<?php
	}
	?>
</div>

<h2>Modules</h2>

<div id="update-info" style="display: none">
	<div id="update-action"></div>
	<div id="update-loading-bar">
		<div style="width: 0%"></div>
	</div>
</div>

<div style="font-size: 0">
	<?php foreach ($modules as $module) { ?>
		<div class="module-cont">
			<div class="module<?= (!$module->official) ? ' not-official' : '' ?>" data-module="<?= $module->folder_name ?>" data-priority="<?= $priorities[$module->folder_name] ?? 999 ?>"<?= $module->corrupted ? ' data-corrupted="1"' : '' ?><?= ($module->corrupted or $module->new_version) ? ' data-update="1"' : '' ?>>
				<div<?= ($module->isConfigurable()) ? ' class="clickable" onclick="document.location.href=\'' . PATH . 'zk/modules/config/' . entities($module->folder_name) . '\'"' : '' ?>>
					<div>
						<div class="module-version"><?= entities($module->version ?? '-') ?></div>
						<b><?= entities($module->name) ?></b>
					</div>
					<?php
					if (isset($module->description)) {
						?>
						<p><i><?= entities($module->description, true) ?></i></p>
						<?php
					}

					if (!$module->installed) {
						?><i style="color: #C00">(not initialized)</i><?php
					}
					?>
				</div>

				<div>
					<div class="md5"><?= entities($module->version_md5) ?></div>
					<div class="module-selector" onclick="toggleModuleSelection('<?= $module->folder_name ?>')"></div>
					<?php
					if ($module->new_version) {
						?>
						<b style="color: #0C0">New version <?= $module->new_version !== $module->version ? ': ' . $module->new_version : '' ?></b>
						<?php
					} elseif ($module->corrupted) {
						?>
						<b style="color: #F00">Edited!</b>
						<?php
					}
					?>
				</div>
			</div>
		</div>
	<?php } ?>
	<div class="module-cont" style="width: 100%">
		<div id="module-new" class="clickable module" onclick="lightboxNewModule()">
			<div>
				<b>Install new module</b>
			</div>
		</div>
	</div>
</div>

<script>
	<?php
	if (count($_SESSION['update-queue'] ?? []) > 0) {
	?>
	updateQueue = <?=json_encode($_SESSION['update-queue'] ?? [])?>;
	<?php
	}
	?>
</script>
