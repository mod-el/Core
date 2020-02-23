<!DOCTYPE html>

<html lang="en">

<head>
	<title>Model Framework Control Panel - <?= APP_NAME ?></title>
	<script type="text/javascript">
		var PATHBASE = '<?=PATH?>';
		var PATH = '<?=$this->model->prefix()?>';
		var REQUEST = <?=json_encode($this->model->getRequest())?>;
	</script>
	<link rel="stylesheet" href="<?= PATH ?>model/Core/files/style.css?v=1" type="text/css"/>
	<script src="<?= PATH ?>model/Core/files/js.js?v=1" type="text/javascript"></script>
	<script src="https://kit.fontawesome.com/b635b5b4c3.js" defer crossorigin="anonymous"></script>
</head>

<body>
<div id="tools">
	<p><b>Tools</b></p>
	<div id="cmd-make-cache"><a href="#" onclick="cmd('make-cache').then(r => alert(r)); return false">Update cache</a>
	</div>
	<div id="cmd-empty-session">
		<a href="#" onclick="cmd('empty-session').then(r => alert(r)); return false">Empty session</a></div>
	<div><a href="<?= PATH ?>zk/inspect-session" target="_blank">Inspect session</a></div>
</div>

<div id="header">
	<a href="<?= PATH ?>zk/modules">Modules</a> <a href="<?= PATH ?>zk/local-modules">App</a>
	<?php
	$cache = $this->model->retrieveCacheFile();
	foreach (($cache['zk-pages'] ?? []) as $p) {
		?>
		<a href="<?= PATH ?>zk/<?= entities($p['url']) ?>"><?= entities($p['text']) ?></a>
		<?php
	}
	?>
</div>

<div id="main">