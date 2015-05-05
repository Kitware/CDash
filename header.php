<!--
<xsl:include href="headscripts.xsl"/>
-->

<div id="header">
  <div id="headertop">
    <div id="topmenu">
      <a href="user.php">
        <span ng-if="cdash.userid > 0">
          My CDash
        </span>
        <span ng-if="!cdash.userid || cdash.userid == 0">
          Login
        </span>
      </a>
      <a href="index.php">All Dashboards</a>
      <a href="user.php?logout=1" ng-if="cdash.userid > 0">Log Out</a>
    </div>

    <div id="datetime"> {{cdash.datetime}} </div>
    <div id="feedicon" alt="RSS Feed" title="RSS Feed">
      <a ng-if="cdash.projectpublic==1" href="rss/SubmissionRSS{{cdash.projectname}}.xml">
        <img src="images/feed-icon16x16.png" alt="RSS" width="14" height="14" border="0" />
      </a>
    </div>
  </div>

  <div id="headerbottom">
    <div id="headerlogo">
      <a href="{{cdash.home}}">
        <img ng-if="cdash.logoid>0" id="projectlogo" border="0" height="50px" src="displayImage.php?imgid={{cdash.logoid}}"/>
        <img ng-if="! cdash.logoid || cdash.logoid==0" id="projectlogo" border="0" height="50px" src="images/cdash.gif"/>
      </a>
    </div>
    <div id="headername">
      {{cdash.projectname}}
      <span ng-if="cdash.subheadername" id="subheadername">
        {{cdash.subprojectname}}
      </span>
    </div>
    <div id="headermenu">
      <ul id="navigation">
        <li>
          <a href="index.php?project={{cdash.projectname_encoded}}&date={{cdash.date}}">
            Dashboard
          </a>
          <ul>
            <li>
              <a href="overview.php?project={{cdash.projectname_encoded}}&date={{cdash.date}}">
                Overview
              </a>
            </li>
            <li>
              <a href="viewChanges.php?project={{cdash.projectname_encoded}}&date={{cdash.date}}{{cdash.extraurl}}">
                Updates
              </a>
            </li>
            <li>
              <a href="buildOverview.php?project={{cdash.projectname_encoded}}&date={{cdash.date}}{{cdash.extraurl}}">
                Builds
              </a>
            </li>
            <li>
              <a href="testOverview.php?project={{cdash.projectname_encoded}}&date={{cdash.date}}{{cdash.extraurl}}">
                Tests
              </a>
            </li>
            <li>
              <a href="queryTests.php?project={{cdash.projectname_encoded}}&date={{cdash.date}}&limit=200{{cdash.extraurl}}">
                Tests Query
              </a>
            </li>
            <li>
              <a href="userStatistics.php?projectid={{cdash.projectid}}">
                Statistics
              </a>
            </li>
            <li class="endsubmenu">
              <a href="viewMap.php?project={{cdash.projectname_encoded}}&date={{cdash.date}}{{cdash.extraurl}}">
                Sites
              </a>
            </li>
          </ul>
        </li>
        <li id="Back" ng-if="cdash.menu.back">
          <a href="{{cdash.menu.back}}">Back</a>
        </li>
        <li>
          <a id="cal" href="#">Calendar</a>
          <span id="date_now" style="display:none;">{{cdash.date}}</span>
        </li>

        <li>
          <a ng-if="cdash.previous" href="{{cdash.menu.previous}}">Previous</a>
          <a ng-if="cdash.noprevious" href="index.php?project={{cdash.projectname_encoded}}&date={{cdash.previousdate}}{{cdash.extraurl}}">
            Previous
          </a>
        </li>
        <li>
          <a ng-if="cdash.menu.current" href="{{cdash.menu.current}}">
            Current
          </a>
          <a ng-if="!cdash.menu.current" href="index.php?project={{cdash.projectname_encoded}}{{cdash.extraurl}}">
            Current
          </a>
        </li>
        <li ng-if="!cdash.menu.nonext">
          <a ng-if="cdash.menu.next" href="{{cdash.menu.next}}">
            Next
          </a>
          <a ng-if="!cdash.menu.next" href="index.php?project={{cdash.projectname_encoded}}&date={{cdash.nextdate}}{{cdash.extraurl}}">
            Next
          </a>
        </li>
        <li>
          <a href="#">Project</a>
          <ul>
            <li>
              <a href="{{cdash.home}}">Home</a>
            </li>
            <li>
              <a href="{{cdash.documentation}}">Documentation</a>
            </li>
            <li>
              <a href="{{cdash.svn}}">Repository</a>
            </li>
            <li ng-class="{endsubmenu: cdash.projectrole}">
              <a href="{{cdash.bugtracker}}">Bug Tracker</a>
            </li>
            <li ng-if="!cdash.projectrole" class="endsubmenu">
              <a href="subscribeProject.php?projectid={{cdash.projectid}}">Subscribe</a>
            </li>
          </ul>
        </li>
        <li ng-if="cdash.admin" id="admin">
          <a href="#">Settings</a>
          <ul>
            <li>
              <a href="createProject.php?edit=1&projectid={{cdash.projectid}}">
                Project
              </a>
            </li>
            <li>
              <a href="manageProjectRoles.php?projectid={{cdash.projectid}}">
                Users
              </a>
            </li>
            <li>
              <a href="manageBuildGroup.php?projectid={{cdash.projectid}}">
                Groups
              </a>
            </li>
            <li>
              <a href="manageCoverage.php?projectid={{cdash.projectid}}">
                Coverage
              </a>
            </li>
            <li>
              <a href="manageBanner.php?projectid={{cdash.projectid}}">
                Banner
              </a>
            </li>
            <li>
              <a href="manageMeasurements.php?projectid={{cdash.projectid}}">
                Measurements
              </a>
            </li>
            <li>
              <a href="manageSubProject.php?projectid={{cdash.projectid}}">
                SubProjects
              </a>
            </li>
            <li class="endsubmenu">
              <a href="manageOverview.php?projectid={{cdash.projectid}}">
                Overview
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</div>

<input type="hidden" id="projectname" value="{{cdash.projectname_encoded}}">
</input>

<input type="hidden" id="projectid" value="{{cdash.projectid}}">
</input>

<div id="calendar" class="ui-datepicker-calendar" ></div>
