CDash.controller('BuildPropertiesController',
  function BuildPropertiesController($filter, $http, $scope, apiLoader, comparators, modalSvc, multisort) {
    apiLoader.loadPageData($scope, 'api/v1/buildProperties.php');
    $scope.finishSetup = function() {
      if ($scope.cdash.builds.length < 1) {
        return;
      }

      $scope.cdash.selections = [];
      $scope.addSelection();

      // Isolate property keys for auto-completion.
      $scope.cdash.propertykeys = Object.keys($scope.cdash.properties);

      // A different set of comparators for each possible type of build property.
      $scope.comparators = comparators.getComparators();

      $scope.chart_data = [];
      $scope.groups = [];
      $scope.addGroup('All', $scope.cdash.builds);
      $scope.computeChartData();

      // Initial render of chart.
      nv.addGraph(function() {
        $scope.chart = nv.models.multiBarChart()
          .duration(350)
          .rotateLabels(0)      //Angle to rotate x-axis labels.
          .showControls(true)   //Allow user to switch between 'Grouped' and 'Stacked' mode.
          .groupSpacing(0.1)    //Distance between each group of bars.
        ;

        $scope.chart.xAxis.tickFormat(function(d) {
          if ($scope.groups[d] !== undefined) {
            return $scope.groups[d].keyword;
          } else {
            return '';
          }
        });
        $scope.chart.yAxis.tickFormat(d3.format(',f'));
        $scope.chart.yAxis.axisLabel("Number of Builds");


        $scope.chart_selection = d3.select('#chart svg') .datum($scope.chart_data);
        $scope.chart_selection.call($scope.chart);

        $scope.chart.update();
        nv.utils.windowResize($scope.chart.update);
        return $scope.chart;
      });
    };

    $scope.addGroup = function(groupname, builds) {
      var pos = $scope.groups.length;
      var group = {
        keyword: groupname,
        position: pos,
        builds: builds
      };
      $scope.groups.push(group);
    };

    $scope.computeChartData = function() {
      $scope.chart_data = [];

      // Count how many builds are in each group.
      var group_totals = [];
      for (var i = 0; i < $scope.groups.length; ++i) {
        group_totals.push({
          x: $scope.groups[i].position,
          y: $scope.groups[i].builds.length
        });
      }

      // Count how many builds have each type of defect for each group.
      for (var i = 0; i < $scope.cdash.defecttypes.length; ++i) {
        var defect_type = $scope.cdash.defecttypes[i];
        if (!defect_type.selected) {
          continue;
        }
        var defect_values = [];
        for (var j = 0; j < $scope.groups.length; ++j) {
          var group = $scope.groups[j], defective_builds = 0;
          for (var k = 0; k < group.builds.length; ++k) {
            var build = group.builds[k];
            defective_builds += build[defect_type.name] > 0 ? 1 : 0;
          }
          defect_values.push({
            x: group.position,
            y: defective_builds
          });
        }
        $scope.chart_data.push({
          key: defect_type.prettyname,
          values: defect_values
        });
      }

      $scope.chart_data.push({
        key: "Total",
        values: group_totals
      });
    };

    $scope.updateSelection = function() {
      var uri = '//' + location.host + location.pathname + '?project=' + $scope.cdash.projectname_encoded;
      // Include date range from time chart.
      if ($scope.cdash.begin_date == $scope.cdash.end_date) {
        uri += '&date=' + $scope.cdash.begin_date;
      } else {
        uri += '&from=' + $scope.cdash.begin_date + '&to=' + $scope.cdash.end_date;
      }

      // Get selected defects.
      var defect_types = [];
      for (var i = 0; i < $scope.cdash.defecttypes.length; ++i) {
        var defect_type = $scope.cdash.defecttypes[i];
        if (defect_type.selected) {
          defect_types.push(defect_type.name);
        }
      }
      if (defect_types.length > 0) {
        uri += '&defects=' + defect_types.join();
      }

      window.location = uri;
    };

    // Split the "All" / "Remainder" group when adding a new selection.
    $scope.split = function(groupName, filterExpression) {
      if ($scope.groups.length == 1) {
        // First split.  Remove the default 'All' group.
        $scope.groups = [];
      } else {
        // Remove the 'Remainder' group.
        var index = -1;
        for (var i = 0, len = $scope.groups.length; i < len; i++) {
          if ($scope.groups[i].keyword === 'Remainder') {
            index = i;
            break;
          }
        }
        if (index > -1) {
          $scope.groups.splice(index, 1);
        }
      }

      // Find the builds that match our expression.
      var matchingBuilds = $filter('filter')($scope.cdash.builds, filterExpression);
      $scope.addGroup(groupName, matchingBuilds);

      // (Re-)create the 'Remainder' group.
      var remainingBuilds = $filter('filter')($scope.cdash.builds, function(value, index, array) {
        var keep_this_build = true;
        for (var i = 0; i < $scope.groups.length; ++i) {
          var group = $scope.groups[i];
          for (var j = 0; j < group.builds.length; ++j) {
            var build = group.builds[j];
            if (value.id == build.id) {
              keep_this_build = false;
              break;
            }
          }
          if (!keep_this_build) {
            break;
          }
        }
        return keep_this_build;
      });
      $scope.addGroup('Remainder', remainingBuilds);

      $scope.computeChartData();
      $scope.rerenderChart();
    };

    // Remove a group and put its builds back in the "All" / "Remainder" group.
    $scope.unsplit = function(groupName) {
      // Find the group to remove.
      var idx_to_remove = -1;
      for (var i = 0, len = $scope.groups.length; i < len; i++) {
        if ($scope.groups[i].keyword === groupName) {
          idx_to_remove = i;
          break;
        }
      }
      if (idx_to_remove === -1) {
        return;
      }
      var groupToRemove = $scope.groups[idx_to_remove];

      // Find the group named "Remainder".
      var idx_remain = -1;
      for (var i = 0, len = $scope.groups.length; i < len; i++) {
        if ($scope.groups[i].keyword === "Remainder") {
          idx_remain = i;
          break;
        }
      }
      if (idx_remain === -1) {
        return;
      }

      // Find any builds in the group to be removed that do not belong
      // anywhere else.  Add those back into the "Remainder" group.
      var buildids_to_move = [];
      for (var i = 0, len1 = groupToRemove.builds.length; i < len1; i++) {
        var build = groupToRemove.builds[i], move_this_build = true;
        for (var j = 0, len2 = $scope.groups.length; j < len2; j++) {
          var group = $scope.groups[j];
          if (j === idx_to_remove || group.keyword === "Remainder") {
            continue;
          }
          for (var k = 0, len3 = group.builds.length; k < len3; k++) {
            if (group.builds[k].id === build.id) {
              move_this_build = false;
              break;
            }
          }
          if (!move_this_build) {
            break;
          }
        }
        if (move_this_build) {
          buildids_to_move.push(groupToRemove.builds[i].id);
        }
      }

      // Move the appropriate builds back to the "Remainder" group.
      var filterExpression = function(value, index, array) {
        if (buildids_to_move.indexOf(value.id) === -1) {
          return false;
        }
        return true;
      };
      var matchingBuilds = $filter('filter')($scope.cdash.builds, filterExpression);
      $scope.groups[idx_remain].builds = $scope.groups[idx_remain].builds.concat(matchingBuilds);

      // Now we can remove the group.
      $scope.groups.splice(idx_to_remove, 1);

      // Update positions of surviving groups.
      for (var i = 0, len = $scope.groups.length; i < len; i++) {
        $scope.groups[i].position = i;
      }

      if ($scope.groups.length == 1) {
        // If we're back down to one group, rename it from "Remainder" to "All".
        $scope.groups[0].keyword = "All";
      }

      // Redraw the chart.
      $scope.computeChartData();
      $scope.rerenderChart();
    };

    $scope.rerenderChart = function() {
      $scope.chart_selection.datum($scope.chart_data).transition().duration(500).call($scope.chart);
      nv.utils.windowResize($scope.chart.update);
    };


    $scope.addSelection = function() {
      var selection = [
        {
          'name': '',
          'comparator': '',
          'comparators': [],
          'property': '',
          'applied': false
        }
      ];
      $scope.cdash.selections.push(selection);
    };

    $scope.removeSelection = function(selection) {
      var index = $scope.cdash.selections.indexOf(selection);
      if (index > -1) {
        $scope.cdash.selections.splice(index, 1);
        $scope.unsplit(selection.name);
      }
    };

    $scope.applySelection = function(selection) {
      var user_clause = selection.property + " " + selection.comparator.symbol + " " + selection.value;
      var actual_code = 'value.properties.' + user_clause;

      var filterExpression = function(value, index, array) {
        try {
          var b = eval(actual_code);
        } catch (err) {
          selection.error = err.message;
        }
        if (b) {
          return true;
        }
        return false;
      };

      selection.applied = true;
      $scope.split(selection.name, filterExpression);
      $scope.addSelection();
    };

    $scope.updateComparators = function(selection) {
      var idx = $scope.cdash.propertykeys.indexOf(selection.property);
      if (idx != -1) {
        var type = $scope.cdash.properties[selection.property].type;
        selection.comparators = $scope.comparators[type];
      }
    };

});
