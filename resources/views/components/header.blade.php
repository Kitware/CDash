@php
    if (isset($project)) {
        $logoid = getLogoID(intval($project->Id));
    }
@endphp

<div id="header">
    <div id="headertop">
        <div id="topmenu">
            <a href="{{ url('/viewProjects.php') }}">All Dashboards</a>
            @if(Auth::check())
                <a href="{{ url('/user.php') }}">My CDash</a>
            @endif

            <span style="float: right;">
                @if(Auth::check())
                    <a href="{{ url('/logout') }}">Logout</a>
                @else
                    <a href="{{ url('/login') }}">Login</a>
                    <a href="{{ route('register') }}">{{ __('Register') }}</a>
                @endif
            </span>
        </div>
    </div>
    <div id="headerbottom">
        <div id="headerlogo">
            <a
                @if(isset($home_url))
                    href="{{ $home_url }}"
                @elseif(isset($angular) && $angular === true)
                    ng-href="@{{::cdash.home}}"
                @elseif(isset($project))
                    href="{{ url('/index.php') }}?project={{ rawurlencode($project->Name) }}"
                @else
                    href="{{ url('/')}}"
                @endif
            >
                {{-- TODO: (williamjallen) refactor this to always render the image URL with Blade --}}
                @if(isset($project) && $logoid > 0)
                    <img id="projectlogo" height="50px" alt="" src="{{ url('/displayImage.php') }}?imgid={{ $logoid }}" />
                @elseif(isset($angular) && $angular === true)
                    <img ng-if="cdash.logoid != 0" id="projectlogo" border="0" height="50px" ng-src="{{ url('/displayImage.php') }}?imgid=@{{::cdash.logoid}}"/>
                    <img ng-if="!cdash.logoid || cdash.logoid==0" id="projectlogo" border="0" height="50px" src="{{ url('img/cdash.png?rev=2019-05-08') }}"/>
                @elseif(isset($vue) && $vue === true)
                    <header-logo></header-logo>
                @else
                    <img id="projectlogo" height="50px" alt="" src="{{ asset('img/cdash.png') }}" />
                @endif
            </a>
        </div>

        <div id="headername2">
            <span id="subheadername">
                @if(isset($title))
                    @if(isset($project))
                        {{ $project->Name }} -
                    @endif
                    {{ $title }}
                @elseif(isset($angular) && $angular === true)
                    @{{title}}
                @endif
            </span>
        </div>


        @if(isset($angular) && $angular === true)
            @verbatim
                <div ng-if="cdash.menu.previous || cdash.menu.current || cdash.menu.next" class="projectnav clearfix">
                    <ul class="projectnav_controls clearfix">
                        <li class="btnprev">
                            <a ng-if="cdash.menu.previous"
                               ng-href="{{::cdash.menu.previous}}{{::cdash.filterurl}}">Prev</a>
                        </li>
                        <li class="btncurr">
                            <a ng-if="cdash.menu.current"
                               ng-href="{{::cdash.menu.current}}{{::cdash.filterurl}}">
                                Current
                            </a>
                        </li>
                        <li class="btnnext">
                            <a ng-if="cdash.menu.next"
                               ng-href="{{::cdash.menu.next}}{{::cdash.filterurl}}">
                                Next
                            </a>
                        </li>
                    </ul>
                </div>

            @endverbatim
        @elseif(isset($vue) && $vue === true)
            <header-nav></header-nav>
        @endif


        @if(isset($angular) && $angular === true)
            <div id="headermenu" style="float: right;">
                <ul id="navigation">
                    <li ng-if="!cdash.noproject && cdash.projectname_encoded !== undefined">
                        <a ng-href="{{ url('/index.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}">
                            Dashboard
                        </a>
                        <ul>
                            <li ng-if="cdash.menu.subprojects == 1">
                                <a ng-href="{{ url('/viewSubProjects.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}">
                                    SubProjects
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/overview.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}">
                                    Overview
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/buildOverview.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}@{{::cdash.extraurl}}">
                                    Builds
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/testOverview.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}@{{::cdash.extraurl}}">
                                    Tests
                                </a>
                            </li>
                            <li>
                                <a ng-if="!cdash.parentid || cdash.parentid <= 0"
                                   ng-href="{{ url('/queryTests.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}@{{::cdash.extraurl}}@{{::cdash.querytestfilters}}">
                                    Tests Query
                                </a>
                                <a ng-if="cdash.parentid > 0"
                                   ng-href="{{ url('/queryTests.php') }}?project=@{{::cdash.projectname_encoded}}&parentid=@{{::cdash.parentid}}@{{::cdash.extraurl}}@{{::cdash.extrafilterurl}}">
                                    Tests Query
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/userStatistics.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}">
                                    Statistics
                                </a>
                            </li>
                            <li class="endsubmenu">
                                <a ng-href="{{ url('/viewMap.php') }}?project=@{{::cdash.projectname_encoded}}&date=@{{::cdash.date}}@{{::cdash.extraurl}}">
                                    Sites
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li id="Back" ng-if="cdash.menu.back">
                        <a ng-href="@{{::cdash.menu.back}}@{{::cdash.extrafilterurl}}"
                           tooltip-popup-delay="1500"
                           tooltip-append-to-body="true"
                           tooltip-placement="bottom"
                           uib-tooltip="Go back up one level in the hierarchy of results">Up</a>
                    </li>
                    <li ng-if="cdash.showcalendar">
                        <a id="cal" href="" ng-click="toggleCalendar()">Calendar</a>
                        <span id="date_now" style="display:none;">@{{::cdash.date}}</span>
                    </li>
                    <li ng-if="!cdash.hidenav && cdash.projectname_encoded !== undefined">
                        <a href="#">Project</a>
                        <ul>
                            <li>
                                <a ng-href="@{{::cdash.home}}">Home</a>
                            </li>
                            <li ng-if="cdash.documentation.replace('https://', '').replace('http://', '').trim() !== ''">
                                <a ng-href="@{{::cdash.documentation}}">Documentation</a>
                            </li>
                            <li ng-if="cdash.vcs.replace('https://', '').replace('http://', '').trim() !== ''">
                                <a ng-href="@{{::cdash.vcs}}">Repository</a>
                            </li>
                            <li ng-if="cdash.bugtracker.replace('https://', '').replace('http://', '').trim() !== ''"
                                ng-class="::{endsubmenu: cdash.projectrole}">
                                <a ng-href="@{{::cdash.bugtracker}}"> Bug Tracker</a>
                            </li>
                            <li ng-if="!cdash.projectrole" class="endsubmenu">
                                <a ng-href="{{ url('/subscribeProject.php') }}?projectid=@{{::cdash.projectid}}">Subscribe</a>
                            </li>
                        </ul>
                    </li>
                    <li ng-if="cdash.user.admin == 1 && !cdash.noproject && cdash.projectid !== undefined" id="admin">
                        <a href="#">Settings</a>
                        <ul>
                            <li>
                                <a ng-href="{{ url('/project') }}/@{{::cdash.projectid}}/edit">
                                    Project
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/manageProjectRoles.php') }}?projectid=@{{::cdash.projectid}}">
                                    Users
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/manageBuildGroup.php') }}?projectid=@{{::cdash.projectid}}">
                                    Groups
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/manageCoverage.php') }}?projectid=@{{::cdash.projectid}}">
                                    Coverage
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/manageBanner.php') }}?projectid=@{{::cdash.projectid}}">
                                    Banner
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/project') }}/@{{::cdash.projectid}}/testmeasurements">
                                    Measurements
                                </a>
                            </li>
                            <li>
                                <a ng-href="{{ url('/manageSubProject.php') }}?projectid=@{{::cdash.projectid}}">
                                    SubProjects
                                </a>
                            </li>
                            <li class="endsubmenu">
                                <a ng-href="{{ url('/manageOverview.php') }}?projectid=@{{::cdash.projectid}}">
                                    Overview
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        @elseif(isset($vue) && $vue === true)
            <header-menu></header-menu>
        @elseif(isset($project)) {{-- Some XSL pages have an admin menu --}}
            <div id="headermenu">
                <ul id="navigation">
                    <li id="admin">
                        <a href="#">Settings</a><ul>
                            <li><a href="{{ url('/project') }}/{{ $project->Id }}/edit">Project</a></li>
                            <li><a href="{{ url('/manageProjectRoles.php') }}?projectid={{ $project->Id }}">Users</a></li>
                            <li><a href="{{ url('/manageBuildGroup.php') }}?projectid={{ $project->Id }}">Groups</a></li>
                            <li><a href="{{ url('/manageCoverage.php') }}?projectid={{ $project->Id }}">Coverage</a></li>
                            <li><a href="{{ url('/manageBanner.php') }}?projectid={{ $project->Id }}">Banner</a></li>
                            <li><a href="{{ url('/project') }}/{{ $project->Id }}/testmeasurements">Measurements</a></li>
                            <li><a href="{{ url('/manageSubProject.php') }}?projectid={{ $project->Id }}">SubProjects</a></li>
                            <li class="endsubmenu"><a href="{{ url('/manageOverview.php') }}?projectid={{ $project->Id }}">Overview</a></li>
                        </ul>
                    </li>
                    <li id="Dashboard">
                        <a href="{{ url('/index.php') }}?project={{ rawurlencode($project->Name) }}">Dashboard</a>
                    </li>
                </ul>
            </div>
        @endif
    </div>

    @if(isset($angular) && $angular === true)
        <div id="calendar"></div>
    @endif
</div>
