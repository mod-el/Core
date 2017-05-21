var updateQueue = [];

var updatingFileList = {};
var updatingTotalSteps = {};
var updatingStep = {};

var myRequest = new Array();

function CreateXmlHttpReq(n, handler, campi_addizionali){ // Funzione che verr� usata da richiestaAjax
	var xmlhttp = false;
	try{
		xmlhttp = new XMLHttpRequest();
	}catch(e){
		try{
			xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		}catch(e){
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
	}
	xmlhttp.onreadystatechange = function(){
		if (myRequest[n].readyState==4){
			if(handler!=false){
				try{
					var r = JSON.parse(myRequest[n].responseText);
				}catch(e){
					var r = myRequest[n].responseText;
				}

				if(typeof handler=='object' && handler.nodeType && handler.nodeType == 1){
					handler.innerHTML = r;
				}else{
					if(typeof handler!='function')
						eval('handler = '+handler+';');

					if(myRequest[n].status==200) handler.call(myRequest[n], r, campi_addizionali);
					else handler.call(myRequest[n], false, campi_addizionali);
				}
			}
			delete myRequest[n];
		}
	}
	return xmlhttp;
}

function ajax(handler, indirizzo, parametri_get, parametri_post, campi_addizionali){
	if(typeof campi_addizionali=='undefined' || campi_addizionali==='') campi_addizionali = [];
	var r = Math.random();
	n = 0; while(myRequest[n]) n++;
	myRequest[n] = CreateXmlHttpReq(n, handler, campi_addizionali);
	myRequest[n].open('POST', indirizzo+'?zkrand='+r+'&'+parametri_get);
	myRequest[n].setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	if(typeof parametri_post=='undefined')
		parametri_post = '';

	myRequest[n].send(parametri_post);

	return n;
}

function loading(cont){
	cont.innerHTML = '<img src="'+base_path+'model/Output/files/loading.gif" alt="" />';
}

function cmd(cmd, post){
	if(typeof post=='undefined') post = '';
	var div = document.getElementById('cmd-'+cmd);
	if(!div)
		return false;
	var ex = div.innerHTML;
	loading(div);
	ajax(function(r, dati){
		alert(r);
		dati.div.innerHTML = dati.html;
	}, absolute_path+'zk/'+cmd, '', post, {'div':div, 'html':ex});
}

function updateModule(name){
	var cont = document.getElementById('module-'+name);
	loading(cont);

	var bar = document.getElementById('loading-bar-'+name);
	bar.style.visibility = 'visible';

	updatingStep[name] = 0;

	ajax(function(r, name){
		if(typeof r!='object'){
			alert('Errore nell\'aggiornamento del modulo '+name+":\n"+r);
			refreshModule(name);

			if(updateQueue.length>0)
				updateModule(updateQueue.shift());
		}else{
			updatingFileList[name] = r;
			updatingTotalSteps[name] = r.length+2;
			updatingStep[name]++;
			updateModuleBar(name);
			updateNextFile(name);
		}
	}, absolute_path+'zk/modules/update', 'module='+encodeURIComponent(name), 'c_id='+c_id, name);
}

function updateModuleBar(name){
	if(typeof updatingTotalSteps[name]=='undefined' || !updatingTotalSteps[name] || typeof updatingStep[name]=='undefined')
		return;
	var bar = document.getElementById('loading-bar-'+name);
	bar.firstElementChild.style.width = parseInt(updatingStep[name]/updatingTotalSteps[name]*100)+'%';
}

function updateNextFile(name){
	if(typeof !updatingFileList[name]=='undefined' || !updatingFileList[name])
		return;
	if(updatingFileList[name].length>0){
		var file = updatingFileList[name].shift();
		ajax(function(r, name){
			if(r=='ok'){
				updatingStep[name]++;
				updateModuleBar(name);
				updateNextFile(name);
			}else{
				alert(r);
				refreshModule(name);

				if(updateQueue.length>0)
					updateModule(updateQueue.shift());
			}
		}, absolute_path+'zk/modules/update-file', 'module='+encodeURIComponent(name)+'&file='+encodeURIComponent(file), 'c_id='+c_id, name);
	}else{
		ajax(function(r, name){
			if(r=='ok'){
				updatingStep[name]++;
				updateModuleBar(name);
				refreshModule(name);
				resetModuleLoadingBar(name);

				if(updateQueue.length>0)
					updateModule(updateQueue.shift());
			}else{
				alert(r);
				refreshModule(name);
			}
		}, absolute_path+'zk/modules/finalize-update', 'module='+encodeURIComponent(name), 'c_id='+c_id, name);
	}
}

function resetModuleLoadingBar(name){
	var bar = document.getElementById('loading-bar-'+name);
	bar.style.visibility = 'hidden';
	bar.style.width = '0%';
}

function refreshModule(name){
	var cont = document.getElementById('module-'+name);
	loading(cont);
	ajax(cont, absolute_path+'zk/modules/refresh', 'module='+encodeURIComponent(name), '', cont);
}

window.addEventListener('load', function(){
	if(updateQueue.length>0)
		updateModule(updateQueue.shift());
});