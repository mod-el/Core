<?php
$module = $this->options['module'];
?>
<h2><?= entities($module->folder_name) ?></h2>

<div style="font-size: 0">
	<?php
	$filesTot = $module->getFilesByType();
	foreach ($filesTot as $type => $files) {
		?>
		<div class="module-files">
			<div>
				<div><b><?= entities($type) ?></b></div>
				<div>
					<?php
					foreach ($files as $f) {
						?>
						<div><?= entities($f) ?></div>
						<?php
					}
					?>
					<div>
						[<a href="#" onclick="makeNewFile('<?= entities($this->model->getRequest(2)) ?>', '<?= entities($type) ?>'); return false"> Make new </a>]
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	?>
</div>