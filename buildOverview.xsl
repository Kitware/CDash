<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
  <xsl:include href="header.xsl"/>
  <xsl:include href="footer.xsl"/>
  <xsl:output method="html" encoding="iso-8859-1"/>
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
<h3>
 Build summary for <u><xsl:value-of select="cdash/dashboard/projectname"/></u> starting at
<xsl:value-of select="cdash/dashboard/startdate"/>
</h3>

<p>
<xsl:for-each select="cdash/sourcefile">
<div class="title-divider"><xsl:value-of select="name"/></div>

  <!-- Display the errors -->
  <xsl:if test="count(error)>0">
    <h3>Errors:</h3>
    <xsl:for-each select="error">
    <b><xsl:value-of select="buildname"/>: </b>
    <xsl:for-each select="text">
      <xsl:value-of select="."/><br/>
    </xsl:for-each>
    </xsl:for-each>
  </xsl:if>
  
  <!-- Display the warnings -->
  <xsl:if test="count(warning)>0">  
    <h3>Warnings:</h3>
    <xsl:for-each select="warning">
    <b><xsl:value-of select="buildname"/>: </b>
    <xsl:for-each select="text">
      <xsl:value-of select="."/><br/>
    </xsl:for-each>
    </xsl:for-each>
  </xsl:if>
<br/>
</xsl:for-each>  
</p>

<br/>
<br/>
    <xsl:call-template name="footer"/>
  </body>
  </html>
  </xsl:template>
</xsl:stylesheet>
