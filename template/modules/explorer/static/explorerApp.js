angular.module('app', ['mm.foundation']).controller(
  'explorerAppController', 
  [ '$scope',
    '$document',
    '$rootScope',
    '$timeout',
    function($scope, $document, $rootScope, $timeout) {
      $scope.routes = [];
      $scope.loading = false;

      $scope.makeRequest = function(controller, method) {
        var url = controller.endpoint;
        if (controller.endpointRelated) {
          url = controller.endpointOrg + '/' + method.url_id + '/' + controller.endpointRelated;
        }
        for(var idx in method.url) {
          url += '/' + method.url[idx];
        }
        url += '?' + method.query
        if (!phresto[method.name]) return;
        $scope.loading = true;
        phresto[method.name](url, method.body)
          .then(function(response) {
            method.response = response;
            method.responseStatus = 200;
            $scope.loading = false;
            $scope.$apply();
          })
          .catch(function(err) {
            method.response = err.message;
            method.responseStatus = err.status;
            $scope.loading = false;
            $scope.$apply();
          });
      }

      phresto.get('explorer/routes')
        .then(function(routes) {
          for (var route in routes) {
            if (routes[route].endpoint.search('/_id_/') > -1) {
              var parts = routes[route].endpoint.split('/_id_/');
              routes[route].endpointOrg = parts[0];
              routes[route].endpointRelated = parts[1];
            } else {
              routes[route].endpointOrg = routes[route].endpoint;
            }
          }
          $scope.$apply(function() {
            $scope.routes = routes;
            console.log(routes);
          });
        })
        .catch(function(err) {
          $rootScope.$emit('addmessage', {type: 'alert', message: err.message});
        });

      angular.element(document).ready(function () {
        $timeout(function() {
          $document.foundation();
        }, 500);
      });
    }
  ]);

angular.module('app').filter('prettyJSON', function () {
    function prettyPrintJson(json) {

      if (typeof json != 'object') return json;

      return JSON.stringify(json, null, '  ');
    }
    return prettyPrintJson;
});

angular.module('app').filter('idfy', function () {
    function idfy(text) {

      return text.replace(/\/+/g, '_');
    }
    return idfy;
});