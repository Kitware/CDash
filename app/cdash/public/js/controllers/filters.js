angular
    .module('CDash')
    .controller('FiltersController', FiltersController);

function FiltersController($scope, $rootScope, $http, $timeout) {

  // The different type of data filters that we support.
  $scope.filterdefinitions = {
    "buildduration": {
      "text": "Build Duration",
      "type": "number",
      "defaultvalue": 0
    },
    "builderrors": {
      "text": "Build Errors",
      "type": "number",
      "defaultvalue": 0
    },
    "buildgenerator": {
      "text": "Submission Client",
      "type": "string",
      "defaultvalue": "2.8"
    },
    "buildname": {
      "text": "Build Name",
      "type": "string",
      "defaultvalue": ""
    },
    "buildstamp": {
      "text": "Build Stamp",
      "type": "string",
      "defaultvalue": ""
    },
    "buildstarttime": {
      "text": "Build Start Time",
      "type": "date",
      "defaultvalue": ""
    },
    "buildtype": {
      "text": "Build Type",
      "type": "string",
      "defaultvalue": "Nightly"
    },
    "buildwarnings": {
      "text": "Build Warnings",
      "type": "number",
      "defaultvalue": 0
    },
    "configureduration": {
      "text": "Configure Duration",
      "type": "number",
      "defaultvalue": 0
    },
    "configureerrors": {
      "text": "Configure Errors",
      "type": "number",
      "defaultvalue": 0
    },
    "configurewarnings": {
      "text": "Configure Warnings",
      "type": "number",
      "defaultvalue": 0
    },
    "coveredlines": {
      "text": "Covered Lines",
      "type": "number",
      "defaultvalue": 0
    },
    "details": {
      "text": "Details",
      "type": "string",
      "defaultvalue": ""
    },
    "expected": {
      "text": "Expected",
      "type": "bool",
      "defaultvalue": ""
    },
    "filename": {
      "text": "Filename",
      "type": "string",
      "defaultvalue": ""
    },
    "groupname": {
      "text": "Group",
      "type": "string",
      "defaultvalue": "Nightly"
    },
    "hascoverage": {
      "text": "Has Coverage",
      "type": "bool",
      "defaultvalue": ""
    },
    "hasctestnotes": {
      "text": "Has CTest Notes",
      "type": "bool",
      "defaultvalue": ""
    },
    "hasdynamicanalysis": {
      "text": "Has Dynamic Analysis",
      "type": "bool",
      "defaultvalue": ""
    },
    "hasusernotes": {
      "text": "Has User Notes",
      "type": "bool",
      "defaultvalue": ""
    },
    "label": {
      "text": "Label",
      "type": "string",
      "defaultvalue": ""
    },
    "priority": {
      "text": "Priority",
      "type": "string",
      "defaultvalue": ""
    },
    "revision": {
      "text": "Revision",
      "type": "string",
      "defaultvalue": ""
    },
    "site": {
      "text": "Site",
      "type": "string",
      "defaultvalue": ""
    },
    "status": {
      "text": "Status",
      "type": "string",
      "defaultvalue": ""
    },
    "subproject": {
      "text": "SubProject",
      "type": "string",
      "defaultvalue": ""
    },
    "subprojects": {
      "text": "SubProjects",
      "type": "list",
      "defaultvalue": "",
      "content": true
    },
    "testname": {
      "text": "Test Name",
      "type": "string",
      "defaultvalue": ""
    },
    "testoutput": {
      "text": "Test Output",
      "type": "search",
      "defaultvalue": "",
      "content": true
    },
    "testsduration": {
      "text": "Tests Duration",
      "type": "number",
      "defaultvalue": 0
    },
    "testsfailed": {
      "text": "Tests Failed",
      "type": "number",
      "defaultvalue": 0
    },
    "testsnotrun": {
      "text": "Tests Not Run",
      "type": "number",
      "defaultvalue": 0
    },
    "testspassed": {
      "text": "Tests Passed",
      "type": "number",
      "defaultvalue": 0
    },
    "testtimestatus": {
      "text": "Tests Timing Failed",
      "type": "number",
      "defaultvalue": 0
    },
    "time": {
      "text": "Time",
      "type": "number",
      "defaultvalue": ""
    },
    "timestatus": {
      "text": "Time Status",
      "type": "string",
      "defaultvalue": ""
    },
    "totallines": {
      "text": "Total Lines",
      "type": "number",
      "defaultvalue": 0
    },
    "uncoveredlines": {
      "text": "Uncovered Lines",
      "type": "number",
      "defaultvalue": 0
    },
    "updateduration": {
      "text": "Update Duration",
      "type": "number",
      "defaultvalue": 0
    },
    "updatedfiles": {
      "text": "Updated Files",
      "type": "number",
      "defaultvalue": 0
    }
  }

  // How our data types can be compared.
  $scope.comparisons = {
    "bool": [
      {"value": 1, text: "is true"},
      {"value": 2, text: "is false"}
     ],
    "date": [
      {"value": 81, text: "is"},
      {"value": 82, text: "is not"},
      {"value": 83, text: "is after"},
      {"value": 84, text: "is before"}
    ],
    "number": [
      {"value": 41, text: "is"},
      {"value": 42, text: "is not"},
      {"value": 43, text: "is greater than"},
      {"value": 44, text: "is less than"}
    ],
    "search": [
      {"value": 94, text: "exclude"},
      {"value": 95, text: "include"},
      {"value": 96, text: "exclude regex"},
      {"value": 97, text: "include regex"},
    ],
    "string": [
      {"value": 63, text: "contains"},
      {"value": 64, text: "does not contain"},
      {"value": 61, text: "is"},
      {"value": 62, text: "is not"},
      {"value": 65, text: "starts with"},
      {"value": 66, text: "ends with"}
    ],
    "list": [
      {"value": 92, text: "exclude"},
      {"value": 93, text: "include"},
    ]
  };

  // Add a new row to our list of filters.
  $scope.addFilter = function(block, index) {
    var previous_filter = block.filters[index-1];
    if (previous_filter.hasOwnProperty('filters')) {
      var filter_to_copy = previous_filter.filters[previous_filter.filters.length - 1];
    } else {
      var filter_to_copy = previous_filter;
    }
    var filter = {
      field: filter_to_copy.field,
      compare: filter_to_copy.compare,
      value: filter_to_copy.value,
    };
    block.filters.splice(index, 0, filter);
  };

  // Remove a filter from our list.
  $scope.removeFilter = function(block, index) {
    block.filters.splice(index-1, 1);
  };

  // Check to see if the type of a filter was changed by the user.
  $scope.changeFilter = function(filter) {
    var type = $scope.filterdefinitions[filter.field].type;
    var comparisons = $scope.comparisons[type];

    // Assign the default comparison value to this filter if its type has changed.
    var found = false;
    for (i in comparisons) {
      if (comparisons[i].value == filter.compare) {
        found = true;
        break;
      }
    }
    if (!found) {
      filter.compare = "0";
    }
  };

  $scope.applyFilters = function() {
    var url = this.createHyperlink();
    window.location.href = url;
  };

  $scope.clearFilters = function() {
    $scope.filterdata.filters = [];
    $scope.applyFilters();
  };

  $scope.displayHyperlink = function() {
    var url = this.createHyperlink();
    $("#div_filtersAsUrl").html("<a href=\"" + url + "\">" + url + "</a>");
  };

  $scope.createHyperlink = function() {
    // Count the number of filters.
    var n = $scope.filterdata.filters.length,
    // Read the current query string parameters.
    params = window.location.search.replace('?', '').split(/[&;]/g);

    // Search for and remove any existing filter/date params.
    var filterParams = ['filtercount', 'filtercombine', 'showfilters', 'field', 'compare', 'value', 'limit', 'date', 'begin', 'end'];
    // Reverse iteration because this is destructive.
    for (var i = params.length; i-- > 0;) {
      for (var j = 0; j < filterParams.length; j++) {
        if (params[i].startsWith(filterParams[j])) {
          params.splice(i, 1);
          break;
        }
      }
    }

    // Reconstruct the URL to include the query string parameters that survived
    // the culling above.
    var s = window.location.origin + window.location.pathname + '?';
    for (var i = 0; i < params.length; i++) {
      s += params[i] + '&';
    }

    // Now add date params.
    if ($scope.cdash.begin && $scope.cdash.end) {
      // Both begin and end were set.
      if ($scope.cdash.begin == $scope.cdash.end) {
        // If they are the same, then convert it to a 'date' param.
        s += 'date=' + $scope.cdash.begin + '&';
      } else {
        // Otherwise construct a URL for a range of date.
        s += 'begin=' + $scope.cdash.begin + '&end=' + $scope.cdash.end + '&';
      }
    } else if ($scope.cdash.begin || $scope.cdash.end) {
      // If only one of begin/end was set, use its value as a 'date' param.
      var date = $scope.cdash.begin ? $scope.cdash.begin : $scope.cdash.end;
      s += 'date=' + date + '&';
    } else if (!$scope.cdash.begin && !$scope.cdash.end) {
      // If both begin and end are explicitly empty then we do not add any
      // date fields to the URL.
    } else if ($scope.cdash.date_set && $scope.cdash.date) {
      // Otherwise include the 'date' param if it was explicitly set (not left blank).
      s += 'date=' + $scope.cdash.date + '&';
    }

    // Now add filter params.
    s = s + "filtercount=" + n;
    s = s + "&showfilters=1";

    l = $("#id_limit").val();
    if (l != 0) {
      s = s + "&limit=" + l;
    }

    if (n > 1) {
      s = s + "&filtercombine=" + $scope.filterdata.filtercombine;
    }

    for (var i = 1; i <= n; i++) {
      if ($scope.filterdata.filters[i-1].hasOwnProperty('filters')) {
        var num_subfilters = $scope.filterdata.filters[i-1].filters.length;
        if (num_subfilters < 1) {
          continue;
        }
        var prefix = "field" + i;
        s = s + "&" + prefix + "=block";
        s = s + "&" + prefix + "count=" + num_subfilters;
        for (var j = 1; j <= num_subfilters ; j++) {
          s = s + "&" + prefix + "field" + j + "=" + encodeURIComponent($scope.filterdata.filters[i-1].filters[j-1].field);
          s = s + "&" + prefix + "compare" + j + "=" + encodeURIComponent($scope.filterdata.filters[i-1].filters[j-1].compare);
          s = s + "&" + prefix + "value" + j + "=" + encodeURIComponent($scope.filterdata.filters[i-1].filters[j-1].value);
        }
      } else {
        s = s + "&field" + i + "=" + encodeURIComponent($scope.filterdata.filters[i-1].field);
        s = s + "&compare" + i + "=" + encodeURIComponent($scope.filterdata.filters[i-1].compare);
        s = s + "&value" + i + "=" + encodeURIComponent($scope.filterdata.filters[i-1].value);
      }
    }

    return s;
  }

  // Add a sub-block of filters to our list.
  $scope.addFilterBlock = function(index) {
    var parent_filter = $scope.filterdata.filters[index-1];
    var or_block = {
      filters: []
    };
    var filter = {
      field: parent_filter.field,
      compare: parent_filter.compare,
      value: parent_filter.value,
    };
    or_block.filters.push(filter);
    $scope.filterdata.filters.splice(index, 0, or_block);
  };

  // Change the value of filterdata.othercombine.
  $scope.updateCombine = function(filterdata) {
    if (filterdata.filtercombine == 'or') {
      filterdata.othercombine = 'and';
    } else {
      filterdata.othercombine = 'or';
    }
  };

  var url = window.location.pathname;
  var filename = url.substring(url.lastIndexOf('/')+1);
  var filename_for_docs = filename;
  if (filename === 'index.php' && 'parentid' in $rootScope.queryString) {
    filename = 'indexchildren.php';
  }
  $rootScope.queryString['page_id'] = filename;
  $rootScope.queryString['showlimit'] = 0;

  $http({
    url: 'api/v1/filterdata.php',
    method: 'GET',
    params: $rootScope.queryString
  }).then(function success(s) {
    var filterdata = s.data;
    filterdata.filters.forEach(function(filter) {
      if (filter.hasOwnProperty('filters')) {
        filter.filters.forEach(function(subfilter) {
          subfilter.compare = subfilter.compare.toString();
        });
      } else if (filter.hasOwnProperty('compare')) {
        filter.compare = filter.compare.toString();
      }
    });

    filterdata.availablenoncontentfilters = filterdata.availablefilters.slice();
    filterdata.availablenoncontentfilters.forEach(function(availablefilter, index, object) {
      var filter_definition = $scope.filterdefinitions[availablefilter];
      if (filter_definition.hasOwnProperty('content') && filter_definition.content) {
        object.splice(index, 1);
      }
    });

    $scope.filterdata = filterdata;
    $scope.cdash.page = filename_for_docs;
    // Set default values for date picker.
    if ($scope.cdash.date_set && $scope.cdash.date) {
      if (!$scope.cdash.begin) {
        $scope.cdash.begin = $scope.cdash.date;
      }
      if (!$scope.cdash.end) {
        $scope.cdash.end = $scope.cdash.date;
      }
    }
  });
}

angular.module('CDash')
       .directive('filterRow', function (VERSION) {
  return {
    templateUrl: 'build/views/partials/filterRow_' + VERSION + '.html'
  };
});

angular.module('CDash')
       .directive('filterButtons', function (VERSION) {
  return {
    templateUrl: 'build/views/partials/filterButtons_' + VERSION + '.html'
  };
});
