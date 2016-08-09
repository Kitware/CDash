CDash.controller('CreateProjectController',
  function CreateProjectController($scope, $rootScope, $http, $location, $timeout, renderTimer, Upload) {

    $scope.loading = true;
    $http({
      url: 'api/v1/createProject.php',
      method: 'GET',
      params: $rootScope.queryString
    }).success(function(cdash) {
      renderTimer.initialRender($scope, cdash);
      // Set title in root scope so the head controller can see it.
      $rootScope['title'] = cdash.title;
    }).finally(function() {
      $scope.loading = false;
      $scope.cdash.changesmade = false;
      var activeTab = 1;
      var disableTabs = false;

      // Go to a specific tab if one was specified.
      var hash = $location.hash();
      if (hash.startsWith('tab')) {
        var whichTab = hash.match(/\d+/);
        if (whichTab) {
          activeTab = whichTab;
        }
      }

      if ($scope.cdash.edit == 0) {
        disableTabs = true;
        $scope.cdash.submitdisabled = true;
      }

      $scope.cdash.tabs = [
        {
          'disabled': false,
          'active': activeTab == 1
        },
        {
          'disabled': disableTabs,
          'active': activeTab == 2
        },
        {
          'disabled': disableTabs,
          'active': activeTab == 3
        },
        {
          'disabled': disableTabs,
          'active': activeTab == 4
        },
        {
          'disabled': disableTabs,
          'active': activeTab == 5
        },
        {
          'disabled': disableTabs,
          'active': activeTab == 6
        }
      ];

      if ($scope.cdash.edit == 1) {
        $scope.cdash.tabs.push({
          'disabled': false,
          'active': activeTab == 7
        });
        $scope.cdash.tabs.push({
          'disabled': false,
          'active': activeTab == 8
        });
      }
    });

    $scope.showHelp = function(id_div) {
      $(".tab_help").html($("#"+id_div).html()).show();
    };

    $scope.clearHelp = function() {
      $('.tab_help').html('');
    };

    $scope.nextTab = function(idx) {
      if(idx == 0 && ($scope.cdash.project.Name === undefined || $scope.cdash.project.Name == '')) {
        alert('please specify a name for the project.');
        return false;
      }
      $scope.gotoTab(idx + 1);
      if(idx == 4) {
        $scope.cdash.submitdisabled = false;
      }
    };

    $scope.previousTab = function(idx) {
      $scope.gotoTab(idx - 1);
    };

    $scope.gotoTab = function(idx) {
      $scope.clearHelp();
      for (var i = 0; i < $scope.cdash.tabs.length; i++) {
        $scope.cdash.tabs[i].active = false;
      }
      $scope.cdash.tabs[idx].disabled = false;
      $scope.cdash.tabs[idx].active = true;
      $scope.setTab(idx);
    };

    $scope.setTab = function(idx) {
      $location.hash("tab" + idx);
    };

    $scope.createProject = function() {
      var parameters = {
        Submit: true,
        project: $scope.cdash.project
      };
      $http.post('api/v1/project.php', parameters)
      .success(function(cdash) {
        if (cdash.projectcreated && cdash.project) {
          $scope.cdash.projectcreated = cdash.projectcreated;
          $scope.cdash.project = cdash.project;
          $scope.setLogo();
        }
      }).error(function(cdash) {
        if (cdash.error) {
          $scope.cdash.error = cdash.error;
        }
      });
    };

    $scope.updateProject = function() {
      var parameters = {
        Update: true,
        project: $scope.cdash.project
      };
      $http.post('api/v1/project.php', parameters)
      .success(function(cdash) {
        if (cdash.projectupdated && cdash.project) {
          $scope.cdash.changesmade = false;
          $scope.cdash.projectupdated = true;
          $scope.cdash.project = cdash.project;
          $scope.setLogo();
          $scope.startFade = false;
          $timeout(function() {
            $scope.cdash.projectupdated = false;
            $scope.startFade = true;
          }, 2000);
        }
      }).error(function(cdash) {
        if (cdash.error) {
          $scope.cdash.error = cdash.error;
        }
      });
    };

    $scope.setLogo = function() {
      if ($scope.cdash.logoFile) {
        Upload.upload({
          url: 'api/v1/project.php',
          data: {
            project: $scope.cdash.project,
            logo: $scope.cdash.logoFile
          }
        }).then(function (resp) {
          if (resp.data.imageid > 0) {
            $scope.cdash.logoFile = null;
            // Use a decache to force the logo to refresh even if the imageid didn't change.
            var imageid = resp.data.imageid + "&decache=" + new Date().getTime();
            $scope.cdash.project.ImageId = imageid;
            $scope.cdash.logoid = imageid;
          }
        });
      }
    };

    $scope.deleteProject = function() {
      if (window.confirm("Are you sure you want to delete this project?")) {
        var parameters = { project: $scope.cdash.project };
        $http({
          url: 'api/v1/project.php',
          method: 'DELETE',
          params: parameters
        }).success(function() {
          // Redirect to user.php
          window.location = 'user.php';
        });
      }
    };

    $scope.changeViewerType = function() {
      if (!$scope.cdash.selectedViewer) {
        return;
      }
      $scope.cdash.project.CvsViewerType = $scope.cdash.selectedViewer.value;
      if (!$scope.cdash.project.CvsUrl) {
        return;
      }
      var parameters = {
        method: 'repository',
        task: 'exampleurl',
        url: $scope.cdash.project.CvsUrl,
        type: $scope.cdash.selectedViewer.value
      };
      $http({
        url: 'api/v1/index_old.php',
        method: 'GET',
        params: parameters
      }).success(function(data) {
        $scope.cdash.repositoryurlexample = data;
      });
    };

    $scope.addRepository = function() {
      // Add another repository form.
      $scope.cdash.project.repositories.push({
        url: '',
        branch: '',
        username: '',
        password: ''
      });
    };

    $scope.addBlockedBuild = function(blockedbuild) {
      var parameters = {
        project: $scope.cdash.project,
        AddBlockedBuild: blockedbuild
      };
      $http.post('api/v1/project.php', parameters)
      .success(function(cdash) {
        if (cdash.blockedid > 0) {
          blockedbuild.id = cdash.blockedid;
          $scope.cdash.project.blockedbuilds.push(blockedbuild);
          $scope.cdash.buildblocked = true;
          $scope.startFade = false;
          $timeout(function() {
            $scope.cdash.buildblocked = false;
            $scope.startFade = true;
            }, 2000);
        }
      }).error(function(cdash) {
        if (cdash.error) {
          $scope.cdash.error = cdash.error;
        }
      });
    };

    $scope.removeBlockedBuild = function(blockedbuild) {
      var parameters = {
        project: $scope.cdash.project,
        RemoveBlockedBuild: blockedbuild
      };
      $http.post('api/v1/project.php', parameters)
      .success(function(cdash) {
        // Find and remove this build.
        var index = -1;
        for(var i = 0, len = $scope.cdash.project.blockedbuilds.length; i < len; i++) {
          if ($scope.cdash.project.blockedbuilds[i].id === blockedbuild.id) {
            index = i;
            break;
          }
        }
        if (index > -1) {
          $scope.cdash.project.blockedbuilds.splice(index, 1);
        }
      }).error(function(cdash) {
        if (cdash.error) {
          $scope.cdash.error = cdash.error;
        }
      });
    };


});
