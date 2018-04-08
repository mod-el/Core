<div style="font-size: 0; min-width: 600px">
    <div class="modulo" style="width: 40%">
        <div>
            <div style="overflow: auto; padding: 0">
				<?php
				if (count($this->options['modules']) > 0) {
					foreach ($this->options['modules'] as $m => $mod) {
						?>
                        <div class="list-module" data-name="<?= entities($m) ?>" data-description="<?= entities($mod['description']) ?>" data-version="<?= $mod['current_version'] ?>" onclick="selectDownloadableModule(this)"><?= entities($mod['name']) ?></div><?php
					}
				} else {
					echo 'No new downloadable module';
				}
				?>
            </div>
        </div>
    </div>

    <div class="modulo" style="width: 60%">
        <div>
            <div id="downloadable-module-details"></div>
        </div>
    </div>
</div>