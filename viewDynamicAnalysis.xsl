<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="header.xsl"/>
  <xsl:include href="footer.xsl"/>
  <!-- Local includes -->
  <xsl:include href="local/footer.xsl"/>
  <xsl:include href="local/header.xsl"/>

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
        <xsl:call-template name="headscripts"/>
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

        <br/>

        <!-- Main -->
        <br/>
        <h3>Dynamic analysis started on <xsl:value-of select="cdash/build/buildtime"/></h3>
        <table border="0">
          <tr>
            <td align="right"><b>Site Name:</b></td>
            <td><xsl:value-of select="cdash/build/site"/></td>
          </tr>
          <tr>
            <td align="right"><b>Build Name:</b></td>
            <td><xsl:value-of select="cdash/build/buildname"/></td>
          </tr>
        </table>

        <table xmlns:lxslt="http://xml.apache.org/xslt" cellspacing="0">
          <tr>
            <th>Name</th>
            <th>Status</th>
            <xsl:for-each select="cdash/defecttypes">
              <th>
                <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
                <xsl:value-of select="type"/>
                <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
              </th>
            </xsl:for-each>
            <th>Labels</th>
          </tr>

          <xsl:for-each select="cdash/dynamicanalysis">
            <tr align="center">
              <xsl:attribute name="bgcolor"><xsl:value-of select="bgcolor"/></xsl:attribute>

              <td align="left"><a>
                <xsl:attribute name="href">viewDynamicAnalysisFile.php?id=<xsl:value-of select="id"/></xsl:attribute>
                <xsl:value-of select="name"/>
              </a></td>
              <td>
              <xsl:attribute name="class">
              <xsl:choose>
              <xsl:when test="status='Passed'">
              normal
              </xsl:when>
              <xsl:otherwise>
              error
              </xsl:otherwise>
              </xsl:choose>
              </xsl:attribute>
              <xsl:value-of select="status"/></td>

              <!-- the various defects are handled by this template -->
              <xsl:call-template name="defect-columns">
                <xsl:with-param name="context" select="."/>
              </xsl:call-template>

              <!-- Labels -->
              <td>
              <xsl:for-each select="labels/label">
              <xsl:if test="position() > 1">,
              <xsl:text disable-output-escaping="yes"> </xsl:text>
              </xsl:if>
              <nobr><xsl:value-of select="."/></nobr>
              </xsl:for-each>
              </td>

            </tr>
          </xsl:for-each>
        </table>

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

  <xsl:template name="defect-columns">
    <xsl:param name="context"/>
    <xsl:param name="index" select="0" />
    <xsl:param name="total" select="/cdash/numcolumns"/>

    <!-- Do something
    <td>Hi</td>
    -->

    <td>
      <xsl:for-each select="$context/defect">
        <xsl:if test="column = $index">
          <xsl:attribute name="class">warning</xsl:attribute>
          <xsl:value-of select="value"/>
        </xsl:if>
      </xsl:for-each>
    </td>

    <xsl:if test="not($index = $total)">
      <xsl:call-template name="defect-columns">
        <xsl:with-param name="index" select="$index + 1" />
        <xsl:with-param name="context" select="."/>
      </xsl:call-template>
    </xsl:if>
  </xsl:template>

</xsl:stylesheet>
