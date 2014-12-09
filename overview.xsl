<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="header.xsl"/>
  <xsl:include href="footer.xsl"/>

  <xsl:include href="local/header.xsl"/>
  <xsl:include href="local/footer.xsl"/>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
    doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

  <xsl:template match="/">
    <html>
      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
        <xsl:call-template name="headscripts"/>

        <!-- Include static css -->
        <link rel="stylesheet" href="css/nv.d3.css" type="text/css"/>
        <link rel="stylesheet" href="css/bootstrap.min.css" type="text/css"/>
        <link rel="stylesheet" href="css/jquery.jqplot.min.css" type="text/css" />

        <!-- Include CDash's css -->
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>

        <!-- Include JavaScript -->
        <script src="javascript/cdashBuildGraph.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/cdashAddNote.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/d3.min.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/nv.d3.min.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/linechart.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/bulletchart.js" type="text/javascript" charset="utf-8"></script>
        <script src="javascript/jquery.jqplot.min.js" type="text/javascript"></script>
        <script src="javascript/plugins/jqplot.dateAxisRenderer.min.js" type="text/javascript"></script>
        <script src="javascript/plugins/jqplot.highlighter.min.js" type="text/javascript"></script>

        <!-- Generate line charts -->
        <script type="text/javascript">
          <!-- build info section -->
          <xsl:for-each select='/cdash/measurement'>
            <xsl:variable name="measurement_name" select="name"/>
            <xsl:variable name="measurement_nice_name" select="nice_name"/>
            <xsl:variable name="sort" select="sort"/>
            <xsl:for-each select='group'>
              var <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$measurement_name"/> =
                <xsl:value-of select="chart"/>;
              makeLineChart("<xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$measurement_name"/>_chart",
                            <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$measurement_name"/>,
                            "<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>",
                            "<xsl:value-of select="group_name_clean"/>",

                            "<xsl:value-of select="/cdash/hasSubprojects"/>",
                            "<xsl:value-of select="$sort"/>");
            </xsl:for-each>
          </xsl:for-each>

          <!-- coverage section -->
          <xsl:for-each select='/cdash/coverage'>
            var <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/> = <xsl:value-of select="chart"/>;
            makeLineChart("<xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/>_chart",
                          <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/>,
                          "<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>",
                          "Coverage",
                          "<xsl:value-of select="/cdash/hasSubprojects"/>",
                          "");
            makeBulletChart("<xsl:value-of select="group_name"/>" + " " + "<xsl:value-of select="nice_name"/>",
              "#<xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/>_bullet svg",
              <xsl:value-of select="low"/>,
              <xsl:value-of select="medium"/>,
              <xsl:value-of select="satisfactory"/>,
              <xsl:value-of select="current"/>,
              <xsl:value-of select="previous"/>,
              25);
          </xsl:for-each>

          <!-- dynamic analysis section -->
          <xsl:for-each select='/cdash/dynamicanalysis'>
            <xsl:variable name="checker_name" select="name"/>
            <xsl:variable name="checker_nice_name" select="nice_name"/>
            <xsl:for-each select='group'>
              var <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$checker_name"/> =
                <xsl:value-of select="chart"/>;
              makeLineChart("<xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$checker_name"/>_chart",
                            <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$checker_name"/>,
                            "<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>",
                            "DynamicAnalysis",
                            "<xsl:value-of select="/cdash/hasSubprojects"/>",
                            "");
            </xsl:for-each>
          </xsl:for-each>

          <!-- static analysis section -->
          <xsl:for-each select='/cdash/staticanalysis'>
            <xsl:variable name="group_name" select="group_name"/>
            <xsl:variable name="group_name_clean" select="group_name_clean"/>

            <xsl:for-each select='measurement'>
              var <xsl:value-of select="$group_name_clean"/>_<xsl:value-of select="name"/> =
                <xsl:value-of select="chart"/>;
              makeLineChart("<xsl:value-of select="$group_name_clean"/>_<xsl:value-of select="name"/>_chart",
                            <xsl:value-of select="$group_name_clean"/>_<xsl:value-of select="name"/>,
                            "<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>",
                            "<xsl:value-of select="$group_name_clean"/>",
                            "<xsl:value-of select="/cdash/hasSubprojects"/>",
                            "<xsl:value-of select="sort"/>");
            </xsl:for-each>
          </xsl:for-each>

        </script>
      </head>

      <body bgcolor="#ffffff">

        <xsl:choose>
        <xsl:when test="/cdash/uselocaldirectory=1">
          <xsl:call-template name="header_local"/>
        </xsl:when>
        <xsl:otherwise>
          <xsl:call-template name="header"/>
        </xsl:otherwise>
        </xsl:choose>

        <table class="table-bordered table-responsive table-condensed container-fluid">
          <caption class="h4">Configure / Build / Test</caption>
          <tr class="row">
              <th class="col-md-1"> </th>
                <xsl:for-each select='/cdash/group'>
                  <th class="col-md-2 border-right border-left" colspan="2">
                    <xsl:value-of select="name"/>
                  </th>
                </xsl:for-each>
          </tr>

          <xsl:for-each select='/cdash/measurement'>
            <xsl:variable name="measurement_name" select="name"/>
            <tr class="row">
              <td class="col-md-1 border-right">
                <b><xsl:value-of select="nice_name"/></b>
              </td>
              <xsl:for-each select='group'>
                <td class="col-md-1 border-left">
                  <xsl:value-of select="value"/>
                </td>
                <td class="col-md-1 border-right">
                  <div id="{group_name_clean}_{$measurement_name}_chart" class="overview-line-chart"></div>
                </td>
              </xsl:for-each>
            </tr>
          </xsl:for-each>
        </table> <!-- end of build info table -->

        <xsl:if test="/cdash/coverage">
          <table class="table-bordered table-responsive table-condensed container-fluid">
            <caption class="h4">Coverage</caption>
            <xsl:for-each select='/cdash/coverage'>
              <tr class="row" style="height:100px;">
                <td class="col-md-1"><b><xsl:value-of select="group_name"/><xsl:text> </xsl:text><xsl:value-of select="nice_name"/></b></td>
                <td class="col-md-1">
                  <xsl:value-of select="current"/>%
                </td>
                <td class="col-md-1">
                  <div id="{group_name_clean}_{name}_chart" class="overview-line-chart"></div>
                </td>
                <td id="{group_name_clean}_{name}_bullet" class="col-md-4" colspan="4" style="height:100px;">
                  <svg></svg>
                </td>
              </tr>
            </xsl:for-each>
          </table>
        </xsl:if> <!-- end of coverage -->

        <xsl:if test="/cdash/dynamicanalysis">
          <table class="table-bordered table-responsive table-condensed container-fluid" style="width:100%;">
            <caption class="h4">Dynamic Analysis</caption>
            <xsl:for-each select='/cdash/dynamicanalysis'>
              <xsl:variable name="checker_name" select="name"/>
              <xsl:variable name="checker_nice_name" select="nice_name"/>
              <xsl:for-each select='group'>
                <tr class="row">
                  <td class="col-md-1">
                    <b><xsl:value-of select="$checker_nice_name"/></b>
                  </td>
                  <td class="col-md-1">
                    <xsl:value-of select="group_name"/>
                  </td>
                  <td class="col-md-1">
                    <xsl:value-of select="value"/>
                  </td>
                  <td class="col-md-1">
                    <div id="{group_name_clean}_{$checker_name}_chart" class="overview-line-chart"></div>
                  </td>
                </tr>
              </xsl:for-each>
            </xsl:for-each>
          </table>
        </xsl:if> <!-- end of dynamic analysis -->

        <xsl:if test="/cdash/staticanalysis">
          <table class="table-bordered table-responsive table-condensed container-fluid" style="width:100%;">
            <caption class="h4">Static Analysis</caption>
            <xsl:for-each select='/cdash/staticanalysis'>
              <xsl:variable name="group_name_clean" select="group_name_clean"/>
              <tr class="row">
                <td class="col-md-1">
                  <b><xsl:value-of select="group_name"/></b>
                </td>
                <xsl:for-each select='measurement'>
                  <td class="col-md-1">
                    <xsl:value-of select="nice_name"/>
                  </td>
                  <td class="col-md-1">
                    <xsl:value-of select="value"/>
                  </td>
                  <td class="col-md-1">
                    <div id="{$group_name_clean}_{name}_chart" class="overview-line-chart"></div>
                  </td>
                </xsl:for-each>
              </tr>
            </xsl:for-each>
          </table>
        </xsl:if> <!-- end of static analysis -->

        <!-- FOOTER -->
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
