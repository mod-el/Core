<h2>Modules</h2>

<div style="font-size: 0">
	<?php foreach($this->options['modules'] as $m){ ?>
        <div class="modulo<?=(!$m->official) ? ' not-official' : ''?>">
            <div>
                <div id="module-<?=$m->folder_name?>"<?=($m->isConfigurable()) ? ' class="clickable" onclick="document.location.href=\''.PATH.'zk/modules/config/'.$m->folder_name.'\'"' : ''?>>
					<?php include(INCLUDE_PATH.'model/Core/templates/module.php'); ?>
                </div>
                <div class="module-loading-bar" id="loading-bar-<?=$m->folder_name?>" style="visibility: hidden"><div></div></div>
            </div>
        </div>
	<?php } ?>
    <div class="modulo" style="width: 100%">
        <div id="module-new" class="clickable" onclick="lightboxNewModule()">
            <div>
                <b>Install new module</b>
            </div>
        </div>
    </div>
</div>

<?php
if(isset($this->options['update-queue'])){
	?>
    <script>
		var updateQueue = <?=json_encode($this->options['update-queue'])?>;
    </script>
	<?php
}