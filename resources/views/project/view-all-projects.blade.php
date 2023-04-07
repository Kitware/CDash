@extends('cdash', [
    'angular' => true,
    'angular_controller' => 'ViewProjectsController'
])

@section('main_content')
    @verbatim
        <p ng-show="cdash.upgradewarning" style="color:red">
            <b>
                The current database schema doesn't match the version of CDash
                you are running, upgrade your database structure in the
                <a href="upgrade.php">
                    Administration/CDash maintenance panel of CDash
                </a>
            </b>
        </p>

        <h1 ng-if="cdash.projects.length == 0"
            class="text-info text-center">
            No Projects Found
        </h1>

        <!-- Main table -->
        <table ng-if="cdash.projects.length > 0"
               border="0" cellpadding="4" cellspacing="0" width="100%" id="indexTable" class="tabb">
            <thead>
            <tr class="table-heading1">
                <td colspan="6" align="left" class="nob"><h3>Dashboards</h3></td>
            </tr>

            <tr class="table-heading">
                <th align="center" id="sort_0" width="10%"><b>Project</b></th>
                <td align="center" width="65%"><b>Description</b></td>
                <th align="center" class="nob"  id="sort_2" width="13%"><b>Last activity</b></th>
            </tr>
            </thead>

            <tbody>
            <tr ng-repeat="project in cdash.projects" ng-class-odd="'odd'" ng-class-even="'even'">
                <td align="center" >
                    <a ng-href="{{project.link}}">
                        {{project.name}}
                    </a>
                </td>
                <td align="left">{{project.description}}</td>
                <td align="center" class="nob">
              <span class="sorttime" style="display:none">
                {{project.lastbuilddatefull}}
              </span>
                    <a class="builddateelapsed" ng-alt="{{project.lastbuild}}" ng-href="{{project.link}}&date={{project.lastbuilddate}}">
                        {{project.lastbuild_elapsed}}
                    </a>
                    <img src="img/cleardot.gif" ng-class="'activity-level-{{project.activity}}'"/>
                </td>
            </tr>
            </tbody>
        </table>

        <table ng-if="cdash.projects.length > 0"
               width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td height="1" colspan="14" align="left" bgcolor="#888888"></td>
            </tr>
            <tr>
                <td height="1" colspan="14" align="right">
                    <div ng-if="cdash.showoldtoggle" id="showold">
                        <a ng-show="!cdash.allprojects" href="viewProjects.php?allprojects=1">
                            Show all {{cdash.nprojects}} projects
                        </a>
                        <a ng-show="cdash.allprojects" href="viewProjects.php">
                            Hide old projects
                        </a>
                    </div>
                </td>
            </tr>
        </table>
    @endverbatim
@endsection
