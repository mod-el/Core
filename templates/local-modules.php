<h2>App Modules</h2>

<div style="font-size: 0">
	<?php foreach ($modules as $m) { ?>
		<div class="module-cont">
			<div class="module">
				<div class="clickable" onclick="document.location.href='<?= PATH ?>zk/local-modules/<?= entities($m->folder_name) ?>'">
					<div class="module-version"><?= entities($m->version ?? '') ?></div>
					<b><?= entities($m->name) ?></b>
					<?php
					if (isset($m->description)) {
						?><p><i><?= entities($m->description, true) ?></i></p><?php
					}
					?>
				</div>
			</div>
		</div>
	<?php } ?>
</div>
