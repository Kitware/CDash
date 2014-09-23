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
        <link href="css/nv.d3.css" rel="stylesheet" type="text/css"/>
        <link rel="stylesheet" href="css/bootstrap.min.css"/>

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

        <!-- Generate line charts -->
        <script type="text/javascript">
          <xsl:for-each select='/cdash/measurement'>
            <xsl:variable name="measurement_name" select="name"/>
            <xsl:variable name="measurement_nice_name" select="nice_name"/>
            <xsl:for-each select='group'>
              var <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$measurement_name"/> =
                <xsl:value-of select="chart"/>;
              makeLineChart("<xsl:value-of select="group_name"/>" + " " + "<xsl:value-of select="$measurement_nice_name"/>",
                            "#<xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$measurement_name"/>_chart svg",
                            <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="$measurement_name"/>,
                            true);
            </xsl:for-each>
          </xsl:for-each>

          <xsl:for-each select='/cdash/coverage'>
            var <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/> = <xsl:value-of select="chart"/>;
            makeLineChart("<xsl:value-of select="group_name"/>" + " " + "<xsl:value-of select="nice_name"/>",
                            "#<xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/>_chart svg",
                            <xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/>,
                            true);
            makeBulletChart("<xsl:value-of select="group_name"/>" + " " + "<xsl:value-of select="nice_name"/>",
              "#<xsl:value-of select="group_name_clean"/>_<xsl:value-of select="name"/>_bullet svg",
              <xsl:value-of select="low"/>,
              <xsl:value-of select="medium"/>,
              <xsl:value-of select="satisfactory"/>,
              <xsl:value-of select="current"/>,
              <xsl:value-of select="previous"/>,
              25);
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
          <caption><h4>Build Info</h4></caption>
          <tr class="row">
              <th class="col-md-1"> </th>
                <xsl:for-each select='/cdash/group'>
                  <th class="col-md-2" colspan="2">
                    <xsl:value-of select="name"/>
                  </th>
                </xsl:for-each>
          </tr>

          <xsl:for-each select='/cdash/measurement'>
            <xsl:variable name="measurement_name" select="name"/>
            <tr class="row">
              <td class="col-md-1">
                <b><xsl:value-of select="nice_name"/></b>
              </td>
              <xsl:for-each select='group'>
                <td class="col-md-1">
                  <xsl:value-of select="value"/>
                </td>
                <td class="col-md-1" id="{group_name_clean}_{$measurement_name}_chart" style="height:51px;">
                  <svg></svg>
                </td>
              </xsl:for-each>
            </tr>
          </xsl:for-each>
        </table> <!-- end of build info table -->

        <xsl:if test="/cdash/coverage">
          <table class="table-bordered table-responsive table-condensed container-fluid">
            <caption><h4>Coverage</h4></caption>
            <xsl:for-each select='/cdash/coverage'>
              <tr class="row" style="height:50px;">
                <td class="col-md-1"><b><xsl:value-of select="group_name"/><xsl:text> </xsl:text><xsl:value-of select="nice_name"/></b></td>
                <td class="col-md-1">
                  <xsl:value-of select="current"/>%
                </td>
                <td id="{group_name_clean}_{name}_chart" class="col-md-1" style="height:50px;">
                  <svg></svg>
                </td>
                <td id="{group_name_clean}_{name}_bullet" class="col-md-4" colspan="4" style="height:50px;">
                  <svg></svg>
                </td>
              </tr>
            </xsl:for-each>
          </table>
        </xsl:if> <!-- end of coverage -->

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
