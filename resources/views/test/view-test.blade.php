@extends('cdash', [
    'angular' => true,
    'angular_controller' => 'ViewTestController'
])

@php
    use App\Http\Controllers\AbstractController;

    $js_version = AbstractController::getJsVersion();
@endphp

@section('main_content')
    @verbatim
        <h3>Testing started on {{::cdash.build.testtime}}</h3>

        <table ng-if="::!cdash.parentBuild" class="tabb striped">
            <thead>
                <tr class="table-heading1">
                    <th colspan="2" class="header">System Information</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <b>Site Name:</b>
                    </td>
                    <td>
                        <a ng-href="sites/{{::cdash.build.siteid}}"
                           ng-click="cancelAjax()">{{::cdash.build.site}}</a>
                    </td>
                </tr>

                <tr>
                    <td>
                        <b>Build Name:</b>
                    </td>
                    <td>
                        <a ng-href="build/{{::cdash.build.buildid}}"
                           ng-click="cancelAjax()">{{::cdash.build.buildname}}</a>
                    </td>
                </tr>

                <tr>
                    <td>
                        <b>Total time:</b>
                    </td>
                    <td>
                        {{::cdash.totaltime}}
                    </td>
                </tr>

                <!-- Display Operating System information  -->
                <tr ng-if="::cdash.build.osname">
                    <td>
                        <b>OS Name:</b>
                    </td>
                    <td>
                        {{::cdash.build.osname}}
                    </td>
                </tr>

                <tr ng-if="::cdash.build.osplatform">
                    <td>
                        <b>OS Platform:</b>
                    </td>
                    <td>
                        {{::cdash.build.osplatform}}
                    </td>
                </tr>

                <tr ng-if="::cdash.build.osrelease">
                    <td>
                        <b>OS Release:</b>
                    </td>
                    <td>
                        {{::cdash.build.osrelease}}
                    </td>
                </tr>

                <tr ng-if="::cdash.build.osversion">
                    <td>
                        <b>OS Version:</b>
                    </td>
                    <td>
                        {{::cdash.build.osversion}}
                    </td>
                </tr>

                <!-- Display Compiler information  -->
                <tr ng-if="::cdash.build.compilername">
                    <td>
                        <b>Compiler Name:</b>
                    </td>
                    <td>
                        {{::cdash.build.compilername}}
                    </td>
                </tr>

                <tr ng-if="::cdash.build.compilerversion">
                    <td>
                        <b>Compiler Version:</b>
                    </td>
                    <td>
                        {{::cdash.build.compilerversion}}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Filters -->
        <div id="labelshowfilters">
            <a id="label_showfilters" ng-click="showfilters_toggle()">
                <span ng-show="showfilters == 0">Show Filters</span>
                <span ng-show="showfilters != 0">Hide Filters</span>
            </a>
        </div>
    @endverbatim
        <ng-include src="'build/views/partials/filterdataTemplate_{{ $js_version }}.html'"></ng-include>
    @verbatim

        <div ng-switch="::cdash.display">
            <h3 ng-switch-when="onlypassed">{{::cdash.numPassed}} tests passed.</h3>
            <h3 ng-switch-when="onlyfailed">{{::cdash.numFailed}} tests failed.</h3>
            <h3 ng-switch-when="onlynotrun">{{::cdash.numNotRun}} tests not run.</h3>
            <h3 ng-switch-when="onlytimestatus">{{::cdash.numTimeFailed}} tests failed the time status check.</h3>
            <h3 ng-switch-default id="test-totals-indicator">
                {{::cdash.numPassed}} passed,
                {{::cdash.numFailed}} failed,
                <span ng-if="::cdash.project.showtesttime">
                    {{::cdash.numTimeFailed}} failed the time status check,
                </span>
                {{::cdash.numNotRun}} not run,
                {{::cdash.numMissing}} missing.
            </h3>
        </div>

        <div ng-if="::cdash.tests.length > 0">
            <!-- Hide a div for javascript to know if time status is on -->
            <div ng-if="::cdash.project.showtesttime == 1" id="showtesttimediv" style="display:none"></div>

            <table id="viewTestTable" cellspacing="0" class="tabb test-table">
                <thead>
                    <tr class="table-heading1">

                        <th ng-if="::cdash.parentBuild" width="10%" class="header" ng-click="updateOrderByFields('subprojectname', $event)">
                            SubProject
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-subprojectname') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('subprojectname') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th width="43%" class="header" ng-click="updateOrderByFields('name', $event)">
                            Name
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-name') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('name') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th width="5%" class="header"
                            ng-click="updateOrderByFields('status', $event)"
                            tooltip-popup-delay="1500"
                            tooltip-append-to-body="true"
                            uib-tooltip="Whether the test passed or failed">
                            Status
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-status') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('status') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th ng-if="::cdash.project.showtesttime == 1" width="5%" class="header"
                            ng-click="updateOrderByFields('timestatus', $event)"
                            tooltip-popup-delay="1500"
                            tooltip-append-to-body="true"
                            uib-tooltip="Whether or not the test failed the time status check">
                            Time Status
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-timestatus') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('timestatus') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th width="9%" class="header" ng-click="updateOrderByFields('execTimeFull', $event)">
                            Time
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-execTimeFull') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('execTimeFull') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th ng-if="::cdash.hasprocessors" width="9%" class="header"
                            ng-click="updateOrderByFields('procTimeFull', $event)"
                            tooltip-popup-delay="1500"
                            tooltip-append-to-body="true"
                            uib-tooltip="Elapsed time * number of processors">
                            Proc Time
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-procTimeFull') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('procTimeFull') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th ng-if="::cdash.displaydetails == 1" width="13%" class="header" ng-click="updateOrderByFields('details', $event)">
                            Details
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-details') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('details') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th ng-if="::cdash.build.displaylabels == 1" width="13%" class="header" ng-click="updateOrderByFields('label', $event)">
                            Labels
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-label') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('label') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th ng-if="::cdash.displayhistory" width="6%" class="header"
                            ng-click="updateOrderByFields('history', $event)"
                            tooltip-popup-delay='1500'
                            tooltip-append-to-body="true"
                            uib-tooltip='Test status for this build over the past four runs'>
                            History
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-history') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('history') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th width="6%" class="header"
                            ng-click="updateOrderByFields('summary', $event)"
                            tooltip-popup-delay='1500'
                            tooltip-append-to-body="true"
                            uib-tooltip='Current test status across the BuildGroup'>
                            Summary
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-summary') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('summary') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>

                        <th ng-repeat="columnname in ::cdash.columnnames"
                            class="header"
                            ng-click="sortByExtraMeasurement($index, $event)">
                            {{::columnname}}
                            <span class="glyphicon" ng-class="orderByFields.indexOf('-measurements[' + $index + ']') != -1 ? 'glyphicon-chevron-down' : (orderByFields.indexOf('measurements[' + $index + ']') != -1 ? 'glyphicon-chevron-up' : 'glyphicon-none')"></span>
                        </th>
                    </tr>
                </thead>

                <tr ng-repeat="test in pagination.filteredTests" ng-class-odd="'odd'" ng-class-even="'even'">
                    <td ng-if="::cdash.parentBuild" align="left">
                        <a ng-href="viewTest.php?buildid={{::test.buildid}}">{{::test.subprojectname}}</a>
                    </td>

                    <td>
                        <img ng-if="::test.new == 1 && test.timestatus == 'Passed' && test.status == 'Passed'"
                             src="img/flaggreen.gif" title="flag"/>
                        <img ng-if="::test.new == 1 && !(test.timestatus == 'Passed' && test.status == 'Passed')"
                             src="img/flag.png" title="flag"/>
                        <a ng-href="{{::test.detailsLink}}"
                           ng-click="cancelAjax()">{{::test.name}}</a>
                    </td>

                    <td align="center" ng-class="::test.statusclass">
                        <a ng-href="{{::test.detailsLink}}"
                           ng-click="cancelAjax()">{{::test.status}}</a>
                    </td>

                    <td ng-if="::cdash.project.showtesttime == 1"
                        align="center" ng-class="::test.timestatusclass">
                        <a ng-href="{{::test.detailsLink}}?graph=time"
                           ng-click="cancelAjax()">{{::test.timestatus}}</a>
                    </td>

                    <td align="right">
                        <span style="display:none">{{::test.execTimeFull}}</span>
                        {{::test.execTime}}
                    </td>

                    <td ng-if="::cdash.hasprocessors" align="right">
                        <span style="display:none">{{::test.procTimeFull}}</span>
                        {{::test.procTime}}
                    </td>

                    <td ng-if="::cdash.displaydetails == 1">
                        {{::test.details}}
                    </td>

                    <td ng-if="::cdash.build.displaylabels == 1" align="left">
                        <span ng-repeat="label in ::test.labels">{{::label}}</span>
                    </td>

                    <td ng-if="::cdash.displayhistory" align="center" ng-class="test.historyclass">
                        {{test.history}}
                    </td>

                    <td align="center" ng-class="test.summaryclass">
                        <a ng-href="{{test.summaryLink}}"
                           ng-click="cancelAjax()">{{test.summary}}</a>
                    </td>
                    <td ng-repeat="measurement in ::test.measurements track by $index">
                        {{::measurement}}
                    </td>
                </tr>
            </table>

            <div ng-if="cdash.tests.length > 25">
                <uib-pagination
                    ng-model="pagination.currentPage"
                    total-items="cdash.tests.length"
                    max-size="pagination.maxSize"
                    items-per-page="pagination.numPerPage"
                    ng-change="pageChanged()"
                    boundary-links="true">
                </uib-pagination>

                <div>
                    <label>Items per page</label>
                    <select name="itemsPerPage" ng-model="pagination.numPerPage" convert-to-number ng-change="numTestsPerPageChanged()">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="-1">All</option>
                    </select>
                </div>
            </div>

            <br/>
            <a ng-href="{{::cdash.csvlink}}">Download Table as CSV File</a>
        </div>
    @endverbatim
@endsection
