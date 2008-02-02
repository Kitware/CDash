<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
  <xsl:include href="header.xsl"/>
  <xsl:include href="footer.xsl"/>
  <xsl:output method="html"/>
  <xsl:template match="/">
  <html>
  <head>
    <title><xsl:value-of select="cdash/title"/></title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="StyleSheet" type="text/css">
      <xsl:attribute name="href">
        <xsl:value-of select="cdash/cssfile"/>
      </xsl:attribute>
    </link>
  </head>
  <body bgcolor="#ffffff">
    <xsl:call-template name="header"/>
    <br/>
<h3>List of 
<u><xsl:value-of select="cdash/dashboard/projectname"/></u>
 tests that did not run cleanly on 
<xsl:value-of select="cdash/dashboard/startdate"/>
</h3>

<p>
<xsl:for-each select="cdash/tests/section">
<h3><xsl:value-of select="sectionName"/></h3>
<xsl:for-each select="test">
<a>
  <xsl:attribute name="href">
  <xsl:value-of select="summaryLink"/>
  </xsl:attribute><xsl:value-of select="testName"/></a>
<xsl:text disable-output-escaping="yes"> </xsl:text>
</xsl:for-each>
</xsl:for-each>
</p>

<br/>
<br/>
    <xsl:call-template name="footer"/>
  </body>
  </html>
  </xsl:template>
</xsl:stylesheet>
