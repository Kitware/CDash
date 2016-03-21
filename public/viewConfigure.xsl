<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

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
        <table border="0">
          <tr><td align="left"><b>Site: </b><a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="cdash/build/siteid"/></xsl:attribute>
          <xsl:value-of select="cdash/build/site"/></a></td></tr>
          <tr><td align="left"><b>Build Name: </b><xsl:value-of select="cdash/build/buildname"/></td></tr>
          <tr><td align="left"><b>Configure Command: </b><xsl:value-of select="cdash/configure/command"/></td></tr>
          <tr><td align="left"><b>Configure Return Value: </b><xsl:value-of select="cdash/configure/status"/></td></tr>
          <tr><td align="left"><b>Configure Output:</b></td></tr>
          <tr><td align="left"><pre><xsl:value-of select="cdash/configure/output"/></pre></td></tr>
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
</xsl:stylesheet>
