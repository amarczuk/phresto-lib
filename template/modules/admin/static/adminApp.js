angular.module('app', []).controller(
    'adminAppController',
    ['$scope',
        '$document',
        '$rootScope',
        '$timeout',
        function ($scope, $document, $rootScope, $timeout) {
            $scope.permissions = [];
            $scope.models = [];
            $scope.records = [];
            $scope.record = {};
            $scope.profiles = [];
            $scope.profileId = 0;
            $scope.loading = true;
            $scope.currentModel = {};
            $scope.selectedModel = '';
            $scope.page = 1;
            $scope.limit = 50;
            $scope.count = 0;
            $scope.records = [];
            $scope.currentRecord;

            $scope.getInputType = function (param) {
                switch (param.type) {
                    case 'int':
                    case 'double':
                        return 'number';
                    case 'DateTime':
                        return 'datetime';
                    case 'boolean':
                        return 'checkbox';
                    default:
                        return 'text';
                }
            };

            $scope.edit = function (record) {
                $scope.currentRecord = angular.copy(record);
            };

            $scope.remove = function (record) {
                if (confirm('Are you sure?')) {
                    makeRequest($scope.currentModel, 'delete', record)
                        .then(function (record) {
                            $rootScope.$emit('addmessage', { type: 'success', message: 'Record removed' });
                            $scope.changePage($scope.page);
                        })
                        .catch(function (err) {
                            $rootScope.$emit('addmessage', { type: 'alert', message: err.message });
                        });
                }
            };

            $scope.saveRecord = function () {
                if (!$scope.currentRecord) {
                    return;
                }

                makeRequest($scope.currentModel, 'put', $scope.currentRecord)
                    .then(function (record) {
                        $rootScope.$emit('addmessage', { type: 'success', message: 'Record saved with id: ' + record.id });
                        $scope.changePage($scope.page);
                        $('#editModal').foundation('close');
                        $scope.currentRecord = null;
                    })
                    .catch(function (err) {
                        $rootScope.$emit('addmessage', { type: 'alert', message: err.message });
                    });
            };

            $scope.changeModel = function () {
                var model = $scope.models.find(function (v) {
                    return v.endpoint === $scope.selectedModel;
                });
                $scope.currentModel = angular.copy(model);
                $scope.page = 1;
                $scope.records = [];
                makeRequest(model, 'head')
                    .then(function (count) {
                        $scope.count = count;
                        setPages(count);
                        return makeRequest(model, 'get');
                    })
                    .then(function (records) {
                        $timeout(function () {
                            $scope.records = records;
                        }, 0);
                    })
                    .catch(function (err) {
                        $rootScope.$emit('addmessage', { type: 'alert', message: err.message });
                    });
            };

            var setPages = function (count) {
                var pages = [1];
                count -= $scope.limit;
                while (count > 0) {
                    pages.push(pages[pages.length - 1] + 1);
                    count -= $scope.limit;
                }
                $scope.pages = pages;
            };

            $scope.changePage = function (page) {
                $scope.page = page;
                makeRequest($scope.currentModel, 'head')
                    .then(function (count) {
                        $scope.count = count;
                        setPages(count);
                        return makeRequest($scope.currentModel, 'get');
                    })
                    .then(function (records) {
                        $timeout(function () {
                            $scope.records = records;
                        }, 0);
                    })
                    .catch(function (err) {
                        $rootScope.$emit('addmessage', { type: 'alert', message: err.message });
                    });
            };

            var makeRequest = function (controller, method, body) {
                return new Promise(function (resolve, reject) {
                    if (!phresto[method]) reject(new Error('Wrong method'));

                    var url = controller.endpoint;
                    if (method == 'get') {
                        var offset = ($scope.page - 1) * $scope.limit;
                        url += '?offset=' + offset + '&limit=' + $scope.limit;
                    }

                    if (method == 'delete') {
                        url += '/' + body.id;
                    }

                    $scope.loading = true;
                    phresto[method](url, body)
                        .then(function (response) {
                            $scope.loading = false;
                            $scope.$apply();
                            resolve(response);
                        })
                        .catch(function (err) {
                            $scope.loading = false;
                            $scope.$apply();
                            reject(err);
                        });
                });
            };

            $scope.loadPermissions = function () {
                $scope.loading = true;
                phresto.get('admin/permissions/' + $scope.profileId)
                    .then(function (permissions) {
                        $scope.permissions = permissions;
                        $scope.loading = false;
                        $scope.$apply();
                    })
                    .catch(function (err) {
                        $rootScope.$emit('addmessage', { type: 'alert', message: err.message });
                        $scope.loading = false;
                        $scope.$apply();
                    });
            }

            $scope.save = function (perm) {
                console.log(perm)
                $scope.loading = true;
                var toSave = Object.assign({}, perm);
                toSave.allow = !toSave.allow;
                phresto.upsert('permission', toSave)
                    .then(function (permission) {
                        if (!perm.id) perm.id = permission.id;
                        $scope.loading = false;
                        $scope.$apply();
                    })
                    .catch(function (err) {
                        $rootScope.$emit('addmessage', { type: 'alert', message: err.message });
                        $scope.loading = false;
                        $scope.$apply();
                    });
            }

            phresto.get('profile')
                .then(function (profiles) {
                    $scope.profiles = profiles;
                    return phresto.get('admin/models');
                })
                .then(function (models) {
                    models.forEach(function (model) {
                        model.methods.forEach(function (method) {
                            if (method.name == 'post') {
                                model.params = method.params;
                            }
                        });
                    });
                    $scope.models = models;
                    console.log(models);
                    $scope.loading = false;
                })
                .catch(function (err) {
                    $rootScope.$emit('addmessage', { type: 'alert', message: err.message });
                    $scope.loading = false;
                });

            angular.element(document).ready(function () {
                $timeout(function () {
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
