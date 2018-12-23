<?php
if ($something_to_update) {
	?>
	<div style="float: right">
		[<a href="?update-all"<?php
		if ($something_edited) {
			echo ' onclick="if(!confirm(\'Some modules are marked as edited. Are you sure you want to overwrite them as well?\')) return false"';
		}
		?>> update all </a>]
	</div>
	<?php
}
?>

	<h2>Modules</h2>

	<div style="font-size: 0">
		<?php foreach ($modules as $module) { ?>
			<div class="module-cont">
				<div class="module<?= (!$module->official) ? ' not-official' : '' ?>" data-module="<?= $module->folder_name ?>">
					<?php include(INCLUDE_PATH . 'model/Core/templates/module.php'); ?>
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

<?php
if (isset($update_queue)) {
	?>
	<script>
		var updateQueue = <?=json_encode($update_queue)?>;
	</script>
	<?php
}
