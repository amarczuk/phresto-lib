angular.module('app').directive('message', [
  function () {
    return {
      replace: false,
      restrict: 'E',
      scope: {},
      controller: function ($scope, $rootScope, $timeout) {

        $scope.messages = [];

        $scope.removeMessage = function(message) {
          var idx = $scope.messages.indexOf(message);
          if (idx < 0) return;
          $scope.messages.splice(idx, 1);
        }

        var off = $rootScope.$on('addmessage', function(e, message) {
          $scope.messages.push(message);
          $timeout(function() {
            $scope.removeMessage(message);
          }, 20000)
        });

        $scope.$on('destroy', off);
        
      },
      template: "\
  <div id=\"messageContainer\">\
    <div ng-click=\"removeMessage(message)\" \
       data-alert \
       class=\"callout alert-box {{message.type}}\"\
       ng-repeat=\"message in messages\">\
       {{message.message}}\
    </div>\
  </div>"
    };
  }
]);
