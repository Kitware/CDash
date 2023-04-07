@extends('cdash', [
    'angular' => true,
    'angular_controller' => 'ViewUpdateController'
])

@section('main_content')
    @verbatim
        <h4 ng-if="::cdash.build.site">
            Files changed on <a ng-href="viewSite.php?siteid={{::cdash.build.siteid}}">{{::cdash.build.site}}</a>
            ({{::cdash.build.buildname}}) as of {{::cdash.build.buildtime}}
        </h4>

        <div ng-if="::cdash.update.revision">
            <b>Revision: </b>
            <a ng-href="{{::cdash.update.revisionurl}}">{{::cdash.update.revision}}</a>
        </div>
        <div ng-if="::cdash.update.priorrevision">
            <b>Prior Revision: </b>
            <a ng-href="{{::cdash.update.revisiondiff}}">{{::cdash.update.priorrevision}}</a>
        </div>

        <br/>
        <!-- Graph -->
        <a ng-click="toggleGraph()">
            <span ng-show="!showGraph">Show Activity Graph</span>
            <span ng-show="showGraph">Hide Activity Graph</span>
        </a>
        <img id="spinner" src="img/loading.gif" ng-show="graphLoading" />
        <div id="graphoptions"></div>
        <div id="graph"></div>
        <div class="center-text" id="graph_holder" ng-show="showGraph"></div>

        <h3 ng-if="::cdash.update.status" class="error">
            {{::cdash.update.status}}
        </h3>

        <!-- Display lists of updated files -->
        <div ng-repeat="group in ::cdash.updategroups"
             updated-files>
        </div>
    @endverbatim
@endsection
