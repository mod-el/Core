var myRequest = new Array(); function CreateXmlHttpReq(n, handler, campi_addizionali){ // Funzione che verr� usata da richiestaAjax
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
					jsFill(r, handler);
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

function cmd(cmd, post){
	if(typeof post=='undefined') post = '';
	var div = document.getElementById('cmd-'+cmd);
	if(!div)
		return false;
	var ex = div.innerHTML;
	div.innerHTML = '<img src="'+base_path+'img/loading.gif" alt="" />';
	ajax(function(r, dati){
		alert(r);
		dati.div.innerHTML = dati.html;
	}, absolute_path+'zk/'+cmd, '', post, {'div':div, 'html':ex});
}