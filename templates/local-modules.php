<h2>App Modules</h2>

<div style="font-size: 0">
	<?php foreach($this->options['modules'] as $m){ ?>
        <div class="modulo">
            <div>
                <div>
                    <div>
                        <div class="versione"><?=entities($m->version)?></div>
                        <b><?=entities($m->name)?></b>
                    </div>
					<?php if($m->description){ ?><p><i><?=entities($m->description, true)?></i></p><?php } ?>
                </div>
            </div>
        </div>
	<?php } ?>
    <?/*<div class="modulo" style="width: 100%">
        <div id="module-new" class="clickable" onclick="lightboxNewModule()">
            <div>
                <b>Install new module</b>
            </div>
        </div>
    </div>*/?>
</div>