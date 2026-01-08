// Initializes jQuery in the window scope before plugin code runs below.
import './jquery-init.js';

import 'jquery-ui-dist/jquery-ui.js';
import 'jquery.cookie/jquery.cookie.js';
import './bootstrap.min.js';
import './je_compare.js';
import 'angular/angular.min.js';
import 'angular-animate/angular-animate.min.js';
import 'angular-ui-bootstrap/dist/ui-bootstrap.js';
import 'angular-ui-sortable/dist/sortable.js';
import 'as-jqplot/dist/jquery.jqplot.js';
import 'as-jqplot/dist/plugins/jqplot.dateAxisRenderer.js';
import 'as-jqplot/dist/plugins/jqplot.highlighter.js';
import './ui-bootstrap-tpls-0.14.2.min.js';
import './tabNavigation.js';
import './jquery.tablesorter.js';
import './jquery.metadata.js';

const CDash = angular.module('CDash', [
  'ngAnimate',
  'ui.sortable',
  'ui.bootstrap',
]);

import { VERSION } from '../../../public/assets/js/angular/version.js';
CDash.constant('VERSION', VERSION);

import { CompareCoverageController } from "./controllers/compareCoverage";
CDash.controller('CompareCoverageController', ["$scope", "$rootScope", "apiLoader", "filters", "multisort", CompareCoverageController]);

import { ManageSubProjectController, filter_subproject_groups } from "./controllers/manageSubProject";
CDash.controller('ManageSubProjectController', ["$scope", "$http", "apiLoader", ManageSubProjectController]);
CDash.filter('filter_subproject_groups', filter_subproject_groups);

import { ManageBuildGroupController, filter_builds, filter_buildgroups } from "./controllers/manageBuildGroup";
CDash.controller('ManageBuildGroupController', ["$scope", "$http", "apiLoader", "modalSvc", ManageBuildGroupController]);
CDash.filter('filter_builds', filter_builds);
CDash.filter('filter_buildgroups', filter_buildgroups);

import { ViewSubProjectsController } from "./controllers/viewSubProjects";
CDash.controller('ViewSubProjectsController', ["$scope", "multisort", "apiLoader", ViewSubProjectsController]);

import { BuildErrorController, buildError } from "./controllers/viewBuildError";
CDash.controller('ViewBuildErrorController', ["$scope", "$sce", "apiLoader", BuildErrorController]);
CDash.directive('buildError', buildError);

import { ManageOverviewController } from "./controllers/manageOverview";
CDash.controller('ManageOverviewController', ["$scope", "$http", "apiLoader", ManageOverviewController]);

import { TestOverviewController } from "./controllers/testOverview";
CDash.controller('TestOverviewController', ["$scope", "$rootScope", "$filter", "apiLoader", "filters", "multisort", TestOverviewController]);

import { HeadController } from "./controllers/head";
CDash.controller('HeadController', ["$rootScope", "$document", HeadController]);

import { IndexController, showEmptyBuildsLast } from "./controllers/index";
CDash.controller('IndexController', ["$scope", "$rootScope", "$location", "$http", "$filter", "$timeout", "anchors", "apiLoader", "filters", "multisort", "modalSvc", IndexController]);
CDash.filter('showEmptyBuildsLast', showEmptyBuildsLast);

import { SubProjectController } from "./controllers/subproject";
CDash.controller('SubProjectController', ["$scope", "$rootScope", "$http", SubProjectController]);

import { QueryTestsController } from "./controllers/queryTests";
CDash.controller('QueryTestsController', ["$scope", "$rootScope", "$filter", "apiLoader", "filters", "multisort", QueryTestsController]);

import { ViewTestController } from "./controllers/viewTest";
CDash.controller('ViewTestController', ["$scope", "$rootScope", "$http", "$filter", "$q", "apiLoader", "multisort", "filters", ViewTestController]);

import { OverviewController, linechart, bulletchart } from "./controllers/overview";
CDash.controller('OverviewController', ["$scope", "$location", "anchors", "apiLoader", OverviewController]);
CDash.directive('linechart', linechart);
CDash.directive('bulletchart', bulletchart);

import { FiltersController, filterRow, filterButtons } from "./controllers/filters";
CDash.controller('FiltersController', ["$scope", "$rootScope", "$http", "$timeout", FiltersController]);
CDash.directive('filterRow', filterRow);
CDash.directive('filterButtons', filterButtons);

import { modalSvc } from './services/modal.js';
CDash.factory('modalSvc', ["$uibModal", modalSvc]);

import { anchorsSvc } from './services/anchors.js';
CDash.service('anchors', ["$anchorScroll", "$location", "$timeout", anchorsSvc]);

import { filtersSvc } from './services/filters.js';
CDash.factory('filters', filtersSvc);

import { apiLoader } from './services/apiLoader.js';
CDash.factory('apiLoader', ["$http", "$rootScope", "$window", "renderTimer", apiLoader]);

import { multisort } from './services/multisort.js';
CDash.factory('multisort', multisort);

import { renderTimer } from './services/renderTimer.js';
CDash.factory('renderTimer', ["$timeout", renderTimer]);

import { build } from './directives/build.js';
CDash.directive('build', ["VERSION", build]);

import { timeline } from './directives/timeline.js';
CDash.directive('timeline', ["VERSION", timeline]);

import { daterange } from './directives/daterange.js';
CDash.directive('daterange', ["VERSION", daterange]);

import { buildgroup } from './directives/buildgroup.js';
CDash.directive('buildgroup', ["VERSION", buildgroup]);

import { autocomplete } from './directives/autocomplete.js';
CDash.directive('autoComplete', ["$parse", autocomplete]);

import { onFinishRender } from './directives/onFinishRender.js';
CDash.directive('onFinishRender', ["$timeout", onFinishRender]);

import { convertToNumber } from './directives/convertToNumber.js';
CDash.directive('convertToNumber', convertToNumber);

import { ctestNonXmlCharEscape, terminalColors, trustAsHtml } from './filters/terminalColors.js';
CDash.filter('ctestNonXmlCharEscape', ctestNonXmlCharEscape);
CDash.filter('terminalColors', terminalColors);
CDash.filter('trustAsHtml', trustAsHtml);

import { showEmptySubProjectsLast } from './filters/showEmptySubProjectsLast.js';
CDash.filter('showEmptySubProjectsLast', showEmptySubProjectsLast);
