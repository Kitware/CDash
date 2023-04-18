// Keep subprojects with missing fields at the bottom of the list.
// Shared between index.html & viewSubProjects.html
CDash.filter('showEmptySubProjectsLast', () => {
  return function (subprojects, sortField) {
    if (!angular.isArray(subprojects)) {
      return;
    }
    if (!sortField) {
      return subprojects;
    }
    if (angular.isArray(sortField)) {
      if (sortField.length < 1) {
        return subprojects;
      }
      sortField = sortField[0];
    }
    if (sortField.charAt(0) === '-') {
      sortField = sortField.substring(1);
    }

    // First weed out the subprojects that are completely empty.
    // These will stay at the bottom of the table.
    const empty = subprojects.filter((subproject) => {
      // eslint-disable-next-line eqeqeq
      return (subproject.nconfigureerror == 0 && subproject.nconfigurewarning == 0 && subproject.nconfigurepass == 0 && subproject.nbuilderror == 0 && subproject.nbuildwarning == 0 && subproject.nbuildpass == 0 && subproject.ntestfail == 0 && subproject.ntestnotrun == 0 && subproject.ntestpass == 0);
    });

    const nonempty = subprojects.filter((subproject) => {
      // eslint-disable-next-line eqeqeq
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
      // eslint-disable-next-line no-var
      var present = nonempty.filter((subproject) => {
        // eslint-disable-next-line eqeqeq
        return (subproject.nconfigureerror != 0 || subproject.nconfigurewarning != 0 || subproject.nconfigurepass != 0);
      });
      // eslint-disable-next-line no-var
      var missing = nonempty.filter((subproject) => {
        // eslint-disable-next-line eqeqeq
        return (subproject.nconfigureerror == 0 && subproject.nconfigurewarning == 0 && subproject.nconfigurepass == 0);
      });
      present = present.concat(missing);
      break;

    case 'nbuilderror':
    case 'nbuildwarning':
    case 'nbuildpass':
      // eslint-disable-next-line no-var
      var present = nonempty.filter((subproject) => {
        // eslint-disable-next-line eqeqeq
        return (subproject.nbuilderror != 0 || subproject.nbuildwarning != 0 || subproject.nbuildpass != 0);
      });
      // eslint-disable-next-line no-var
      var missing = nonempty.filter((subproject) => {
        // eslint-disable-next-line eqeqeq
        return (subproject.nbuilderror == 0 && subproject.nbuildwarning == 0 && subproject.nbuildpass == 0);
      });
      present = present.concat(missing);
      break;

    case 'ntestfail':
    case 'ntestnotrun':
    case 'ntestpass':
      // eslint-disable-next-line no-var
      var present = nonempty.filter((subproject) => {
        // eslint-disable-next-line eqeqeq
        return (subproject.ntestfail != 0 || subproject.ntestnotrun != 0 || subproject.ntestpass != 0);
      });
      // eslint-disable-next-line no-var
      var missing = nonempty.filter((subproject) => {
        // eslint-disable-next-line eqeqeq
        return (subproject.ntestfail == 0 && subproject.ntestnotrun == 0 && subproject.ntestpass == 0);
      });
      present = present.concat(missing);
      break;
    }

    return present.concat(empty);
  };
});

