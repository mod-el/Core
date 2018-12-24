<div style="font-size: 0; min-width: 600px">
	<div class="module-cont" style="width: 40%">
		<div class="module">
			<div style="overflow: auto; padding: 0">
				<?php
				if (count($modules) > 0) {
					foreach ($modules as $m => $mod) {
						?>
						<div class="list-module" data-name="<?= entities($m) ?>" data-description="<?= entities($mod['description']) ?>" data-version="<?= $mod['current_version'] ?>" onclick="selectDownloadableModule(this)"><?= entities($mod['name']) ?></div><?php
					}
				} else {
					echo '<div style="padding: 10px">No new downloadable module</div>';
				}
				?>
			</div>
		</div>
	</div>

	<?php
	if (count($modules) > 0) {
		?>
		<div class="module-cont" style="width: 60%">
			<div class="module">
				<div id="downloadable-module-details"></div>
			</div>
			<div style="text-align: right; padding: 20px 0"><input type="button" value="Install selected modules" onclick="installSelectedModules()" /></div>
		</div>
		<?php
	}
	?>
</div>