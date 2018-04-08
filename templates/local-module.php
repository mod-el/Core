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
                </div>
            </div>
        </div>
		<?php
	}
	?>
</div>