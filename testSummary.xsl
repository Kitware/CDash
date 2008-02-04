<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
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
<br/><br/>
<h3>Testing summary for 
<u><xsl:value-of select="cdash/testName"/></u>
 performed on <xsl:value-of select="cdash/dashboard/date"/>
</h3>
<table cellspacing="0" cellpadding="3">
  <tr>
    <th>Site</th>
    <th>Build Name</th>
    <th>Build Stamp</th>
    <th>Status</th>
    <th>Time</th>
    <th>Detail</th>
  </tr>
<xsl:for-each select="cdash/builds/build">
  <tr>
    <xsl:attribute name="class">
      <xsl:value-of select="class"/>
    </xsl:attribute>
    <td>
      <xsl:value-of select="site"/>
    </td>

    <td><a>
      <xsl:attribute name="href">
        <xsl:value-of select="buildLink"/>
      </xsl:attribute>
      <xsl:value-of select="buildName"/>
    </a></td>
    <td>
      <xsl:value-of select="buildStamp"/>
    </td>
    <td>
      <xsl:attribute name="class">
        <xsl:value-of select="status"/>
      </xsl:attribute>
      <a>
      <xsl:attribute name="href">
        <xsl:value-of select="testLink"/>
      </xsl:attribute>
      <xsl:value-of select="status"/>
      </a>
    </td>
    <td>
      <xsl:value-of select="time"/>
    </td>
    <td>
      <xsl:value-of select="details"/>
    </td>
  </tr>
</xsl:for-each>
</table>
<br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
