function RequestError(message, status) {
  this.name = 'RequestError';
  this.message = message;
  this.status = status;
}
RequestError.prototype = Object.create(Error.prototype);
RequestError.prototype.constructor = RequestError;

phresto = (function() {

	var OAuthToken = '';

	var setToken = function() {
		var token = Cookies.get('prsid');
		if (token) {
			OAuthToken = token;
		}
	}

	var makeRequest = function(type, url, parameters) {
		setToken();
		return new Promise(function(resolve, reject) {
			var xmlhttp = new XMLHttpRequest();
			xmlhttp.onreadystatechange = function() {

			    if (this.readyState == 4 && this.status != 200) {
			    	try {
			        	var result = JSON.parse(this.responseText);
			        	reject(new RequestError(result, this.status));
			        } catch(e) {
			        	reject(new RequestError('Request failed with status ' + this.status + '. ' + this.responseText, this.status));
			        }
			    	return;
			    }

			    if (this.readyState == 4 && this.status == 200 && type == 'HEAD') {
			    	return resolve();
			    }

			    if (this.readyState == 4 && this.status == 200) {
			    	try {
			        	var result = JSON.parse(this.responseText);
			        	resolve(result);
			        } catch(e) {
			        	resolve(this.responseText);
			        }
			    }
			};

			var requestBody = null;
			if (parameters) {
				requestBody = JSON.stringify(parameters);
			}

			xmlhttp.open(type, url, true);
			xmlhttp.setRequestHeader('Content-Type', 'application/json');
			if (OAuthToken) {
				xmlhttp.setRequestHeader('Authorization', 'Bearer ' + OAuthToken);
			}
			xmlhttp.send(requestBody);
		});
	}

	var get = function(url) {
		return makeRequest('GET', url);
	}

	var getById = function(name, id) {
		return get(name + '/' + id);
	}

	var exists = function(name, id) {
		return new Promise(function(resolve, reject) {
			var url = name;
			if (id) url += '/' + id;
			makeRequest('HEAD', url)
				.then(function() {
					resolve(true);
				})
				.catch(function(error) {
					if (error.name = 'RequestError' && error.status == 404) {
						return reject(new RequestError(false, 404));
					}

					reject(error);
				});
		});
	}

	var create = function(name, params) {
		return makeRequest('POST', name, params);
	}

	var destroy = function(name, id) {
		return Delete(name + '/' + id);
	}

	var Delete = function(url) {
		return makeRequest('DELETE', url);
	}

	var update = function(name, id, params) {
		return patch( name + '/' + id, params);
	}

	var patch = function(url, params) {
		return makeRequest('PATCH', url, params);
	}

	var upsert = function(name, params) {
		return makeRequest('PUT', name, params);
	}

	return {
		get: get,
		getById: getById,
		head: exists,
		exists: exists,
		post: create,
		destroy: destroy,
		delete: Delete,
		update: update,
		patch: patch,
		upsert: upsert,
		put: upsert,
		setToken: setToken
	}

})();