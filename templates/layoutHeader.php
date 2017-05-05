<!DOCTYPE html>

<html>

<head>
    <title>Model Framework Control Panel - <?=NOME_SITO?></title>

    <style type="text/css">
        html, body{
            margin: 0;
            font-family: Verdana;
            font-size: 14px;
            min-height: 100%;
        }

        body{
            background: #F9F9F9 url('<?=PATH?>model/Core/templates/logo.png') center no-repeat;
        }

        #tools{
            position: absolute;
            z-index: 10;
            top: 0px;
            right: 0px;
            padding: 20px;
            background: #2693FF;
            border-bottom-left-radius: 10px;
        }

        #tools a:link, #tools a:visited{
            color: #FFF;
        }

        a:link, a:visited{
            text-decoration: none;
            color: #2693FF;
        }

        a:hover{
            text-shadow: 0px 0px 2px #DDD;
        }

        input:not([type=checkbox]):not([type=radio]), select{
            height: 30px;
            padding: 0 7px;
        }

        #header{
            padding: 5px 10px;
            background: rgba(255, 255, 255, 0.8);
            border-bottom: solid #EEE 1px;
        }

        #header a{
            padding: 10px 30px;
            border-right: solid #FFF 1px;
            display: inline-block;
        }

        #main{
            width: 1160px;
            margin: auto;
            margin-top: 15px;
            background: rgba(255, 255, 255, 0.95);
            border: solid #CCC 1px;
            border-radius: 15px;
            padding: 20px;
            overflow: hidden;
        }

        .boxino{
            border: solid #AAA 1px;
            padding: 15px;
            margin-top: 15px;
        }
    </style>
</head>

<body>
<div id="tools">
    <p><b>Tools</b></p>
    <div id="comando-generate-models"><a href="#" onclick="return false">Update cache</a></div>
    <div id="comando-session-clear"><a href="#" onclick="return false">Empty session</a></div>
    <div><a href="<?=PATH?>zk/get-session" target="_blank">Inspect session</a></div>
</div>

<div id="header">
    <a href="<?=PATH?>zk/modules">Modules</a>
    <!--<a href="<?/*=PATH*/?>zk/elements">Elements</a>
    <a href="<?/*=PATH*/?>zk/error-log">Error Log</a>-->
</div>

<div id="main">