<h2>App Modules</h2>

<div style="font-size: 0">
	<?php foreach ($this->options['modules'] as $m) { ?>
        <div class="modulo">
            <div>
                <div class="clickable" onclick="document.location.href='<?= PATH ?>zk/local-modules/<?= entities($m->folder_name) ?>'">
                    <div>
                        <div class="versione"><?= entities($m->version) ?></div>
                        <b><?= entities($m->name) ?></b>
						<?php if ($m->description) { ?><p><i><?= entities($m->description, true) ?></i></p><?php } ?>
                    </div>
                </div>
            </div>
        </div>
	<?php } ?>
</div>