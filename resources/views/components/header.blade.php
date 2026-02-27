@php
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Auth;

if (isset($project)) {
    $logoid = $project->ImageId;
}

$hideRegistration = config('cdash.user_registration_form_enabled') === false;

$userInProject = false;
if (isset($project)) {
    $eloquentProject = \App\Models\Project::findOrFail((int) $project->Id);

    $currentDateString = Carbon::parse($eloquentProject->builds()->max('starttime'))->toDateString();

    $userInProject = auth()->user() !== null && $eloquentProject->users()->where('id', auth()->user()->id)->exists();
}

$showHeaderNav = isset($build);
@endphp

<div id="header">
    <div id="headertop">
        <div id="topmenu">
            <span>
                <a class="cdash-link" href="{{ url('/projects') }}">All Dashboards</a>
                @if(Auth::check())
                    <a class="cdash-link" href="{{ url('/user') }}">My CDash</a>
                @endif
            </span>

            @if(config('cdash.global_banner') !== null && strlen(config('cdash.global_banner')) > 0)
                <span id="global-banner">
                    {!! Str::inlineMarkdown(config('cdash.global_banner'), ['allow_unsafe_links' => false, 'html_input' => 'escape']) !!}
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
                @if(isset($project) && $logoid !== null)
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
            @elseif(isset($vue) && $vue === true && $showHeaderNav)
                <header-nav
                    @if($previousUrl)
                        previous-url="{{ $previousUrl }}"
                    @endif
                    @if($latestUrl)
                        latest-url="{{ $latestUrl }}"
                    @endif
                    @if($nextUrl)
                        next-url="{{ $nextUrl }}"
                    @endif
                />
            @endif
        </nav>


        <div id="headermenu">
            <ul id="navigation">
                @if(isset($project))
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
                                <a href="{{ url('/testOverview.php') }}?project={{rawurlencode($project->Name)}}&date={{$currentDateString}}">
                                    Test Overview
                                </a>
                            </li>
                            <li>
                                <a href="{{ url("/projects/$project->Id/sites") }}">
                                    Sites
                                </a>
                            </li>
                            @if(isset($project->Id) && ProjectService::getNumberOfSubProjects((int) $project->Id, request()->get('date')) > 0)
                                <li>
                                    <a href="{{ url('/viewSubProjects.php') }}?project={{ rawurlencode($project->Name) }}">
                                        SubProjects
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif
                @if(isset($angular) && $angular === true)
                    <li ng-if="cdash.showcalendar">
                        <a class="cdash-link" id="cal" href="" ng-click="toggleCalendar()">Calendar</a>
                        <span id="date_now" style="display:none;">@{{::cdash.date}}</span>
                    </li>
                @endif
                @if(isset($project))
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
                @endif
            </ul>
        </div>
    </div>

    @if(isset($angular) && $angular === true)
        <div id="calendar"></div>
    @endif
</div>
