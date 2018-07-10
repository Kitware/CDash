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
      "defaultvalue": ""
    },
    "testname": {
      "text": "Test Name",
      "type": "string",
      "defaultvalue": ""
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
  $scope.addFilter = function(index) {
    var parent_filter = $scope.filterdata.filters[index-1];
    var filter = {
      key: parent_filter.key,
      compare: parent_filter.compare,
      value: parent_filter.value,
    };
    $scope.filterdata.filters.splice(index, 0, filter);
  };

  // Remove a filter from our list.
  $scope.removeFilter = function(index) {
    $scope.filterdata.filters.splice(index-1, 1);
  };

  // Check to see if the type of a filter was changed by the user.
  $scope.changeFilter = function(index) {
    var filter = $scope.filterdata.filters[index-1];
    var type = $scope.filterdefinitions[filter.key].type;
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

    // Search for and remove any existing filter params.
    var filterParams = ['filtercount', 'showfilters', 'field', 'compare', 'value', 'limit'];
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

    // Now add filter params.
    s = s + "filtercount=" + n;
    s = s + "&showfilters=1";

    l = $("#id_limit").val();
    if (l != 0) {
      s = s + "&limit=" + l;
    }

    if (n > 1) {
      s = s + "&filtercombine=" + $("#id_filtercombine").val();
    }

    for (var i = 1; i <= n; i++) {
      s = s + "&field" + i + "=" + escape($scope.filterdata.filters[i-1].key);
      s = s + "&compare" + i + "=" + escape($scope.filterdata.filters[i-1].compare);
      s = s + "&value" + i + "=" + escape($scope.filterdata.filters[i-1].value);
    }

    return s;
  }

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
      filter.compare = filter.compare.toString();
    });

    $scope.filterdata = filterdata;
    $scope.cdash.page = filename_for_docs;
  });
}
