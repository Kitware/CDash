<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
<xsl:include href="header.xsl"/>
<xsl:include href="footer.xsl"/>
<xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
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
  <xsl:call-template name="headscripts"/> 
</head>
<body bgcolor="#ffffff">
<xsl:call-template name="header"/>
<br/><br/>
<h3>Testing summary for 
<u><xsl:value-of select="cdash/testName"/></u>
 performed on <xsl:value-of select="cdash/dashboard/date"/>
</h3>
<table id="testSummaryTable" cellspacing="0" cellpadding="3" class="tabb">
<thead> 
  <tr class="table-heading1">
    <th id="sort_0">Site</th>
    <th id="sort_1">Build Name</th>
    <th id="sort_2">Build Stamp</th>
    <th id="sort_3">Status</th>
    <th id="sort_4">Time</th>
    <th id="sort_5" class="nob">Detail</th>
  </tr>
</thead> 

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
        <xsl:value-of select="statusclass"/>
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
    <td class="nob">
      <xsl:value-of select="details"/>
    </td>
  </tr>
</xsl:for-each>
</table>
<br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
<font size="1">Generated in <xsl:value-of select="/cdash/generationtime"/> seconds</font>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
