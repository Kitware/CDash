<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html lang="en" ng-app="CDash" ng-controller="ManageBuildGroupController">
  <head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex,nofollow" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="stylesheet" type="text/css" href="cdash.css" /> <!-- TODO: handle switch -->
    <link rel="stylesheet" type="text/css" href="javascript/jquery.qtip.min.css" />
    <link rel="stylesheet" type="text/css" href="javascript/jquery-ui-1.8.16.css" />
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"/>

    <script src="javascript/jquery-1.10.2.js"></script>
    <script src="javascript/jquery.flot.min.js"></script>
    <script src="javascript/jquery.flot.selection.min.js"></script>
    <script src="javascript/jquery.flot.time.min.js"></script>
    <script src="javascript/jquery.qtip.min.js"></script>
    <script src="javascript/jquery-ui-1.10.4.min.js"></script>
    <script src="javascript/tooltip.js"></script>

    <!-- all this just for query string? TODO: improve that -->
    <script src="javascript/cdashmenu.js"></script>
    <script src="javascript/jquery.cookie.js"></script>
    <script src="javascript/jquery.tablesorter.js"></script>
    <script src="javascript/jquery.dataTables.min.js"></script>
    <script src="javascript/jquery.metadata.js"></script>
    <script src="javascript/jtip.js"></script>
    <script src="javascript/jqModal.js"></script>

    <script src="javascript/cdashSortable.js"></script>
    <script src="javascript/bootstrap.min.js"></script>
    <script src="javascript/tabNavigation.js"></script>

    <script src="javascript/angular.min.js"></script>
    <script src="javascript/angular-animate.min.js"></script>
    <script src="javascript/angular-ui-sortable.min.js"></script>
    <script src="javascript/cdash_angular.js"></script>
    <script src="javascript/controllers/manageBuildGroup.js"></script>

    <title>CDash - BuildGroups</title>
  </head>
  <body bgcolor="#ffffff">
    <ng-include src="'header.php'"></ng-include>
    <br/>

    <div ng-show="cdash.error">
      <b>Error: {{cdash.error}}</b>
    </div>
    <div ng-show="cdash.warning">
      <b>Warning: {{cdash.warning}}</b>
    </div>

    <div ng-if="cdash.requirelogin == 1">
      Please <a href="user.php">login</a> to view this page.
    </div>

    <div class="container" ng-if="cdash.requirelogin != 1">

      <!-- project selection form -->
      <div class="row">
        <div class="col-md-3">
          <form class="form-inline" name="form1" method="post" action="{{'manageBuildGroup.php?projectid=' + cdash.projectid}}">
            <div class="form-group">
              <label for="projectSelection">Project: </label>
              <select name="projectSelection" onchange="location = 'manageBuildGroup.php?projectid='+this.options[this.selectedIndex].value;" class="form-control">
                <option value=-1>Choose...</option>
                <option ng-repeat="proj in cdash.availableprojects" value="{{proj.id}}" ng-selected="proj.id==cdash.project.id">
                  {{proj.name}}
                </option>
              </select>
            </div>
          </form>
        </div>
      </div>

      <!-- If a project has been selected -->
      <div role="tabpanel" ng-if="cdash.project.id > -1">
        <ul class="nav nav-tabs" role="tablist" id="tabs">
          <li role="presentation" class="active">
            <a href="#current" aria-controls="current" role="tab" data-toggle="tab">
              <strong>Current BuildGroups</strong>
            </a>
          </li>
          <li role="presentation">
            <a href="#create" aria-controls="create" role="tab" data-toggle="tab">
              <strong>Create new BuildGroup</strong>
            </a>
          </li>
          <li role="presentation">
            <a href="#move" aria-controls="move" role="tab" data-toggle="tab">
              <strong>Move Builds</strong>
            </a>
          </li>
          <li role="presentation">
            <a href="#wildcard" aria-controls="wildcard" role="tab" data-toggle="tab">
              <strong>Wildcard BuildGroups</strong>
            </a>
          </li>
        </ul>

        <div class="tab-content container">

          <div role="tabpanel" class="tab-pane active" id="current">
            <!-- List the current BuildGroups -->
            <div class="row">
              <div class="col-md-12">
                <div id="sortable" ui-sortable="sortable" ng-model="cdash.buildgroups">
                  <div class="row" ng-repeat="buildgroup in cdash.buildgroups" id="{{buildgroup.id}}" style="cursor: move;" ng-class-even="'even'" ng-class-odd="'odd'">
                    <div class="col-md-4">
                      <!-- the BuildGroup name & an icon for more options -->
                      <span ng-click="showOptions = !showOptions" ng-class="showOptions ? 'glyphicon glyphicon-chevron-down' : 'glyphicon glyphicon-chevron-right'" style="cursor: pointer;"></span> {{buildgroup.name}}

                      <!-- Display delete icon if this isn't a default build group -->
                      <span ng-if="buildgroup.name != 'Nightly' && buildgroup.name != 'Continuous' && buildgroup.name != 'Experimental'" ng-show="showOptions" ng-click="deleteBuildGroup(buildgroup.id)" class="glyphicon glyphicon-trash animate-show" style="cursor: pointer;"></span>
                    </div>

                    <div class="col-md-8 animate-show" ng-show="showOptions">
                      <!-- Form to modify BuildGroup settings -->
                      <form ng-show="showOptions">
                        <input type="hidden" name="buildgroupid" value="{{buildgroup.id}}"/>
                        <!-- Only allow this BuildGroup to be renamed if it isn't one of the default BuildGroups (Nightly/Continuous/Experimental) -->
                        <div class="form-group" ng-if="buildgroup.name != 'Nightly' && buildgroup.name != 'Continuous' && buildgroup.name != 'Experimental'">
                          <label for="name">Name</label>
                          <input name="name" type="text" class="form-control" ng-model="buildgroup.name"/>
                        </div>

                        <div class="form-group">
                          <label for="description">Description</label>
                          <input name="description" type="text" class="form-control" ng-model="buildgroup.description"/>
                        </div>

                        <div class="form-group">
                          <label for="autoremovetimeframe">Number of days before builds should be removed</label>
                          <input type="text" name="autoremovetimeframe" class="form-control" ng-model="buildgroup.autoremovetimeframe"/>
                          <p class="help-block">
                            "0" indicates that this BuildGroup should defer to its Project's settings for build expiration.
                          </p>
                        </div>

                        <div class="radio">
                          <label>
                            <input name="summaryEmail" type="radio" value="0" ng-model="buildgroup.summaryemail" ng-checked="buildgroup.summaryemail == 0"/> Normal email
                          </label>
                          <p class="help-block">
                            Defer to the Project's email settings.
                          </p>
                        </div>

                        <div class="radio">
                          <label>
                            <input name="summaryEmail" type="radio" value="1" ng-model="buildgroup.summaryemail" ng-checked="buildgroup.summaryemail == 1"/> Summary email
                          </label>
                          <p class="help-block">
                            Send only one email per day when the first build fails.
                          </p>
                        </div>

                        <div class="radio">
                          <label>
                            <input name="summaryEmail" type="radio" value="2" ng-model="buildgroup.summaryemail" ng-checked="buildgroup.summaryemail == 2"/> No email
                          </label>
                          <p class="help-block">
                            Don't send any email, regardless of users' preferences.
                          </p>
                        </div>

                        <div class="checkbox">
                          <label>
                            <input name="emailCommitters" type="checkbox" value="1" ng-model="buildgroup.emailcommitters" ng-checked="buildgroup.emailcommitters != 0"/> Email committers
                          </label>
                          <p class="help-block">
                            Send email to authors &amp; committers when build problems occur.
                          </p>
                        </div>

                        <div class="checkbox">
                          <label>
                            <input name="includeInSummary" type="checkbox" value="1" ng-model="buildgroup.includesubprojecttotal" ng-checked="buildgroup.includesubprojecttotal == 1"/> Included in SubProject summary
                          </label>
                          <p class="help-block">
                            This group's builds should contribute to totals on SubProject summary pages.
                          </p>
                        </div>

                        <button type="submit" class="btn btn-default" ng-click="saveBuildGroup(buildgroup)">Save</button>
                        <img id="buildgroup_updated_{{buildgroup.id}}" src="images/check.gif" style="display: none; height:16px; width=16px; margin-top:9px;" />

                      </form>

                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <button class="btn btn-default" ng-click="updateBuildGroupOrder()">Update Order</button>
                <img id="order_updated" src="images/check.gif" style="display: none; height:16px; width=16px; margin-top:9px;" />
              </div>
            </div>
          </div> <!-- current BuildGroups -->

          <div role="tabpanel" class="tab-pane" id="create">
            <div class="row">
              <div class="col-md-12 text-center">
                <strong>Create a new BuildGroup</strong>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12 form-horizontal">
                <label for="newBuildGroupName">New BuildGroup name:</label>
                <input name="newBuildGroupName" class="form-control" type="text" size="40" ng-model="newBuildGroupName" />
                <button class="btn btn-default" ng-click="createBuildGroup(newBuildGroupName)">Create BuildGroup</button>
                <img id="buildgroup_created" src="images/check.gif" style="display: none; height:16px; width=16px; margin-top:9px;" />
              </div>
            </div>
          </div> <!-- create new BuildGroup -->

          <div role="tabpanel" class="tab-pane" id="move">
            <div class="form-horizontal">

              <div class="form-group">
                <label for="globalMoveSelectionType"> Show:</label>
                <select name="globalMoveSelectionType" ng-model="cdash.filteredBuildGroup" ng-options="buildgroup as buildgroup.name for buildgroup in cdash.buildgroups | orderBy:'name'" class="form-control">
                  <option value="">All</option>
                </select>
                <p class="help-block">
                  expected builds and those submitted in the past 7 days
                </p>
              </div>

              <div class="form-group">
                <select name="movebuilds[]" size="15" multiple="multiple" id="movebuilds" ng-model="cdash.movebuilds" ng-options="build as build.name for build in cdash.currentbuilds | orderBy:'name' | filter_builds:cdash.filteredBuildGroup" class="repeat-item form-control">
                </select>
              </div>

              <div class="form-group">
                <label for="buildgroupSelection"> Move to BuildGroup: </label>
                <select name="buildgroupSelection" ng-model="cdash.buildgroupSelection" ng-options="buildgroup as buildgroup.name for buildgroup in cdash.buildgroups | orderBy:'name'" class="form-control">
                  <option value="">Choose...</option>
                </select>
                <p class="help-block">
                  (select a group even if you want only expected)
                </p>
              </div>

              <div class="form-group">
                <div class="checkbox">
                  <label><input name="expectedMove" ng-model="expected" type="checkbox" value="1"/> Expected</label>
                </div>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-default" ng-click="moveBuilds(cdash.movebuilds, cdash.buildgroupSelection, expected)">Move selected build(s) to group</button>
                <img id="builds_moved" src="images/check.gif" style="display: none; height:16px; width=16px; margin-top:9px;" />
              </div>
            </div>
          </div> <!-- global move -->

          <div role="tabpanel" class="tab-pane" id="wildcard">
            <div class="form-horizontal">
              <div class="form-group">
                <label for="buildgroupSelection">Define Wildcard rule for:</label>
                <select name="buildgroupSelection" ng-model="cdash.buildgroupSelection" ng-options="buildgroup as buildgroup.name for buildgroup in cdash.buildgroups | orderBy:'name'" class="form-control">
                  <option value="">Choose...</option>
                </select>
              </div>
              <div class="form-group">
                <label for="buildNameMatch">Build Names should contain:</label>
                <input name="buildNameMatch" type="text" ng-model="buildNameMatch" size="12" class="form-control"/>
              </div>
              <div class="form-group">
                <label for="buildType">Build Type:</label>
                <select name="buildType" ng-model="buildType" class="form-control">
                  <option>Nightly</option>
                  <option>Continuous</option>
                  <option>Experimental</option>
                </select>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-default" ng-click="addWildcardRule(cdash.buildgroupSelection, buildType, buildNameMatch)">Define BuildGroup</button>
                <img id="wildcard_defined" src="images/check.gif" style="display: none; height:16px; width=16px; margin-top:9px;" />
              </div>
            </div>

            <hr/>

            <form name="deletewildcardrules" method="post" ng-if="cdash.wildcards" action="{{'manageBuildGroup.php?projectid=' + cdash.project.id}}">
              <table class="table-striped" style="width:100%;">
                <caption class="h4">Existing Wildcard Rules</caption>
                <thead>
                  <th>BuildGroup</th>
                  <th>Matches</th>
                  <th>Type</th>
                  <th>Delete?</th>
                </thead>
                <tbody>
                  <tr ng-repeat="wildcard in cdash.wildcards" class="repeat-item">
                    <td>{{wildcard.buildgroupname}}</td>
                    <td>{{wildcard.match}}</td>
                    <td>{{wildcard.buildtype}}</td>
                    <td>
                      <span ng-click="deleteWildcardRule(wildcard)" class="glyphicon glyphicon-trash animate-show" style="cursor: pointer;"></span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div> <!-- wildcard -->
        </div> <!-- tab-content -->
      </div> <!-- tabpanel -->
    </div> <!-- container -->

    <!-- FOOTER -->
    <br/>
    <ng-include src="'footer.php'"></ng-include>
  </body>
</html>
