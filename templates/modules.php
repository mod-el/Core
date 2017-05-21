<style>
    .modulo{
        display: inline-block;
        box-sizing: border-box;
        padding: 5px;
        width: calc(100% / 3);
        font-size: 12px;
        vertical-align: top;
    }

    .modulo > div{
        border: solid #CCC 1px;
        min-height: 50px;
        position: relative;
    }

    .modulo > div > div:first-of-type{
        box-sizing: border-box;
        padding: 15px;
    }

    .modulo.not-official > div{
        border: dashed #F00 1px;
    }

    .versione{
        font-style: italic;
        float: right;
    }

    .clickable, .list-module{
        cursor: pointer;
    }

    .list-module{
        padding: 5px 10px;
    }

    .clickable:hover, .list-module:hover, .list-module.selected{
        background: #D9ECFF;
    }

    .module-loading-bar{
        position: absolute;
        bottom: 0;
        width: 100%;
    }

    .module-loading-bar > div{
        width: 0%;
        height: 5px;
        background: #2693FF;
        -webkit-transition: width 0.4s ease-out;
        -moz-transition: width 0.4s ease-out;
        -o-transition: width 0.4s ease-out;
        -ms-transition: width 0.4s ease-out;
        transition: width 0.4s ease-out;
    }
</style>

<h2>Modules</h2>

<div style="font-size: 0">
	<?php foreach($this->options['modules'] as $m){ ?>
        <div class="modulo<?=(!$m->official) ? ' not-official' : ''?>">
            <div>
                <div id="module-<?=$m->folder_name?>"<?=($m->configurable) ? ' class="clickable" onclick="document.location.href=\''.PATH.'zk/modules/config/'.$m->folder_name.'\'"' : ''?>>
					<?php include(INCLUDE_PATH.'model/Core/templates/module.php'); ?>
                </div>
                <div class="module-loading-bar" id="loading-bar-<?=$m->folder_name?>" style="visibility: hidden"><div></div></div>
            </div>
        </div>
	<?php } ?>
    <div class="modulo" style="width: 100%">
        <div id="module-new" class="clickable" onclick="zkPopup({'url':'<?=PATH?>zk/new-module'})">
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