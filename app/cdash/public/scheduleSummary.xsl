<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="headscripts.xsl"/>
  <xsl:include href="headeradminproject.xsl" />

  <xsl:include href="local/footer.xsl"/>
  <xsl:include href="local/headscripts.xsl"/>
  <xsl:include href="local/headeradminproject.xsl" />

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>
  <xsl:template match="/">
      <html>
      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
    <link rel="shortcut icon" href="favicon.ico"/>
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>
        <xsl:call-template name="headscripts"/>
        <script src="js/cdashClient.js" type="text/javascript" charset="utf-8"></script>
      </head>

<body>
<xsl:call-template name="headeradminproject"/>

<!-- Message -->

<h3>Job Status: <xsl:value-of select="/cdash/status" /></h3>

<xsl:if test="count(/cdash/build)>0">
<h3>Builds Submitted For This Job</h3>
<xsl:for-each select="/cdash/build">
  <a><xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="id" /></xsl:attribute><xsl:value-of select="id" /></a><br />
</xsl:for-each>
</xsl:if>

<!-- FOOTER -->
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
