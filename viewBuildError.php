<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html lang="en" ng-app="CDash" ng-controller="BuildErrorController">
  <head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex,nofollow" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="stylesheet" type="text/css" href="cdash.css" /> <!-- TODO: handle switch -->

    <script src="javascript/jquery-1.10.2.js"></script>
    <script src="javascript/jquery.flot.min.js"></script>
    <script src="javascript/jquery.flot.time.min.js"></script>
    <script src="javascript/jquery.flot.selection.min.js"></script>
    <script src="javascript/tooltip.js"></script>
    <link rel="stylesheet" type="text/css" href="javascript/jquery.qtip.min.css" />
    <script src="javascript/jquery.qtip.min.js"></script>
    <script src="javascript/jquery-ui-1.10.4.min.js"></script>
    <link rel="stylesheet" type="text/css" href="javascript/jquery-ui-1.8.16.css" />
    <script src="javascript/cdashmenu.js"></script>
    <script src="javascript/jquery.cookie.js"></script>
    <script src="javascript/jquery.tablesorter.js"></script>
    <script src="javascript/jquery.dataTables.min.js"></script>
    <script src="javascript/cdashTableSorter.js"></script>
    <script src="javascript/jquery.metadata.js"></script>
    <script src="javascript/jtip.js"></script>
    <script src="javascript/jqModal.js"></script>
    <link rel="stylesheet" type="text/css" href="javascript/jqModal.css" />
    <link rel="stylesheet" type="text/css" href="javascript/jquery.dataTables.css" />
    <script src="javascript/cdashBuildError.js"></script>

    <script src="javascript/angular.min.js"></script>
    <script src="javascript/cdash_angular.js"></script>
    <script src="javascript/controllers/viewBuildError.js"></script>

    <title>CDash : {{cdash.project}}</title>
  </head>

  <body bgcolor="#ffffff">
    <ng-include src="'header.php'"></ng-include>
    <br/>
    <table border="0">
      <tr>
        <td align="left">
          <b>Site: </b>
          <a href="viewSite.php?siteid={{cdash.build.siteid}}">
            {{cdash.build.site}}
          </a>
        </td>
      </tr>
      <tr>
        <td align="left">
          <b>Build Name: </b>
          {{cdash.build.buildname}}
        </td>
      </tr>
      <tr>
        <td align="left">
          <b>Build Time: </b>
          {{cdash.build.starttime}}
        </td>
      </tr>
      <tr>
        <td align="left">
          &#x20;
        </td>
      </tr>
      <tr>
        <td align="left">
          Found <b>{{cdash.errors.length}}</b>&#x20;{{cdash.errortypename}}s
        </td>
      </tr>
      <tr>
        <td align="left">
          <a href="viewBuildError.php?type={{cdash.nonerrortype}}&buildid={{cdash.build.buildid}}">
            {{cdash.nonerrortypename}}s are here.
          </a>
        </td>
      </tr>
    </table>

    <br/>
    <table ng-repeat="error in cdash.errors" style="table-layout:fixed; width:100%">
      <colgroup>
        <col style="width: 115px"/>
        <col/>
      </colgroup>

      <tr ng-if="error.sourceline" style="background-color: #b0c4de; font-weight: bold">
        <th colspan="2" align="left">
          <img ng-if="error.new == -1" src="images/flaggreen.gif" title="flag"/>
          <img ng-if="error.new == 1" src="images/flag.png" title="flag"/>
          <pre ng-if="error.new == 0" style="height: 0px;"></pre>
        </th>
      </tr>

      <tr style="background-color: #b0c4de; font-weight: bold" ng-if="error.targetname">
        <th colspan="2">
          {{cdash.errortypename}} while building
          <code>{{error.language}}</code> {{error.outputtype}} "
          <code>{{error.outputfile}}</code>"
          in target {{error.targetname}}
        </th>
      </tr>

      <tr ng-if="cdash.cvsurl">
        <th class="measurement">
          <nobr> Repository </nobr>
        </th>
        <td>
          <a href="{{cdash.cvsurl}}">
            {{cdash.cvsurl}}
          </a>
        </td>
      </tr>

      <tr ngif="error.logline">
        <th class="measurement">
          <nobr>Build Log Line </nobr>
        </th>
        <td>
          {{error.logline}}
        </td>
      </tr>

      <tr ngif="error.precontext || error.postcontext">
        <th class="measurement">
          <nobr> {{cdash.errortypename}} </nobr>
        </th>
        <td>
          <pre class="compiler-output">{{error.precontext}}</pre>
          <b>
            <pre class="compiler-output" ng-bind-html="error.text"></pre>
          </b>
          <pre class="compiler-output">{{error.postcontext}}</pre>
        </td>
      </tr>

      <tr ng-if="error.sourcefile && error.targetname">
        <th class="measurement">
          <nobr>Source File</nobr>
        </th>
        <td>
          {{error.sourcefile}}
        </td>
      </tr>

      <tr ng-if="error.labels">
        <th class="measurement">
          <div ng-if="error.labels.length=1">Label</div>
          <div ng-if="error.labels.length>1">Labels</div>
        </th>
        <td>
          <div ng-repeat="label in error.labels">
          {{label}}
          </div>
        </td>
      </tr>

      <tr ng-if="error.argumentfirst">
        <th class="measurement" style="width: 1%">Command</th>
        <td>
          <div style="margin-left: 25px; text-indent: -25px;">

            <div ng-if="error.argument">
              <span ng-hide="error.argument.length>4" id="showarguments_{{error.id}}">
                <a href="#" onclick="return showArguments({{error.id}});">
                  [+]
                </a>
                <nobr>"<font class="argument">{{error.argumentfirst}}</font>"</nobr>
              </span>

              <span ng-hide="error.argument.length>3" id="argumentlist_{{error.id}}">
                <a href="#" onclick="return hideArguments({{error.id}});">
                  [-]
                </a>
                <nobr>"<font class="argument">{{error.argumentfirst}}</font>"</nobr>
                <div ng-repeat="argument in error.arguments">
                  "<font class="argument">{{argument}}</font>"</nobr>
                </div>
              </span>
            </div>

            <div ng-if="!error.argument">
              <nobr>"<font class="argument">{{error.argumentfirst}}</font>"</nobr>

              <div ng-repeat="argument in error.arguments">
                "<font class="argument">{{argument}}</font>"</nobr>
              </div>
            </div>

          </div>
        </td>
      </tr>

      <tr ng-if="error.workingdirectory">
        <th class="measurement" style="width: 1%">
          Directory
        </th>
        <td>
          {{error.workingdirectory}}
        </td>
      </tr>

      <tr ng-if="error.exitcondition">
        <th class="measurement">
          <nobr> Exit Condition </nobr>
        </th>
        <td>
          {{error.exitcondition}}
        </td>
      </tr>

      <tr ng-if="error.stdoutput">
        <th class="measurement">
          <nobr> Standard Output </nobr>
        </th>
        <td>
          <pre class="compiler-output" name="stdout">{{error.stdoutput}}</pre>
        </td>
      </tr>

      <tr ng-if="error.stderror">
        <th class="measurement">
          <nobr> Standard Error </nobr>
        </th>
        <td>
          <pre class="compiler-error" name="stderr">
            {{error.stderror}}
          </pre>
        </td>
      </tr>
    </table>

    <!-- FOOTER -->
    <br/>
    <ng-include src="'footer.php'"></ng-include>
  </body>
</html>
