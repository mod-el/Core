if (typeof c_id === 'undefined')
	var c_id = '';

var updateQueue = [];

var updatingFileList = {};
var updatingTotalSteps = {};
var updatingStep = {};

var updatingModule = null;

var myRequest = [];

function CreateXmlHttpReq(n, handler, campi_addizionali) { // Funzione che verr� usata da richiestaAjax
	var xmlhttp = false;
	try {
		xmlhttp = new XMLHttpRequest();
	} catch (e) {
		try {
			xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
	}
	xmlhttp.onreadystatechange = function () {
		if (myRequest[n].readyState == 4) {
			if (handler != false) {
				try {
					var r = JSON.parse(myRequest[n].responseText);
				} catch (e) {
					var r = myRequest[n].responseText;
				}

				if (typeof handler === 'object' && handler.nodeType && handler.nodeType == 1) {
					handler.innerHTML = r;
				} else {
					if (typeof handler !== 'function')
						eval('handler = ' + handler + ';');

					if (myRequest[n].status == 200) handler.call(myRequest[n], r, campi_addizionali);
					else handler.call(myRequest[n], false, campi_addizionali);
				}
			}
			delete myRequest[n];
		}
	}
	return xmlhttp;
}

function ajax(handler, indirizzo, parametri_get, parametri_post, campi_addizionali) {
	if (typeof campi_addizionali === 'undefined' || campi_addizionali === '') campi_addizionali = [];
	let r = Math.random();
	n = 0;
	while (myRequest[n]) n++;
	myRequest[n] = CreateXmlHttpReq(n, handler, campi_addizionali);
	myRequest[n].open('POST', indirizzo + '?zkrand=' + r + '&' + parametri_get);
	myRequest[n].setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	if (typeof parametri_post === 'undefined')
		parametri_post = '';

	myRequest[n].send(parametri_post);

	return n;
}

Element.prototype.loading = function () {
	this.innerHTML = '<img src="' + base_path + 'model/Output/files/loading.gif" alt="" class="loading-gif" />';
	return this;
}

function cmd(cmd, post) {
	if (typeof post === 'undefined')
		post = '';
	if (typeof refresh === 'undefined')
		refresh = false;

	let div = document.getElementById('cmd-' + cmd);
	if (!div)
		return false;
	let ex = div.innerHTML;
	div.loading();
	return new Promise(((cmd, post, refresh, div, ex) => {
		return function (resolve) {
			ajax(r => {
				div.innerHTML = ex;
				resolve(r);
			}, absolute_path + 'zk/' + cmd, '', post)
		};
	})(cmd, post, refresh, div, ex));
}

function queueModuleUpdate(name) {
	if (updatingModule) {
		updateQueue.push(name);

		let cont = document.getElementById('module-' + name);
		cont.loading();
	} else {
		updateModule(name);
	}
}

function updateModule(name) {
	if (updatingModule)
		return false;

	let cont = document.getElementById('module-' + name);
	cont.loading();

	let bar = document.getElementById('loading-bar-' + name);
	bar.style.visibility = 'visible';

	updatingStep[name] = 0;
	updatingModule = name;

	ajax(function (r, name) {
		if (typeof r !== 'object') {
			alert('Errore nell\'aggiornamento del modulo ' + name + ":\n" + r);
			refreshModule(name);

			updatingModule = null;
			if (updateQueue.length > 0)
				updateModule(updateQueue.shift());
		} else {
			updatingFileList[name] = r;
			updatingTotalSteps[name] = r.length + 2;
			updatingStep[name]++;
			updateModuleBar(name);
			updateNextFile(name);
		}
	}, absolute_path + 'zk/modules/update', 'module=' + encodeURIComponent(name), 'c_id=' + c_id, name);
}

function updateModuleBar(name) {
	if (typeof updatingTotalSteps[name] === 'undefined' || !updatingTotalSteps[name] || typeof updatingStep[name] === 'undefined')
		return;
	let bar = document.getElementById('loading-bar-' + name);
	bar.firstElementChild.style.width = parseInt(updatingStep[name] / updatingTotalSteps[name] * 100) + '%';
}

function updateNextFile(name) {
	if (typeof !updatingFileList[name] === 'undefined' || !updatingFileList[name])
		return;
	if (updatingFileList[name].length > 0) {
		let file = updatingFileList[name].shift();
		ajax(function (r, name) {
			if (r === 'ok') {
				updatingStep[name]++;
				updateModuleBar(name);
				updateNextFile(name);
			} else {
				alert(r);
				refreshModule(name);

				updatingModule = null;
				if (updateQueue.length > 0)
					updateModule(updateQueue.shift());
			}
		}, absolute_path + 'zk/modules/update-file', 'module=' + encodeURIComponent(name) + '&file=' + encodeURIComponent(file), 'c_id=' + c_id, name);
	} else {
		ajax(function (r, name) {
			if (r === 'ok') {
				updatingStep[name]++;
				updateModuleBar(name);
				refreshModule(name);
				resetModuleLoadingBar(name);

				updatingModule = null;
				if (updateQueue.length > 0) {
					updateModule(updateQueue.shift());
				} else {
					cmd('make-cache').then(() => document.location.reload());
				}
			} else {
				alert(r);
				refreshModule(name);
			}
		}, absolute_path + 'zk/modules/finalize-update', 'module=' + encodeURIComponent(name), 'c_id=' + c_id, name);
	}
}

function resetModuleLoadingBar(name) {
	let bar = document.getElementById('loading-bar-' + name);
	bar.style.visibility = 'hidden';
	bar.style.width = '0%';
}

function refreshModule(name) {
	let cont = document.getElementById('module-' + name);
	cont.loading();
	ajax(function (r, cont) {
		if (typeof r === 'object') {
			switch (r.action) {
				case 'init':
					document.location.href = absolute_path + 'zk/modules/init/' + r.module;
					break;
				default:
					alert('Unknown response');
					break;
			}
		} else {
			cont.innerHTML = r;
		}
	}, absolute_path + 'zk/modules/refresh', 'module=' + encodeURIComponent(name), '', cont);
}

window.addEventListener('load', function () {
	if (updateQueue.length > 0)
		updateModule(updateQueue.shift());
});

function lightbox(html) {
	let lightbox = document.getElementById('lightbox');

	if (!lightbox) {
		let contLightbox = document.createElement('div');
		contLightbox.id = 'lightbox-bg';
		contLightbox.addEventListener('click', closeLightbox);
		document.body.appendChild(contLightbox);

		lightbox = document.createElement('div');
		lightbox.id = 'lightbox';
		lightbox.innerHTML = html;
		document.body.appendChild(lightbox);
	}

	return lightbox;
}

function closeLightbox() {
	let lightbox = document.getElementById('lightbox');
	if (lightbox)
		lightbox.parentNode.removeChild(lightbox);
	let contLightbox = document.getElementById('lightbox-bg');
	if (contLightbox)
		contLightbox.parentNode.removeChild(contLightbox);
}

function lightboxNewModule() {
	let lb = lightbox('');
	lb.loading();
	ajax(lb, absolute_path + 'zk/modules/install', '', '');
}

function selectDownloadableModule(el) {
	let selected = document.getElementById('.list-module.selected');
	if (selected)
		selected.className = 'list-module';
	el.className = 'list-module selected';

	let div = document.getElementById('downloadable-module-details');
	let name = el.dataset.name;
	div.innerHTML = '<div><div class="versione">' + el.dataset.version + '</div><b>' + name + '</b></div><p><i>' + el.dataset.description + '</i></p><div style="text-align: right"><input type="button" value="Scarica e installa" onclick="installModule(\'' + name + '\')" /></div>';
}

function installModule(name) {
	document.getElementById('lightbox').loading();

	ajax(function (r) {
		if (r === 'ok') {
			document.location.reload();
		} else {
			document.getElementById('lightbox').innerHTML = r;
		}
	}, absolute_path + 'zk/modules/install/' + encodeURIComponent(name), '', 'c_id=' + c_id);
}