var CDash = angular
.module('CDash', [
    'ui.sortable',
    'ui.bootstrap',
    'ngAnimate'
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
