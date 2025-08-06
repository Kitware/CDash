@php
if (isset($project)) {
    $logoid = $project->ImageId;
}

$hideRegistration = config('auth.user_registration_form_enabled') === false;

$currentDateString = now()->toDateString();

$userInProject = isset($project) && auth()->user() !== null && \App\Models\Project::findOrFail($project->Id)->users()->where('id', auth()->user()->id)->exists();
@endphp

<div id="header">
    <div id="headertop">
        <div id="topmenu" style="display: flex; justify-content: space-between;">
            <span>
                <a class="cdash-link" href="{{ url('/projects') }}">All Dashboards</a>
                @if(Auth::check())
                    <a class="cdash-link" href="{{ url('/user') }}">My CDash</a>
                @endif
            </span>

            @if(config('cdash.global_banner') !== null && strlen(config('cdash.global_banner')) > 0)
                <span id="global-banner" style="color: #2ee84a;">
                    {{ config('cdash.global_banner') }}
                </span>
            @endif

            <span>
                @if(Auth::check())
                    <a class="cdash-link" href="{{ url('/logout') }}">Logout</a>
                @else
                    <a class="cdash-link" href="{{ url('/login') }}">Login</a>
                    @if(!$hideRegistration)
                      <a class="cdash-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                    @endif
                @endif
            </span>
        </div>
    </div>
    <div id="headerbottom">
        <div id="headerlogo">
            <a
                @if(isset($project))
                    href="{{ url('/index.php') }}?project={{ rawurlencode($project->Name) }}"
                @else
                    href="{{ url('/')}}"
                @endif
            >
                @if(isset($project) && $logoid > 0)
                    <img id="projectlogo" height="50px" alt="" src="{{ url('/image/' . $logoid) }}" />
                @else
                    <img id="projectlogo" height="50px" alt="" src="{{ asset('img/cdash.svg') }}" />
                @endif
            </a>
        </div>

        <div id="headername2">
            <span id="subheadername">
                {{ $title }}
            </span>
        </div>

        <nav class="projectnav clearfix">
            @if(isset($angular) && $angular === true)
                @verbatim
                    <ul ng-if="cdash.menu.previous || cdash.menu.current || cdash.menu.next" class="projectnav_controls clearfix">
                        <li class="btnprev">
                            <a class="cdash-link" ng-if="cdash.menu.previous"
                               ng-href="{{::cdash.menu.previous}}{{::cdash.filterurl}}">Prev</a>
                        </li>
                        <li class="btncurr">
                            <a class="cdash-link" ng-if="cdash.menu.current"
                               ng-href="{{::cdash.menu.current}}{{::cdash.filterurl}}">
                                Latest
                            </a>
                        </li>
                        <li class="btnnext">
                            <a class="cdash-link" ng-if="cdash.menu.next"
                               ng-href="{{::cdash.menu.next}}{{::cdash.filterurl}}">
                                Next
                            </a>
                        </li>
                    </ul>
                @endverbatim
            @elseif(isset($vue) && $vue === true)
                <header-nav></header-nav>
            @endif
        </nav>


        @if(isset($angular) && $angular === true)
            <div id="headermenu" style="float: right;">
                <ul id="navigation">
                    <li ng-if="!cdash.noproject && cdash.projectname_encoded !== undefined">
                        <a class="cdash-link" ng-href="{{ url('/index.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}">
                            Dashboard
                        </a>
                        <ul>
                            <li ng-if="cdash.menu.subprojects == 1">
                                <a class="cdash-link" ng-href="{{ url('/viewSubProjects.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}">
                                    SubProjects
                                </a>
                            </li>
                            <li>
                                <a class="cdash-link" ng-href="{{ url('/overview.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}">
                                    Overview
                                </a>
                            </li>
                            <li>
                                <a class="cdash-link" ng-href="{{ url('/buildOverview.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}@{{::cdash.extraurl}}">
                                    Builds
                                </a>
                            </li>
                            <li>
                                <a class="cdash-link" ng-href="{{ url('/testOverview.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}@{{::cdash.extraurl}}">
                                    Tests
                                </a>
                            </li>
                            <li>
                                <a class="cdash-link" ng-if="!cdash.parentid || cdash.parentid <= 0"
                                   ng-href="{{ url('/queryTests.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}@{{::cdash.extraurl}}@{{::cdash.querytestfilters}}">
                                    Tests Query
                                </a>
                                <a class="cdash-link" ng-if="cdash.parentid > 0"
                                   ng-href="{{ url('/queryTests.php') }}?project=@{{::cdash.projectname_encoded}}&parentid=@{{::cdash.parentid}}@{{::cdash.extraurl}}@{{::cdash.extrafilterurl}}">
                                    Tests Query
                                </a>
                            </li>
                            <li class="endsubmenu">
                                <a class="cdash-link" ng-href="{{ url('/projects') }}/@{{::cdash.projectid}}/sites@{{::cdash.extraurl}}">
                                    Sites
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li id="Back" ng-if="cdash.menu.back">
                        <a class="cdash-link"
                           ng-href="@{{::cdash.menu.back}}@{{::cdash.extrafilterurl}}"
                           tooltip-popup-delay="1500"
                           tooltip-append-to-body="true"
                           tooltip-placement="bottom"
                           uib-tooltip="Go back up one level in the hierarchy of results">Up</a>
                    </li>
                    <li ng-if="cdash.showcalendar">
                        <a class="cdash-link" id="cal" href="" ng-click="toggleCalendar()">Calendar</a>
                        <span id="date_now" style="display:none;">@{{::cdash.date}}</span>
                    </li>
                    <li ng-if="!cdash.hidenav && cdash.projectname_encoded !== undefined">
                        <a class="cdash-link" href="#">Project</a>
                        <ul>
                            <li>
                                <a class="cdash-link" ng-href="@{{::cdash.home}}">Home</a>
                            </li>
                            <li ng-if="cdash.documentation.replace('https://', '').replace('http://', '').trim() !== ''">
                                <a class="cdash-link" ng-href="@{{::cdash.documentation}}">Documentation</a>
                            </li>
                            <li ng-if="cdash.vcs.replace('https://', '').replace('http://', '').trim() !== ''">
                                <a class="cdash-link" ng-href="@{{::cdash.vcs}}">Repository</a>
                            </li>
                            <li ng-if="cdash.bugtracker.replace('https://', '').replace('http://', '').trim() !== ''"
                                ng-class="::{endsubmenu: cdash.projectrole}">
                                <a class="cdash-link" ng-href="@{{::cdash.bugtracker}}"> Bug Tracker</a>
                            </li>
                            <li class="endsubmenu">
                                <a class="cdash-link" ng-href="{{ url('/projects') }}/@{{::cdash.projectid}}/members">Members</a>
                            </li>
                            @if($userInProject)
                                <li class="endsubmenu">
                                    <a class="cdash-link" ng-href="{{ url('/subscribeProject.php') }}?projectid=@{{::cdash.projectid}}">Notifications</a>
                                </li>
                            @endif
                        </ul>
                    </li>
                    <li ng-if="cdash.user.admin == 1 && !cdash.noproject && cdash.projectid !== undefined" id="admin">
                        <a class="cdash-link" href="#">Settings</a>
                        <ul>
                            <li>
                                <a class="cdash-link" ng-href="{{ url('/project') }}/@{{::cdash.projectid}}/edit">
                                    Project
                                </a>
                            </li>
                            <li>
                                <a class="cdash-link" ng-href="{{ url('/manageBuildGroup.php') }}?projectid=@{{::cdash.projectid}}">
                                    Groups
                                </a>
                            </li>
                            <li>
                                <a class="cdash-link" ng-href="{{ url('/project') }}/@{{::cdash.projectid}}/testmeasurements">
                                    Measurements
                                </a>
                            </li>
                            <li>
                                <a class="cdash-link" ng-href="{{ url('/manageSubProject.php') }}?projectid=@{{::cdash.projectid}}">
                                    SubProjects
                                </a>
                            </li>
                            <li class="endsubmenu">
                                <a class="cdash-link" ng-href="{{ url('/manageOverview.php') }}?projectid=@{{::cdash.projectid}}">
                                    Overview
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        @elseif(isset($project))
            <div id="headermenu">
                <ul id="navigation">
                    <li>
                        <a href="#">Dashboard</a>
                        <ul>
                            <li>
                                <a href="{{ url('/overview.php') }}?project={{ rawurlencode($project->Name) }}&date={{$currentDateString}}">
                                    Overview
                                </a>
                            </li>
                            <li>
                                <a href="{{ url('/index.php') }}?project={{ rawurlencode($project->Name) }}&date={{$currentDateString}}">
                                    Builds
                                </a>
                            </li>
                            <li>
                                <!-- This only excludes passing tests for performance reasons. TODO: show all tests. -->
                                <a href="{{ url('/queryTests.php') }}?project={{rawurlencode($project->Name)}}&date={{$currentDateString}}&filtercount=1&showfilters=1&field1=status&compare1=62&value1=passed">
                                    Tests
                                </a>
                            </li>
                            <li>
                                <a href="{{ url("/projects/$project->Id/sites") }}">
                                    Sites
                                </a>
                            </li>
                            @if(isset($project->Id) && $project->GetNumberOfSubProjects(request()->get('date')) > 0)
                                <li>
                                    <a href="{{ url('/viewSubProjects.php') }}?project={{ rawurlencode($project->Name) }}">
                                        SubProjects
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                    <li>
                        <a href="#">Project</a>
                        <ul>
                            @if(isset($project->HomeUrl) && strlen($project->HomeUrl) > 0)
                                <li>
                                    <a href="{{ $project->HomeUrl }}">
                                        Home
                                    </a>
                                </li>
                            @endif
                            @if(isset($project->DocumentationUrl) && strlen($project->DocumentationUrl) > 0)
                                <li>
                                    <a href="{{ $project->DocumentationUrl }}">
                                        Documentation
                                    </a>
                                </li>
                            @endif
                            @if(isset($project->CvsUrl) && strlen($project->CvsUrl) > 0)
                                <li>
                                    <a href="{{ $project->CvsUrl }}">
                                        Repository
                                    </a>
                                </li>
                            @endif
                            @if(isset($project->BugTrackerUrl) && strlen($project->BugTrackerUrl) > 0)
                                <li>
                                    <a href="{{ $project->BugTrackerUrl }}">
                                        Bug Tracker
                                    </a>
                                </li>
                            @endif
                            @if(isset($project))
                                <li>
                                    <a href="{{ url("/projects/{$project->Id}/members") }}">
                                        Users
                                    </a>
                                </li>
                            @endif
                            @if($userInProject)
                                <li>
                                    <a href="{{ url('/subscribeProject.php') }}?projectid={{ $project->Id }}">
                                        Notifications
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                    @can('edit-project', $project)
                        <li>
                            <a href="#">Settings</a>
                            <ul>
                                <li>
                                    <a href="{{ url('/project') }}/{{ $project->Id }}/edit">
                                        Project
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('/manageBuildGroup.php') }}?projectid={{ $project->Id }}">
                                        Groups
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('/project') }}/{{ $project->Id }}/testmeasurements">
                                        Measurements
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('/manageSubProject.php') }}?projectid={{ $project->Id }}">
                                        SubProjects
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('/manageOverview.php') }}?projectid={{ $project->Id }}">
                                        Overview
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endcan
                </ul>
            </div>
        @endif
    </div>

    @if(isset($angular) && $angular === true)
        <div id="calendar"></div>
    @endif
</div>
