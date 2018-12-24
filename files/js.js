if (typeof c_id === 'undefined')
	var c_id = '';

var updateQueue = [];
var selectedModules = [];
var updatingModules = [];
var updatingFileList = [];
var updatingTotalSteps = null;

/**************************************************************************************/

/**************************************************************************************/

function ajax(url, get, post, options) {
	options = array_merge({
		'additional': [],
		'bind': true,
		'fullResponse': false,
		'onprogress': null,
		'method': null,
		'headers': {}
	}, options);

	if (typeof get === 'undefined')
		get = '';
	if (typeof post === 'undefined')
		post = '';

	if (typeof get === 'object')
		get = queryStringFromObject(get);
	if (typeof post === 'object')
		post = queryStringFromObject(post);

	if (window.fetch && options['onprogress'] === null) {
		var fetchOptions = {
			'credentials': 'include'
		};
		if (post) {
			if (options['method'] === null)
				options['method'] = 'POST';

			fetchOptions['body'] = post;
			options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
		}

		if (options['method'])
			fetchOptions['method'] = options['method'];
		fetchOptions['headers'] = options['headers'];

		return fetch(url + '?' + get, fetchOptions).then(function (response) {
			if (options['fullResponse'])
				return response;
			else
				return response.text();
		}).then(function (text) {
			if (!options['fullResponse'])
				text = handleAjaxResponse(text);

			return text;
		});
	} else {
		return new Promise(function (resolve) {
			oldAjax(resolve, url, get, post, options['additional'], options['bind'], options['onprogress']);
		});
	}
}

function queryStringFromObject(obj) {
	var string = [];
	for (var k in obj) {
		string.push(k + '=' + encodeURIComponent(obj[k]));
	}
	return string.join('&');
}

Element.prototype.ajax = function (url, get, post, options) {
	return ajax(url, get, post, options).then((function (el) {
		return function (r) {
			el.jsFill(r);
			return r;
		};
	})(this)).then(function (r) {
		return r;
	});
};

function handleAjaxResponse(text) {
	try {
		var r = JSON.parse(text);

		if (typeof r.ZKDEBUG != 'undefined') {
			infoDebugJSON.push(r.ZKDEBUG);
		}
		if (typeof r.ZKBINDINGS != 'undefined') {
			for (var i in r.ZKBINDINGS) {
				if (!r.ZKBINDINGS.hasOwnProperty(i)) continue;
				var b = r.ZKBINDINGS[i];
				switch (b['type']) {
					case 'element':
						if (typeof b['field'] != 'undefined') {
							var elements = document.querySelectorAll('[data-bind-element="' + b['element'] + '-' + b['id'] + '"][data-bind-field="' + b['field'] + '"]');
						} else if (typeof b['method'] != 'undefined') {
							var elements = document.querySelectorAll('[data-bind-element="' + b['element'] + '-' + b['id'] + '"][data-bind-method="' + b['method'] + '"]');
						}
						break;
					case 'table':
						var elements = document.querySelectorAll('[data-bind-table="' + b['table'] + '-' + b['id'] + '"][data-bind-field="' + b['field'] + '"]');
						break;
				}
				for (var il in elements) {
					if (!elements.hasOwnProperty(il)) continue;
					var elemType = elements[il].nodeName.toLowerCase();
					if (elemType == 'input' || elemType == 'textarea' || elemType == 'select' || elemType == 'radio') {
						elements[il].setValue(b['v'], false);
					} else {
						elements[il].innerHTML = b['v'];
					}
				}
			}
		}
		if (typeof r.ZKDATA != 'undefined') {
			r = r.ZKDATA;
		}
	} catch (e) {
		var r = text;
	}

	return r;
}

Element.prototype.removeClass = function (name) {
	var classi = this.className.split(' ');
	var nuove = [];
	for (var i in classi) {
		if (classi[i] != name)
			nuove.push(classi[i]);
	}
	this.className = nuove.join(' ');
}

Element.prototype.addClass = function (name) {
	var classi = this.className.split(' ');
	for (var i in classi) {
		if (classi[i] == name) return;
	}
	this.className = this.className + ' ' + name;
}

Element.prototype.hasClass = function (name) {
	return new RegExp('(\\s|^)' + name + '(\\s|$)').test(this.className);
}

Element.prototype.loading = function () {
	this.innerHTML = '<img src="' + base_path + 'model/Output/files/loading.gif" alt="Loading..." class="loading-gif" />';
	return this;
}

function splitScripts(text) {
	var scripts = '';
	var cleaned = text.toString().replace(/<script[^>]*>([\s\S]*?)<\/script>/gi, function () {
		scripts += arguments[1] + '\n';
		return '';
	});
	return {'html': cleaned, 'js': scripts};
}

Element.prototype.jsFill = function (text) {
	var split = splitScripts(text);
	this.innerHTML = split.html;
	eval(split.js);
}

function array_merge(obj1, obj2) {
	var obj3 = {};
	for (var attrname in obj1) {
		obj3[attrname] = obj1[attrname];
	}
	for (var attrname in obj2) {
		obj3[attrname] = obj2[attrname];
	}
	return obj3;
}

/**************************************************************************************/

/**************************************************************************************/

window.addEventListener('load', function () {
	if (updateQueue.length > 0) {
		updateQueue.forEach(name => selectModule(name));
		updateSelectedModules();
	}
});

