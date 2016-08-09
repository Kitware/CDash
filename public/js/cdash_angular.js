var CDash = angular
.module('CDash', [
    'ui.sortable',
    'ui.bootstrap',
    'ngAnimate',
    'ngFileUpload'
    ]);

// Keep subprojects with missing fields at the bottom of the list.
// Shared between index.html & viewSubProjects.html
CDash.filter("showEmptySubProjectsLast", function () {
  return function (subprojects, sortField) {
    if (!angular.isArray(subprojects)) return;
    if (!sortField) return subprojects;
    if (angular.isArray(sortField)) {
      if (sortField.length < 1) {
        return subprojects;
      }
      sortField = sortField[0];
    }
    if (sortField.charAt(0) == '-') {
      sortField = sortField.substring(1);
    }

    // First weed out the subprojects that are completely empty.
    // These will stay at the bottom of the table.
    var empty = subprojects.filter(function (subproject) {
      return (subproject.nconfigureerror == 0 && subproject.nconfigurewarning == 0 && subproject.nconfigurepass == 0 && subproject.nbuilderror == 0 && subproject.nbuildwarning == 0 && subproject.nbuildpass == 0 && subproject.ntestfail == 0 && subproject.ntestnotrun == 0 && subproject.ntestpass == 0);
    });

    var nonempty = subprojects.filter(function (subproject) {
      return (subproject.nconfigureerror != 0 || subproject.nconfigurewarning != 0 || subproject.nconfigurepass != 0 || subproject.nbuilderror != 0 || subproject.nbuildwarning != 0 || subproject.nbuildpass != 0 || subproject.ntestfail != 0 || subproject.ntestnotrun != 0 || subproject.ntestpass != 0);
    });

    switch (sortField) {
      case 'name':
      case 'lastsubmission':
      default:
        return subprojects;
        break;

      case 'nconfigureerror':
      case 'nconfigurewarning':
      case 'nconfigurepass':
        var present = nonempty.filter(function (subproject) {
          return (subproject.nconfigureerror != 0 || subproject.nconfigurewarning != 0 || subproject.nconfigurepass != 0);
        });
        var missing = nonempty.filter(function (subproject) {
          return (subproject.nconfigureerror == 0 && subproject.nconfigurewarning == 0 && subproject.nconfigurepass == 0);
        });
        present = present.concat(missing);
        break;

      case 'nbuilderror':
      case 'nbuildwarning':
      case 'nbuildpass':
        var present = nonempty.filter(function (subproject) {
          return (subproject.nbuilderror != 0 || subproject.nbuildwarning != 0 || subproject.nbuildpass != 0);
        });
        var missing = nonempty.filter(function (subproject) {
          return (subproject.nbuilderror == 0 && subproject.nbuildwarning == 0 && subproject.nbuildpass == 0);
        });
        present = present.concat(missing);
        break;

      case 'ntestfail':
      case 'ntestnotrun':
      case 'ntestpass':
        var present = nonempty.filter(function (subproject) {
          return (subproject.ntestfail != 0 || subproject.ntestnotrun != 0 || subproject.ntestpass != 0);
        });
        var missing = nonempty.filter(function (subproject) {
          return (subproject.ntestfail == 0 && subproject.ntestnotrun == 0 && subproject.ntestpass == 0);
        });
        present = present.concat(missing);
        break;
    }

    return present.concat(empty);
  };
});

// Sort by multiple columns at once.
CDash.factory('multisort', function () {
  return {
    updateOrderByFields: function(obj, field, $event) {
      // Note that by default we sort in descending order.
      // This is accomplished by prepending the field with '-'.

      var idx = obj.orderByFields.indexOf('-' + field);
      if ($event.shiftKey) {
        // When shift is held down we append this field to the list of sorting
        // criteria.
        if (idx != -1) {
          // Reverse sort for this field because it was already in the list.
          obj.orderByFields[idx] = field;
        } else {
          var idx2 = obj.orderByFields.indexOf(field);
          if (idx2 != -1) {
            // If field is in the list replace it with -field.
            obj.orderByFields[idx2] = '-' + field;
          } else {
            // Otherwise just append -field to the end of the list.
            obj.orderByFields.push('-' + field);
          }
        }
      } else {
        // Shift wasn't held down so this field is the only criterion that we
        // will use for sorting.
        if (idx != -1) {
          obj.orderByFields = [field];
        } else {
          obj.orderByFields = ['-' + field];
        }
      }
    }
  };
});

CDash.factory('filters', function () {
  var filters = {};

  filters.toggle = function(show) {
    var str = new String(window.location);
    var idx = str.indexOf("&showfilters=", 0);
    if (idx > 0) {
      if (show) {
        window.location.href = str.replace("&showfilters=0", "&showfilters=1");
      } else {
        window.location.href = str.replace("&showfilters=1", "&showfilters=0");
      }
    }
  }

  filters.getString = function() {
    var str = new String(window.location);
    var idx = str.indexOf("&filtercount=", 0);
    if (idx > 0) {
      return str.substr(idx);
    }
    else {
      return "";
    }
  }

  filters.getLabelString = function(filterdata) {
    if ( filterdata == undefined ) {
      return "";
    }

    var s = "&showfilters=1";
    var n = 0;
    for (var i = 1; i <= filterdata.filters.length; i++) {
      var field = filterdata.filters[i-1].field;
      if (field == 'label') {
        n = n + 1;
        s = s + "&field" + i + "=" + escape(field);
        s = s + "&compare" + i + "=" + escape(filterdata.filters[i-1].compare);
        s = s + "&value" + i + "=" + escape(filterdata.filters[i-1].value);
      }
    }

    if (n > 1) {
      s = "&filtercombine=" + filterdata.filtercombine + s;
    }

    if (n > 0) {
      return "&filtercount=" + n + s;
    }
    else {
      return "";
    }
  }

  return filters;
});

// Time how long the initial render takes and add this to the value
// shown at the bottom of the page.
CDash.factory('renderTimer', function ($timeout) {
  var initialRender = function(controllerScope, cdash) {
    // Redirect if the API told us to.
    if ('redirect' in cdash) {
      window.location = cdash.redirect;
      return;
    }

    if (!"generationtime" in cdash) {
      return;
    }
    var start = new Date();

    // This is when the initial page render happens.
    controllerScope.cdash = cdash;

    $timeout(function() {
      var renderTime = +((new Date() - start) / 1000);
      controllerScope.cdash.generationtime = (renderTime + cdash.generationtime).toFixed(2);
    }, 0, true, controllerScope, cdash);
  };
  return {
    initialRender: initialRender
  };
});

CDash.directive('convertToNumber', function() {
  return {
    require: 'ngModel',
    link: function(scope, element, attrs, ngModel) {
      ngModel.$parsers.push(function(val) {
        return parseInt(val, 10);
      });
      ngModel.$formatters.push(function(val) {
        return '' + val;
      });
    }
  };
});

CDash.directive('onFinishRender', function ($timeout) {
  return {
    restrict: 'A',
    link: function (scope, element, attr) {
      if (scope.$last === true) {
        scope.$evalAsync(attr.onFinishRender);
      }
    }
  }
});
