<!DOCTYPE html>

<html>

<head>
    <title>Model Framework Control Panel - <?=NOME_SITO?></title>
    <?php $this->head(); ?>
</head>

<body>
<div id="tools">
    <p><b>Tools</b></p>
    <div id="cmd-make-cache"><a href="#" onclick="cmd('make-cache'); return false">Update cache</a></div>
    <div id="cmd-session-clear"><a href="#" onclick="return false">Empty session</a></div>
    <div><a href="<?=PATH?>zk/get-session" target="_blank">Inspect session</a></div>
</div>

<div id="header">
    <a href="<?=PATH?>zk/modules">Modules</a>
    <!--<a href="<?/*=PATH*/?>zk/elements">Elements</a>
    <a href="<?/*=PATH*/?>zk/error-log">Error Log</a>-->
</div>

<div id="main">