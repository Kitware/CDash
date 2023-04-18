CDash.controller('BuildPropertiesController',
  ($filter, $http, $scope, apiLoader, comparators, modalSvc, multisort) => {
    apiLoader.loadPageData($scope, 'api/v1/buildProperties.php');
    $scope.finishSetup = function() {
      if ($scope.cdash.builds.length < 1) {
        return;
      }

      $scope.cdash.showDefects = false;
      $scope.cdash.defectsLoaded = false;

      $scope.cdash.selections = [];
      $scope.addSelection();

      // Isolate property keys for auto-completion.
      $scope.cdash.propertykeys = Object.keys($scope.cdash.properties);

      // A different set of comparators for each possible type of build property.
      $scope.comparators = comparators.getComparators();

      // Pagination settings for defects table.
      $scope.pagination = [];
      $scope.pagination.filteredTests = [];
      $scope.pagination.currentPage = 1;
      $scope.pagination.maxSize = 5;
      const num_per_page_cookie = $.cookie('buildProperties_num_per_page');
      if (num_per_page_cookie) {
        $scope.pagination.numPerPage = parseInt(num_per_page_cookie);
      }
      else {
        $scope.pagination.numPerPage = 10;
      }

      // Sorting for defects table.
      const sort_cookie_value = $.cookie('cdash_buildProperties_sort');
      if (sort_cookie_value) {
        $scope.orderByFields = sort_cookie_value.split(',');
      }
      else {
        $scope.orderByFields = ['-builds.length'];
      }

      $scope.chart_data = [];
      $scope.groups = [];
      $scope.addGroup('All', $scope.cdash.builds);
      $scope.computeChartData();

      // Initial render of chart.
      nv.addGraph(() => {
        $scope.chart = nv.models.multiBarChart()
          .duration(350)
          .rotateLabels(0)      //Angle to rotate x-axis labels.
          .showControls(true)   //Allow user to switch between 'Grouped' and 'Stacked' mode.
          .groupSpacing(0.1);   //Distance between each group of bars.

        $scope.chart.xAxis.tickFormat((d) => {
          if ($scope.groups[d] !== undefined) {
            return $scope.groups[d].keyword;
          }
          else {
            return '';
          }
        });
        $scope.chart.yAxis.tickFormat(d3.format(',f'));
        $scope.chart.yAxis.axisLabel('Number of Builds');


        $scope.chart_selection = d3.select('#chart svg') .datum($scope.chart_data);
        $scope.chart_selection.call($scope.chart);

        $scope.chart.update();
        nv.utils.windowResize($scope.chart.update);
        return $scope.chart;
      });
    };

    $scope.addGroup = function(groupname, builds) {
      const pos = $scope.groups.length;
      const group = {
        keyword: groupname,
        position: pos,
        builds: builds,
      };
      $scope.groups.push(group);
    };

    $scope.computeChartData = function() {
      $scope.chart_data = [];

      // Count how many builds are in each group.
      const group_totals = [];
      for (let i = 0; i < $scope.groups.length; ++i) {
        group_totals.push({
          x: $scope.groups[i].position,
          y: $scope.groups[i].builds.length,
        });
      }

      // Count how many builds have each type of defect for each group.
      for (let i = 0; i < $scope.cdash.defecttypes.length; ++i) {
        const defect_type = $scope.cdash.defecttypes[i];
        if (!defect_type.selected) {
          continue;
        }
        const defect_values = [];
        for (let j = 0; j < $scope.groups.length; ++j) {
          const group = $scope.groups[j];
          let defective_builds = 0;
          for (let k = 0; k < group.builds.length; ++k) {
            const build = group.builds[k];
            defective_builds += build[defect_type.name] > 0 ? 1 : 0;
          }
          defect_values.push({
            x: group.position,
            y: defective_builds,
          });
        }
        $scope.chart_data.push({
          key: defect_type.prettyname,
          values: defect_values,
        });
      }

      $scope.chart_data.push({
        key: 'Total',
        values: group_totals,
      });
    };

    $scope.updateSelection = function() {
      let uri = `//${location.host}${location.pathname}?project=${$scope.cdash.projectname_encoded}`;
      // Include date range from time chart.
      // eslint-disable-next-line eqeqeq
      if ($scope.cdash.begin_date == $scope.cdash.end_date) {
        uri += `&date=${$scope.cdash.begin_date}`;
      }
      else {
        uri += `&begin=${$scope.cdash.begin_date}&end=${$scope.cdash.end_date}`;
      }

      // Get selected defects.
      const defect_types = [];
      for (let i = 0; i < $scope.cdash.defecttypes.length; ++i) {
        const defect_type = $scope.cdash.defecttypes[i];
        if (defect_type.selected) {
          defect_types.push(defect_type.name);
        }
      }
      if (defect_types.length > 0) {
        uri += `&defects=${defect_types.join()}`;
      }

      window.location = uri;
    };

    // Split the "All" / "Remainder" group when adding a new selection.
    $scope.split = function(groupName, filterExpression) {
      // eslint-disable-next-line eqeqeq
      if ($scope.groups.length == 1) {
        // First split.  Remove the default 'All' group.
        $scope.groups = [];
      }
      else {
        // Remove the 'Remainder' group.
        let index = -1;
        for (let i = 0, len = $scope.groups.length; i < len; i++) {
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
      const matchingBuilds = $filter('filter')($scope.cdash.builds, filterExpression);
      $scope.addGroup(groupName, matchingBuilds);

      // (Re-)create the 'Remainder' group.
      const remainingBuilds = $filter('filter')($scope.cdash.builds, (value, index, array) => {
        let keep_this_build = true;
        for (let i = 0; i < $scope.groups.length; ++i) {
          const group = $scope.groups[i];
          for (let j = 0; j < group.builds.length; ++j) {
            const build = group.builds[j];
            // eslint-disable-next-line eqeqeq
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
      let idx_to_remove = -1;
      for (let i = 0, len = $scope.groups.length; i < len; i++) {
        if ($scope.groups[i].keyword === groupName) {
          idx_to_remove = i;
          break;
        }
      }
      if (idx_to_remove === -1) {
        return;
      }
      const groupToRemove = $scope.groups[idx_to_remove];

      // Find the group named "Remainder".
      let idx_remain = -1;
      for (let i = 0, len = $scope.groups.length; i < len; i++) {
        if ($scope.groups[i].keyword === 'Remainder') {
          idx_remain = i;
          break;
        }
      }
      if (idx_remain === -1) {
        return;
      }

      // Find any builds in the group to be removed that do not belong
      // anywhere else.  Add those back into the "Remainder" group.
      const buildids_to_move = [];
      for (let i = 0, len1 = groupToRemove.builds.length; i < len1; i++) {
        const build = groupToRemove.builds[i];
        let move_this_build = true;
        for (let j = 0, len2 = $scope.groups.length; j < len2; j++) {
          const group = $scope.groups[j];
          if (j === idx_to_remove || group.keyword === 'Remainder') {
            continue;
          }
          for (let k = 0, len3 = group.builds.length; k < len3; k++) {
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
      const filterExpression = function(value, index, array) {
        return buildids_to_move.indexOf(value.id) !== -1;
      };
      const matchingBuilds = $filter('filter')($scope.cdash.builds, filterExpression);
      $scope.groups[idx_remain].builds = $scope.groups[idx_remain].builds.concat(matchingBuilds);

      // Now we can remove the group.
      $scope.groups.splice(idx_to_remove, 1);

      // Update positions of surviving groups.
      for (let i = 0, len = $scope.groups.length; i < len; i++) {
        $scope.groups[i].position = i;
      }

      // eslint-disable-next-line eqeqeq
      if ($scope.groups.length == 1) {
        // If we're back down to one group, rename it from "Remainder" to "All".
        $scope.groups[0].keyword = 'All';
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
      const selection = [
        {
          'name': '',
          'comparator': '',
          'comparators': [],
          'property': '',
          'applied': false,
        },
      ];
      $scope.cdash.selections.push(selection);
    };

    $scope.removeSelection = function(selection) {
      const index = $scope.cdash.selections.indexOf(selection);
      if (index > -1) {
        $scope.cdash.selections.splice(index, 1);
        $scope.unsplit(selection.name);
      }
    };

    $scope.applySelection = function(selection) {
      const user_clause = `${selection.property} ${selection.comparator.symbol} ${selection.value}`;
      const actual_code = `value.properties.${user_clause}`;

      const filterExpression = function(value, index, array) {
        let b = false;
        try {
          b = eval(actual_code);
        }
        catch (err) {
          selection.error = err.message;
        }
        return b;
      };

      selection.applied = true;
      $scope.split(selection.name, filterExpression);
      $scope.addSelection();
    };

    $scope.updateComparators = function(selection) {
      const idx = $scope.cdash.propertykeys.indexOf(selection.property);
      // eslint-disable-next-line eqeqeq
      if (idx != -1) {
        const type = $scope.cdash.properties[selection.property].type;
        selection.comparators = $scope.comparators[type];
      }
    };

    $scope.showModal = function(defect) {
      $scope.cdash.currentDefect = defect;
      modalSvc.showModal(null, () => {}, 'modal-template', $scope, 'lg');
    };

    $scope.toggleDefects = function() {
      if (!$scope.cdash.defectsLoaded) {
        $scope.loadDefects();
      }
      else {
        $scope.cdash.showDefects = !$scope.cdash.showDefects;
      }
    };

    $scope.loadDefects = function() {
      $scope.cdash.loadingDefects = true;

      const buildids = [];
      for (let i = 0; i < $scope.cdash.builds.length ; ++i) {
        buildids.push($scope.cdash.builds[i].id);
      }

      const defect_types = [];
      for (let i = 0; i < $scope.cdash.defecttypes.length; ++i) {
        const defect_type = $scope.cdash.defecttypes[i];
        if (defect_type.selected) {
          defect_types.push(defect_type.name);
        }
      }

      // Query the API to get the types of defects suffered by these builds.
      const parameters = {
        'buildid[]': buildids,
        'defect[]': defect_types,
      };
      $scope.cdash.defectsError = '';
      $http({
        url: 'api/v1/buildProperties.php',
        method: 'GET',
        params: parameters,
      }).then((s) => {
        $scope.cdash.defects = s.data.defects;
        for (let i = 0; i < $scope.cdash.defects.length; ++i) {
          $scope.cdash.defects[i].classifiersLoaded = false;
          $scope.cdash.defects[i].loadingClassifiers = false;
          $scope.cdash.defects[i].showClassifiers = false;
        }
        $scope.cdash.defects = $filter('orderBy')($scope.cdash.defects, $scope.orderByFields);
        $scope.cdash.defectsLoaded = true;
        $scope.cdash.showDefects = true;
        $scope.pageChanged();
      }, (e) => {
        $scope.cdash.defectsError = e.data;
      }).finally(() => {
        $scope.cdash.loadingDefects = false;
      });
    };

    $scope.toggleClassifiers = function(defect) {
      if (!defect.classifiersLoaded) {
        $scope.computeClassifiers(defect);
      }
      defect.showClassifiers = !defect.showClassifiers;
    };

    $scope.computeClassifiers = function(defect) {
      defect.loadingClassifiers = true;
      // Mark each build as passing or failing.
      for (let i = 0; i < $scope.cdash.builds.length; ++i) {
        if (defect.builds.indexOf($scope.cdash.builds[i].id) === -1) {
          $scope.cdash.builds[i].success = true;
        }
        else {
          $scope.cdash.builds[i].success = false;
        }
      }
      // Send the builds back to our API, which will figure out what properties are
      // most informative in distinguishing between passing & failing.
      const parameters = {
        'builds[]': $scope.cdash.builds,
      };
      $http({
        url: 'api/v1/computeClassifier.php',
        method: 'GET',
        params: parameters,
      }).then((s) => {
        defect.classifiers = s.data;
        defect.classifiersLoaded = true;
      }, (e) => {
        $scope.cdash.warning = e.data;
      }).finally(() => {
        defect.loadingClassifiers = false;
      });
    };

    $scope.pageChanged = function() {
      const begin = (($scope.pagination.currentPage - 1) * $scope.pagination.numPerPage)
        , end = begin + $scope.pagination.numPerPage;
      if (end > 0) {
        $scope.pagination.filteredDefects = $scope.cdash.defects.slice(begin, end);
      }
      else {
        $scope.pagination.filteredDefects = $scope.cdash.defects;
      }
    };

    $scope.numDefectsPerPageChanged = function() {
      $.cookie('buildProperties_num_per_page', $scope.pagination.numPerPage, { expires: 365 });
      $scope.pageChanged();
    };

    $scope.updateOrderByFields = function(field, $event) {
      multisort.updateOrderByFields($scope, field, $event);
      $scope.cdash.defects = $filter('orderBy')($scope.cdash.defects, $scope.orderByFields);
      $scope.pageChanged();
      $.cookie('cdash_buildProperties_sort', $scope.orderByFields);
    };
  });
