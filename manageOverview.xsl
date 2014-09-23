<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="headscripts.xsl"/>
  <xsl:include href="headeradminproject.xsl"/>

  <!-- Local includes -->
  <xsl:include href="local/footer.xsl"/>
  <xsl:include href="local/headscripts.xsl"/>
  <xsl:include href="local/headeradminproject.xsl"/>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
  doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

  <xsl:template match="/">
    <html>

      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>

        <link rel="stylesheet" href="css/bootstrap.min.css"/>
        <link rel="stylesheet" href="css/jquery-ui.css"/>
        <style>
          #buildSortable
            {
            margin: 0;
            padding: 0;
            list-style: none;
            height: 275px;
            }
          #buildSortable li
            {
            margin: 0 3px 3px 3px;
            padding: 3px;
            height: 250px;
            font-size: 1.4em;
            border-style: solid;
            border-width: 2px;
            cursor: move;
            }
          #staticSortable
            {
            margin: 0;
            padding: 0;
            }
          #staticSortable tr
            {
            margin: 0 3px 3px 3px;
            padding: 3px;
            font-size: 1.4em;
            border-style: solid;
            border-width: 2px;
            cursor: move;
            }
          .spacer
            {
            margin-top: 20px;
            }
        </style>

        <script src="javascript/jquery-1.10.2.js"/>
        <script src="javascript/jquery-ui-1.10.4.min.js"/>
        <script src="javascript/cdashSortable.js"></script>
        <script>
          $(function() {
            // setup sortable element
            $( "#buildSortable" ).sortable({ cursor: "move" });
            $( "#buildSortable" ).disableSelection();
            $( "#staticSortable" ).sortable({ cursor: "move" });
            $( "#staticSortable" ).disableSelection();

            // save layout function
            $( "#saveLayout" ).click(function() {

              // gather up our new layout here.
              // mark all build and static components as such
              var buildElements = getSortedElements('#buildSortable');
              for (i = 0; i <xsl:text disable-output-escaping="yes">&lt;</xsl:text> buildElements.length; ++i)
              {
                buildElements[i]['type'] = 'build';
              }

              var staticElements = getSortedElements('#staticSortable');
              for (i = 0; i <xsl:text disable-output-escaping="yes">&lt;</xsl:text> staticElements.length; ++i)
              {
                staticElements[i]['type'] = 'static';
              }

              // then concatenate them together and convert the list to JSON
              var newLayout = JSON.stringify(buildElements.concat(staticElements));

              $("#loading").attr("src", "images/loading.gif");
              $.ajax(
                {
                url: "manageOverview.php?projectid=<xsl:value-of select="cdash/project/id"/>",
                type: "POST",
                data: {saveLayout : newLayout},
                success: function(data)
                  {
                  $("#loading").attr("src", "images/check.gif");
                  },
                error: function(e, s, t)
                  {
                  console.log("status: " + s);
                  console.log("error thrown: " + t);
                  }
                });
              });

            // function to re-add an option to the selects.
            // called when the user removes a build group from the overview.
            function addGroupToLists(groupName)
              {
              $("#newBuildColumn").append("<option>" + groupName + "</option>");
              $("#newBuildColumn").prop("disabled", false);
              $("#addBuildColumn").prop("disabled", false);

              $("#newStaticRow").append("<option>" + groupName + "</option>");
              $("#newStaticRow").prop("disabled", false);
              $("#addStaticRow").prop("disabled", false);
              }

            // function to remove an option from the selects.
            // called when the user adds a build group to the overview.
            function removeGroupFromLists(groupId)
              {
              // build
              $("#newBuildColumn").find("option[value=" + groupId + "]").remove();
              if ($("#newBuildColumn").has("option").length == 0)
                {
                $("#newBuildColumn").prop("disabled", true);
                $("#addBuildColumn").prop("disabled", true);
                }

              // static analysis
              $("#newStaticRow").find("option[value=" + groupId + "]").remove();
              if ($("#newStaticRow").has("option").length == 0)
                {
                $("#newStaticRow").prop("disabled", true);
                $("#addStaticRow").prop("disabled", true);
                }
              }

            // add build column function
            function addBuildColumn(newGroupName, newGroupId)
              {
              // create the new element and add it to the sortable list
              var newElement = $('<li class='col-md-2 measurement text-center'>\
                  <div class='row'>\
                    <div class='col-xs-1 col-xs-offset-9 glyphicon glyphicon-remove'>\
                    </div>\
                  </div>\
                  <p id='label' class='text-center'>' + newGroupName + '</p>\
                </li>\
              ');
              newElement.attr('id', newGroupId);
              $( "#buildSortable" ).append(newElement);

              // now that this group has been added to the overview,
              // remove it as an option from our drop-down menus
              removeGroupFromLists(newGroupId);
              }

            // call addBuildColumn when the appropriate button is clicked.
            $( "#addBuildColumn" ).click(function() {
              var newColumnName = $("#newBuildColumn").find(":selected").text();
              var newColumnId = $("#newBuildColumn").val();
              addBuildColumn(newColumnName, newColumnId);
            });

            // add static analysis row function
            function addStaticRow(newGroupName, newGroupId)
              {
              // create the new row and add it to the sortable table
              var newElement = $('\
                <tr class='row measurement text-center'>\
                  <td id='label' class='text-center col-md-11'>' + newGroupName + '</td>\
                  <td class='col-md-1 glyphicon glyphicon-remove'>\
                  </td>\
                </tr>\
              ');
              newElement.attr('id', newGroupId);
              $( "#staticSortable" ).append(newElement);

              // now that this group has been added to the overview,
              // remove it as an option from our drop-down menus
              removeGroupFromLists(newGroupId);
              }

            // call addStaticRow when the appropriate button is clicked.
            $( "#addStaticRow" ).click(function() {
              var newRowName = $("#newStaticRow").find(":selected").text();
              var newRowId = $("#newStaticRow").val();
              addStaticRow(newRowName, newRowId);
            });

            // remove build column function
            $( "#buildSortable" ).delegate(".glyphicon-remove", 'click', function() {
              var listElement = $(this).closest("li");
              var label = listElement.find("p").text();
              listElement.remove();
              addGroupToLists(label);
            });

            // remove static row function
            $( "#staticSortable" ).delegate(".glyphicon-remove", 'click', function() {
              var tableRow = $(this).closest("tr");
              var label = tableRow.find("td").text();
              tableRow.remove();
              addGroupToLists(label);
            });

          // add existing groups
          <xsl:for-each select='/cdash/overview/build'>
            addBuildColumn('<xsl:value-of select="name"/>', <xsl:value-of select="id"/>);
          </xsl:for-each>
          <xsl:for-each select='/cdash/overview/static'>
            addStaticRow('<xsl:value-of select="name"/>', <xsl:value-of select="id"/>);
          </xsl:for-each>
          });
        </script>
      </head>

      <body bgcolor="#ffffff">

        <xsl:choose>
        <xsl:when test="/cdash/uselocaldirectory=1">
          <xsl:call-template name="headeradminproject_local"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:call-template name="headeradminproject"/>
        </xsl:otherwise>
        </xsl:choose>
        <br/>

        <div class="container" style="height:400px;">
          <div class="row">
            <div class="col-md-11">
              <ul class="row" id="buildSortable">
              </ul>
              <div class="row">
                <table style="width:100%;">
                  <tbody id="staticSortable">
                  </tbody>
                </table>
              </div>
              <div class="row spacer">
                <select id="newBuildColumn" class="col-md-2">
                  <xsl:for-each select='/cdash/buildgroup'>
                    <option>
                      <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                      <xsl:value-of select="name"/>
                    </option>
                  </xsl:for-each>
                </select>
                <div class="col-md-1">
                  <button id="addBuildColumn" class="btn btn-default"> Add Build Column </button>
                </div>
                <select id="newStaticRow" class="col-md-2 col-md-offset-4">
                  <xsl:for-each select='/cdash/buildgroup'>
                    <option>
                      <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                      <xsl:value-of select="name"/>
                    </option>
                  </xsl:for-each>
                </select>
                <div class="col-md-1">
                  <button id="addStaticRow" class="btn btn-default"> Add Static Analysis Row </button>
                </div>
              </div>
              <div class="row spacer"></div>
              <div class="row">
                <button type="submit" id="saveLayout" class="btn btn-default col-md-3 col-md-offset-4">
                  Save Layout
                </button>
                <div class="col-md-1">
                  <img id="loading" style="height:16px; width=16px; margin-top:9px;"/>
                </div>
              </div>
              <div class="row spacer" id="instructions">
                <p>
                  Add and remove groups above.
                  General build groups will appear as columns,
                  while static analysis groups will appear as rows.
                  Drag the groups into the proper order (if necessary).
                  Once you are satisfied, click the "Save Layout" button.
                </p>
                <a><xsl:attribute name="href">overview.php?project=<xsl:value-of select="cdash/project/name_encoded"/></xsl:attribute>Go to overview</a>
              </div>
            </div>
          </div>
        </div>

        <!-- FOOTER -->
        <br/>
        <br/>
        <br/>
        <br/>
        <br/>
        <xsl:choose>
        <xsl:when test="/cdash/uselocaldirectory=1">
          <xsl:call-template name="footer_local"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:call-template name="footer"/>
        </xsl:otherwise>
        </xsl:choose>

      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
