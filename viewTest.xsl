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
<h2>Testing started on <xsl:value-of select="cdash/build/testtime"/></h2>
<p><b>Site Name: </b><xsl:value-of select="cdash/build/site"/></p>
<p><b>Build Name: </b><xsl:value-of select="cdash/build/buildname"/></p><br/>
<h3>
  <xsl:value-of select="cdash/numPassed"/> passed, 
  <xsl:value-of select="cdash/numFailed"/> failed, 
  <xsl:value-of select="cdash/numNotRun"/> not run
</h3><br/>

<table cellspacing="0">
  <tr>
    <th>Name</th>
    <th>Status</th>
    <th>Time</th>
    <th>Details</th>
  </tr>
<xsl:for-each select="cdash/tests/test">
  <tr>
    <xsl:attribute name="class">
      <xsl:value-of select="class"/>
    </xsl:attribute>
    <td><a>
      <xsl:attribute name="href">
        <xsl:value-of select="summaryLink"/>
      </xsl:attribute>
      <xsl:value-of select="name"/>
    </a></td>
    <td>
      <xsl:attribute name="align">center</xsl:attribute>
      <xsl:attribute name="class">
        <xsl:value-of select="status"/>
      </xsl:attribute>
      <a>
 <xsl:attribute name="href">
   <xsl:value-of select="detailsLink"/>
 </xsl:attribute>
        <xsl:value-of select="status"/>
      </a>
    </td>
    <td align="right">
      <xsl:value-of select="execTime"/>
    </td>
    <td align="right">
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
