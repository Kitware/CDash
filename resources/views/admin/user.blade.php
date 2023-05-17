@extends('cdash', [
    'angular' => true,
    'angular_controller' => 'UserController'
])

@section('main_content')
    @verbatim
        <!-- Message -->
        <table ng-if="::cdash.message">
            <tr>
                <td width="95"><div align="right"></div></td>
                <td>
                    <div style="color: green;">{{::cdash.message}}</div>
                </td>
            </tr>
        </table>

        <!-- My Projects -->
        <div ng-if="::cdash.projects.length > 0">
            <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
                <tbody>
                <tr class="table-heading1">
                    <td colspan="7" id="nob"><h3>My Projects</h3></td>
                </tr>
                <tr class="table-heading">
                    <td align="center" width="100px" class="botl">Project Name</td>
                    <td align="center" width="240px" class="botl">Actions</td>
                    <td align="center" width="130px" class="botl">Builds</td>
                    <td align="center" width="130px" class="botl">Builds per day</td>
                    <td align="center" width="130px" class="botl">Success Last 24h</td>
                    <td align="center" width="130px" class="botl">Errors Last 24h</td>
                    <td align="center" width="130px" class="botl">Warnings Last 24h</td>
                </tr>
                <tr class="table-heading" ng-repeat="project in ::cdash.projects">
                    <td align="center" >
                        <a ng-href="index.php?project={{::project.name_encoded}}">{{::project.name}}</a>
                    </td>
                    <td align="center" bgcolor="#DDDDDD">
                        <a title="Edit subscription"
                           ng-href="subscribeProject.php?projectid={{::project.id}}&edit=1">
                            <img src="img/edit.png" border="0" alt="subscribe"></img>
                        </a>
                        <a title="Claim sites"
                           ng-if="::project.role > 0"
                           ng-href="editSite.php?projectid={{::project.id}}">
                            <img src="img/systemtray.png" border="0" alt="claimsite" />
                        </a>
                        <a title="Edit project"
                           ng-if="::project.role > 1"
                           ng-href="project/{{::project.id}}/edit">
                            <img src="img/edit2.png" border="0" alt="editproject" />
                        </a>
                        <a title="Manage subprojects"
                           ng-if="::project.role > 1"
                           ng-href="manageSubProject.php?projectid={{::project.id}}">
                            <img src="img/subproject.png" border="0" alt="subproject" />
                        </a>
                        <a title="Manage project groups"
                           ng-if="::project.role > 1"
                           ng-href="manageBuildGroup.php?projectid={{::project.id}}">
                            <img src="img/edit_group.png" border="0" alt="managegroups" />
                        </a>
                        <a title="Manage project users"
                           ng-if="::project.role > 1"
                           ng-href="manageProjectRoles.php?projectid={{::project.id}}">
                            <img src="img/users.png" border="0" alt="manageusers" />
                        </a>
                        <a title="Manage project coverage"
                           ng-if="::project.role > 1"
                           ng-href="manageCoverage.php?projectid={{::project.id}}">
                            <img src="img/filecoverage.png" border="0" alt="managecoverage" />
                        </a>
                    </td>
                    <td align="center"  bgcolor="#DDDDDD">
                        {{::project.nbuilds}}
                    </td>
                    <td align="center"  bgcolor="#DDDDDD">
                        {{::project.average_builds}}
                    </td>
                    <td align="center"  bgcolor="#DDDDDD"
                        ng-class="::{'normal': project.success > 0}">
                        {{::project.success}}
                    </td>
                    <td align="center"  bgcolor="#DDDDDD"
                        ng-class="::{'error': project.error > 0}">
                        {{::project.error}}
                    </td>
                    <td align="center"  bgcolor="#DDDDDD"
                        ng-class="::{'warning': project.warning > 0}">
                        {{::project.warning}}
                    </td>
                </tr>
                </tbody>
            </table>
            <br/>
        </div>

        <!-- My Sites -->
        <div ng-if="::cdash.claimedsites.length > 0">
            <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
                <tbody>
                <tr class="table-heading1">
                    <td colspan="10" id="nob"><h3>My Sites</h3></td>
                </tr>
                <!-- header of the matrix -->
                <tr class="table-heading">
                    <td align="center"><b>Site</b></td>
                    <td align="center" id="nob"
                        ng-repeat="project in ::cdash.claimedsiteprojects">
                        <a ng-href="index.php?project={{::project.name_encoded}}">
                            {{::project.name}}
                        </a>
                    </td>
                </tr>
                <!-- Fill in the information -->
                <tr class="treven" ng-repeat="site in ::cdash.claimedsites">
                    <td align="center">
                        <a ng-href="editSite.php?siteid={{::site.id}}">
                            {{::site.name}}
                        </a>
                        <img border="0" src="img/flag.png" title="flag"
                             ng-if="::site.outoforder == 1"></img>
                    </td>
                    <td align="center" id="nob" ng-repeat="project in ::site.projects">
                        <table width="100%" border="0">
                            <tr class="table-heading" ng-if="::project.nightly.NA == 0">
                                <td align="center"><b>N</b></td>
                                <td align="center" id="nob" ng-class="::project.nightly.updateclass">
                                    {{::project.nightly.update}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.nightly.configureclass">
                                    {{::project.nightly.configure}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.nightly.errorclass">
                                    {{::project.nightly.error}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.nightly.testfailclass">
                                    {{::project.nightly.testfail}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.nightly.dateclass">
                                    {{::project.nightly.date}}
                                </td>
                            </tr>
                            <tr class="table-heading" ng-if="::project.continuous.NA == 0">
                                <td align="center"><b>C</b></td>
                                <td align="center" id="nob" ng-class="::project.continuous.updateclass">
                                    {{::project.continuous.update}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.continuous.configureclass">
                                    {{::project.continuous.configure}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.continuous.errorclass">
                                    {{::project.continuous.error}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.continuous.testfailclass">
                                    {{::project.continuous.testfail}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.continuous.dateclass">
                                    {{::project.continuous.date}}
                                </td>
                            </tr>
                            <tr class="table-heading" ng-if="::project.experimental.NA == 0">
                                <td align="center"><b>E</b></td>
                                <td align="center" id="nob" ng-class="::project.experimental.updateclass">
                                    {{::project.experimental.update}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.experimental.configureclass">
                                    {{::project.experimental.configure}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.experimental.errorclass">
                                    {{::project.experimental.error}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.experimental.testfailclass">
                                    {{::project.experimental.testfail}}
                                </td>
                                <td align="center" id="nob" ng-class="::project.experimental.dateclass">
                                    {{::project.experimental.date}}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                </tbody>
            </table>
            <br/>
        </div>

        <!-- Public Project -->
        <div ng-if="::cdash.publicprojects.length > 0">
            <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
                <tbody>
                <tr class="table-heading1">
                    <td colspan="3" id="nob"><h3>Public projects</h3></td>
                </tr>
                <tr class="table-heading"
                    ng-repeat="project in ::cdash.publicprojects"
                    ng-class-odd="'trodd'" ng-class-even="'treven'">
                    <td align="center"  id="nob">
                        <b>{{::project.name}}</b>
                    </td>
                    <td id="nob">
                        <a ng-href="subscribeProject.php?projectid={{::project.id}}">
                            Subscribe to this project
                        </a>
                    </td>
                </tr>
                </tbody>
            </table>
            <br/>
        </div>

        <div>
            <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
                <tbody>
                <tr class="table-heading1">
                    <td colspan="4" id="nob">
                        <h3>My Authentication Tokens</h3>
                    </td>
                </tr>
                <tr class="table-heading"
                    ng-if-start="cdash.authtokens.length > 0">
                    <td align="center" class="botl">Description</td>
                    <td align="center" class="botl">Scope</td>
                    <td align="center" class="botl">Expires</td>
                    <td align="center" class="botl">Revoke</td>
                </tr>
                <tr
                    ng-repeat-start="authtoken in cdash.authtokens"
                    ng-class-odd="'trodd'" ng-class-even="'treven'">
                    <td align="center">{{::authtoken.description}}</td>
                    <td align="center" ng-if="::authtoken.scope !== 'submit_only'">
                        Full Access
                    </td>
                    <td align="center" ng-if="authtoken.scope === 'submit_only'">
                        Submit Only{{::authtoken.projectname !== null && authtoken.projectname.length > 0 ? ' (' + authtoken.projectname + ')' : ''}}
                    </td>
                    <td align="center">{{::authtoken.expires}}</td>
                    <td align="center">
                <span class="glyphicon glyphicon-trash"
                      ng-click="revokeToken(authtoken)"
                      tooltip-popup-delay="1500"
                      tooltip-append-to-body="true"
                      uib-tooltip="Revoke this token" />
                    </td>
                </tr>
                <tr ng-if="authtoken.raw_token !== undefined"
                    ng-repeat-end>
                    <td align="center">
                        Token for <strong>{{authtoken.description}}</strong>: <code>{{authtoken.raw_token}}</code>
                    </td>
                    <td align="center" colspan="2">
                        <strong ng-show="!authtoken.copied" class="animate-show">
                            Copy this token. It cannot be retrieved later if you leave this page!
                        </strong>
                    </td>
                    <td align="center">
                        <button class="btn btn-default"
                                clipboard text="authtoken.raw_token"
                                on-copied="copyTokenSuccess(authtoken)">Copy</button>
                        <span class="glyphicon"
                              ng-class="authtoken.showcheck ? 'glyphicon-ok' : 'glyphicon-none'"></span>
                    </td>
                </tr>
                <tr ng-if-end></tr>
                <tr class="bordertop">
                    <td id="tokenDescriptionCell">
                        <label for="tokenDescription" id="tokenDescriptionlabel">New Token:</label>
                        <input type="text" id="tokenDescription" name="tokenDescription" class="form-control"
                               placeholder="Description (site, project, etc)"
                               ng-model="cdash.tokendescription" />
                    </td>
                    <td align="center">
                        <select ng-model="cdash.tokenscope" class="form-select">
                            <option selected="selected" value="full_access" ng-if="cdash.allow_full_access_tokens">
                                Full Access
                            </option>
                            <option value="submit_only" ng-if="cdash.allow_submit_only_tokens">All Projects (Submit Only)</option>
                            <option ng-repeat="project in ::cdash.projects" value="{{::project.id}}">{{::project.name}} (Submit Only)</option>
                        </select>
                    </td>
                    <td></td>
                    <td align="center">
                        <button class="btn btn-default"
                                ng-click="generateToken()">Generate Token</button>
                    </td>
                </tr>
                </tbody>
            </table>
            <br/>
        </div>

        <!-- If we allow user to create new projects -->
        <div ng-if="::cdash.user_can_create_projects == 1 && cdash.user_is_admin ==0">
            <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
                <tbody>
                <tr class="table-heading1">
                    <td id="nob">
                        <h3>Administration</h3>
                    </td>
                </tr>
                <tr class="trodd">
                    <td id="nob">
                        <a href="project/new">Start a new project</a>
                    </td>
                </tr>
                </tbody>
            </table>
            <br/>
        </div>

        <!-- Global Administration -->
        <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb"
               ng-if="::cdash.user_is_admin == 1">
            <tbody>
            <tr class="table-heading1">
                <td id="nob">
                    <h3>Administration</h3>
                </td>
            </tr>
            <tr class="treven">
                <td id="nob">
                    <a href="project/new">Create new project</a>
                </td>
            </tr>
            <tr class="trodd">
                <td id="nob">
                    <a href="manageProjectRoles.php">Manage project roles</a>
                </td>
            </tr>
            <tr class="treven">
                <td id="nob">
                    <a href="manageSubProject.php">Manage subproject</a>
                </td>
            </tr>
            <tr class="trodd">
                <td id="nob">
                    <a href="manageBuildGroup.php">Manage project groups</a>
                </td>
            </tr>
            <tr class="treven">
                <td id="nob">
                    <a href="manageCoverage.php">Manage project coverage</a>
                </td>
            </tr>
            <tr class="trodd">
                <td id="nob">
                    <a href="manageBanner.php">Manage banner message</a>
                </td>
            </tr>
            <tr class="treven">
                <td id="nob">
                    <a href="manageUsers.php">Manage users</a>
                </td>
            </tr>
            <tr class="trodd">
                <td id="nob">
                    <a href="authtokens/manage">Manage authentication tokens</a>
                </td>
            </tr>
            <tr class="treven">
                <td id="nob">
                    <a href="upgrade.php">Maintenance</a>
                </td>
            </tr>
            <tr class="trodd">
                <td id="nob">
                    <a href="monitor.php">Monitor / Processing Statistics</a>
                </td>
            </tr>
            <tr class="treven">
                <td id="nob">
                    <a href="siteStatistics.php">Site Statistics</a>
                </td>
            </tr>
            <tr class="trodd">
                <td id="nob">
                    <a href="userStatistics.php">User Statistics</a>
                </td>
            </tr>
            <tr class="treven">
                <td id="nob">
                    <a href="manageBackup.php">Manage Backup</a>
                </td>
            </tr>
            </tbody>
        </table>

        <style>
            #tokenDescriptionCell {
                display: flex;
                justify-content: space-between;
                white-space: nowrap;
                align-items: center;
            }

            #tokenDescriptionlabel {
                margin: 0 0.5em 0 0;
            }
        </style>
    @endverbatim
@endsection
