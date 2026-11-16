// Initializes jQuery in the window scope before plugin code runs below.
import './jquery-init.js';

import 'jquery-ui-dist/jquery-ui.js';
import 'jquery.cookie/jquery.cookie.js';
import './bootstrap.min.js';
import 'angular/angular.min.js';
import 'angular-animate/angular-animate.min.js';
import 'angular-ui-sortable/dist/sortable.js';
import './ui-bootstrap-tpls-0.14.2.min.js';

const CDash = angular.module('CDash', [
  'ngAnimate',
  'ui.sortable',
  'ui.bootstrap',
]);

// $http sends the CSRF token from the XSRF-TOKEN cookie. If that token was stale (e.g., the
// session expired and "remember me" logged the user back in), the 419 response sets a fresh
// cookie, so retry once.
CDash.config(['$httpProvider', function ($httpProvider) {
  $httpProvider.interceptors.push(['$injector', '$q', function ($injector, $q) {
    return {
      responseError: function (rejection) {
        if (rejection.status !== 419 || rejection.config.csrfRetried) {
          return $q.reject(rejection);
        }
        return $injector.get('$http')({ ...rejection.config, csrfRetried: true });
      },
    };
  }]);
}]);

// subProjectTable.html is only ever reached via ng-include in server-rendered
// view HTML (not through Angular DI), so its content is pre-loaded into
// $templateCache under the same path ng-include requests, instead of being
// fetched over HTTP.
import subProjectTableHtml from './views/partials/subProjectTable.html';
CDash.run(["$templateCache", function ($templateCache) {
  $templateCache.put('assets/js/angular/views/partials/subProjectTable.html', subProjectTableHtml);
}]);

import { ManageSubProjectController, filter_subproject_groups } from "./controllers/manageSubProject";
CDash.controller('ManageSubProjectController', ["$scope", "$http", "apiLoader", ManageSubProjectController]);
CDash.filter('filter_subproject_groups', filter_subproject_groups);

import { ManageBuildGroupController, filter_buildgroups } from "./controllers/manageBuildGroup";
CDash.controller('ManageBuildGroupController', ["$scope", "$http", "apiLoader", "modalSvc", ManageBuildGroupController]);
CDash.filter('filter_buildgroups', filter_buildgroups);

import { ViewSubProjectsController } from "./controllers/viewSubProjects";
CDash.controller('ViewSubProjectsController', ["$scope", "multisort", "apiLoader", ViewSubProjectsController]);

import { ManageOverviewController } from "./controllers/manageOverview";
CDash.controller('ManageOverviewController', ["$scope", "$http", "apiLoader", ManageOverviewController]);

import { HeadController } from "./controllers/head";
CDash.controller('HeadController', ["$rootScope", HeadController]);

import { SubProjectController } from "./controllers/subproject";
CDash.controller('SubProjectController', ["$scope", "$rootScope", "$http", SubProjectController]);

import { OverviewController, linechart, bulletchart } from "./controllers/overview";
CDash.controller('OverviewController', ["$scope", "$location", "anchors", "apiLoader", OverviewController]);
CDash.directive('linechart', linechart);
CDash.directive('bulletchart', bulletchart);

import { modalSvc } from './services/modal.js';
CDash.factory('modalSvc', ["$uibModal", modalSvc]);

import { anchorsSvc } from './services/anchors.js';
CDash.service('anchors', ["$anchorScroll", "$location", "$timeout", anchorsSvc]);

import { apiLoader } from './services/apiLoader.js';
CDash.factory('apiLoader', ["$http", "$rootScope", "$window", "renderTimer", apiLoader]);

import { multisort } from './services/multisort.js';
CDash.factory('multisort', multisort);

import { renderTimer } from './services/renderTimer.js';
CDash.factory('renderTimer', ["$timeout", renderTimer]);

import { convertToNumber } from './directives/convertToNumber.js';
CDash.directive('convertToNumber', convertToNumber);

import { showEmptySubProjectsLast } from './filters/showEmptySubProjectsLast.js';
CDash.filter('showEmptySubProjectsLast', showEmptySubProjectsLast);
