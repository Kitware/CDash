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
          #sortable
            {
            margin: 0;
            padding: 0;
            list-style: none;
            }
          #sortable
            {
            height: 425px;
            }
          #sortable li
            {
            margin: 0 3px 3px 3px;
            padding: 3px;
            height: 400px;
            font-size: 1.4em;
            border-style: solid;
            border-width: 2px;
            cursor: move;
            }
          #instructions
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
            $( "#sortable" ).sortable({ cursor: "move" });
            $( "#sortable" ).disableSelection();

            // save layout function
            $( "#saveLayout" ).click(function() {
              var newLayout = JSON.stringify(getSortedElements("#sortable"));
              $("#loading").attr("src", "images/loading.gif");
              $.ajax(
                {
                url: "manageOverview.php",
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

            // add column function
            function addColumn(newColumnName, newColumnId)
              {
              var newElement = $('<li class='col-md-2 measurement text-center'>\
                  <div class='row'>\
                    <div class='col-xs-1 col-xs-offset-9 glyphicon glyphicon-remove'>\
                    </div>\
                  </div>\
                  <p id='label' class='text-center'>' + newColumnName + '</p>\
                </li>\
              ');
              newElement.attr('id', newColumnId);
              $( "#sortable" ).append(newElement);
              $("#newColumn").find("option[value=" + newColumnId + "]").remove();
              if ($("#newColumn").has("option").length == 0)
                {
                $("#newColumn").prop("disabled", true);
                $("#addColumn").prop("disabled", true);
                }
              }

            // call addColumn when the appropriate button is clicked.
            $( "#addColumn" ).click(function() {
              var newColumnName = $("#newColumn").find(":selected").text();
              var newColumnId = $("#newColumn").val();
              addColumn(newColumnName, newColumnId);
            });

            // remove column function
            $( "#sortable" ).delegate(".glyphicon-remove", 'click', function() {
              var listElement = $(this).closest("li");
              var label = listElement.find("p").text();
              listElement.remove();
              $("#newColumn").append("<option>" + label + "</option>");
              $("#newColumn").prop("disabled", false);
              $("#addColumn").prop("disabled", false);
            });

            // add existing columns
            <xsl:for-each select='/cdash/overviewgroup'>
              addColumn('<xsl:value-of select="name"/>', <xsl:value-of select="id"/>);
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
              <ul class="row" id="sortable">
              </ul>
              <div class="row">
                <button type="submit" id="saveLayout" class="btn btn-default col-md-1">
                  Save Layout
                </button>
                <div class="col-md-1">
                  <img id="loading" style="height:16px; width=16px; margin-top:9px;"/>
                </div>
                <select id="newColumn" class="col-md-2 col-md-offset-4">
                  <xsl:for-each select='/cdash/buildgroup'>
                    <option>
                      <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                      <xsl:value-of select="name"/>
                    </option>
                  </xsl:for-each>
                </select>
                <button id="addColumn" class="btn btn-default"> Add Column </button>
              </div>
              <div class="row" id="instructions">
                <p>
                  Add and remove columns above.
                  Drag them into the proper order (if necessary).
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
