angular.module('app', ['mm.foundation']).controller(
  'adminAppController', 
  [ '$scope',
    '$document',
    '$rootScope',
    '$timeout',
    function($scope, $document, $rootScope, $timeout) {
      $scope.permissions = [];
      $scope.profiles = [];
      $scope.profileId = 0;
      $scope.loading = true;

      $scope.loadPermissions = function() {
        $scope.loading = true;
        phresto.get('admin/permissions/' + $scope.profileId)
          .then(function(permissions) {
            $scope.permissions = permissions;
            $scope.loading = false;
            $scope.$apply();
          })
          .catch(function(err) {
            $rootScope.$emit('addmessage', {type: 'alert', message: err.message});
            $scope.loading = false;
            $scope.$apply();
          });
      }

      $scope.save = function(perm) {
        $scope.loading = true;
        phresto.upsert('permission', perm )
          .then(function(permission) {
            console.log(permission);
            if (!perm.id) perm.id = permission.id;
            $scope.loading = false;
            $scope.$apply();
          })
          .catch(function(err) {
            $rootScope.$emit('addmessage', {type: 'alert', message: err.message});
            $scope.loading = false;
            $scope.$apply();
          });
      }

      phresto.get('profile')
        .then(function(profiles) {
          $scope.profiles = profiles;
          $scope.loading = false;
        })
        .catch(function(err) {
          $rootScope.$emit('addmessage', {type: 'alert', message: err.message});
          $scope.loading = false;
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