function cmd(cmd, post) {
	if (typeof post === 'undefined')
		post = '';

	let div = document.getElementById('cmd-' + cmd);
	if (!div)
		return false;
	let ex = div.innerHTML;
	div.loading();
	return ajax(absolute_path + 'zk/' + cmd, {}, post).then(r => {
		div.innerHTML = ex;
		return r;
	});
}

function selectModule(name) {
	let index = selectedModules.indexOf(name);
	if (index === -1)
		selectedModules.push(name);

	refreshSelectedModules();
}

function toggleModuleSelection(name) {
	let index = selectedModules.indexOf(name);
	if (index === -1) {
		selectedModules.push(name);
	} else {
		selectedModules.splice(index, 1);
	}

	refreshSelectedModules();
}

function refreshSelectedModules() {
	document.querySelectorAll('[data-module]').forEach(module => {
		let name = module.getAttribute('data-module');
		if (selectedModules.indexOf(name) === -1) {
			module.removeClass('selected');
		} else {
			module.addClass('selected');
		}
	});
}

function selectAllModules(onlyUpdate) {
	if (typeof onlyUpdate === 'undefined')
		onlyUpdate = false;

	if (onlyUpdate)
		deselectAllModules();

	document.querySelectorAll('[data-module]').forEach(module => {
		let name = module.getAttribute('data-module');
		let index = selectedModules.indexOf(name);
		if (index === -1) {
			if (!onlyUpdate || module.getAttribute('data-update'))
				selectedModules.push(name);
		}
	});

	refreshSelectedModules();
}

function deselectAllModules() {
	selectedModules = [];
	refreshSelectedModules();
}

function updateAllModules() {
	selectAllModules(true);
	updateSelectedModules();
}

function updateSelectedModules() {
	let updateList = [], corrupted = false;
	selectedModules.forEach(module => {
		let priority = 999;
		let div = document.querySelector('[data-module="' + module + '"]');
		if (div.getAttribute('data-corrupted'))
			corrupted = true;

		if (div) {
			priority = parseInt(div.getAttribute('data-priority'));
			if (isNaN(priority))
				priority = 999;
		}

		updateList.push({
			'name': module,
			'priority': priority
		});
	});

	if (corrupted && !confirm('Some modules are marked as edited. Are you sure you want to overwrite them as well?'))
		return false;

	updateList.sort((a, b) => {
		if (a.priority < b.priority)
			return -1;
		if (a.priority > b.priority)
			return 1;
		return 0;
	});

	document.getElementById('update-info').style.display = 'block';
	document.getElementById('update-action').innerHTML = 'Downloading files list...';

	updatingModules = updateList.map(module => module.name);

	ajax(absolute_path + 'zk/modules/files-list', {
		'modules': updatingModules.join(',')
	}).then(list => {
		if (typeof list !== 'object')
			throw list;

		updatingFileList = list;
		updatingTotalSteps = list.length + 1;
		updateNextFile();
	}).catch(err => {
		alert(err);
	});
}

function updateNextFile() {
	refreshLoadingBar();

	if (updatingFileList.length > 0) {
		let file = updatingFileList.shift();

		document.getElementById('update-action').innerHTML = 'Downloading ' + file;

		ajax(absolute_path + 'zk/modules/update-file', {}, {
			'file': file,
			'c_id': c_id
		}).then(r => {
			if (r === 'ok') {
				updateNextFile();
			} else {
				alert(r);
				document.location.reload();
			}
		});
	} else {
		document.getElementById('update-action').innerHTML = 'Finalizing...';

		ajax(absolute_path + 'zk/modules/finalize-update', {}, {
			'modules': updatingModules.join(','),
			'c_id': c_id
		}).then(r => {
			if (r === 'ok') {
				cmd('make-cache').then(() => document.location.reload());
			} else {
				alert(r);
				document.location.reload();
			}
		});
	}
}

function refreshLoadingBar() {
	let current = updatingTotalSteps - updatingFileList.length;
	let percentage = Math.round(current / updatingTotalSteps * 100);
	document.getElementById('update-loading-bar').firstElementChild.style.width = percentage + '%';
}

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
	lb.loading().ajax(absolute_path + 'zk/modules/install');
}

function selectDownloadableModule(el) {
	if (el.hasClass('selected')) {
		el.removeClass('selected');
	} else {
		el.addClass('selected');
	}

	let div = document.getElementById('downloadable-module-details');
	let name = el.dataset.name;
	div.innerHTML = '<div><div class="module-version">' + el.dataset.version + '</div><b>' + name + '</b></div><p><i>' + el.dataset.description + '</i></p>';
}

function installSelectedModules() {
	let el = document.querySelector('.list-module.selected');
	if (!el)
		return;

	alert('Funzione in costruzione, momentaneamente verrà installato solo il primo selezionato');
	let name = el.dataset.name;

	document.getElementById('lightbox').loading();

	ajax(absolute_path + 'zk/modules/install/' + encodeURIComponent(name), '', 'c_id=' + c_id).then(r => {
		if (r === 'ok') {
			document.location.reload();
		} else {
			document.getElementById('lightbox').innerHTML = r;
		}
	});
}

function makeNewFile(module, type) {
	return lightbox('').loading().ajax(absolute_path + 'zk/local-modules/' + module + '/make/' + type);
}