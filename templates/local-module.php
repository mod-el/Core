<h2><?= entities($module->folder_name) ?></h2>

<div style="font-size: 0">
	<?php
	$filesTot = $module->getFilesByType();
	foreach ($filesTot as $type => $files) {
		$maker = new \Model\Core\Maker($this->model);
		$fileTypeData = $maker->getFileTypeData($type);
		?>
		<div class="module-files">
			<div>
				<div><b><?= entities($type) ?></b></div>
				<div>
					<?php
					foreach ($files as $f) {
						?>
						<div>
							<?php
							echo entities($f);
							foreach (($fileTypeData['actions'] ?? []) as $actionName => $actionOptions) {
								echo ' <a href="#" onclick="performActionOnFile(\'' . entities($this->model->getRequest(2)) . '\', \'' . entities($type) . '\', \'' . entities($f) . '\', \'' . $actionName . '\'); return false" title="' . entities($actionName) . '"><i class="' . $actionOptions['fa-icon'] . '"></i></a>';
							}
							?>
						</div>
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