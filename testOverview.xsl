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
<xsl:value-of select="cdash/dashboard/projectname"/>
 tests that didn't run cleanly on 
<xsl:value-of select="cdash/dashboard/date"/>
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
<!--
<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
<xsl:for-each select="cdash/builds/build">
  <tr>
    <xsl:attribute name="class">
      <xsl:value-of select="class"/>
    </xsl:attribute>
    <td>
      <xsl:value-of select="number"/>
    </td>

    <td>
      <xsl:value-of select="site"/>
    </td>
    <td>
      <xsl:value-of select="buildName"/>
    </td>
    <td>
      <xsl:value-of select="buildStamp"/>
    </td>
  </tr>
-->

    <xsl:call-template name="footer"/>
  </body>
  </html>
  </xsl:template>
</xsl:stylesheet>
