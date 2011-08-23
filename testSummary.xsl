<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

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
  <link rel="StyleSheet" type="text/css">
    <xsl:attribute name="href">
      <xsl:value-of select="cdash/cssfile"/>
    </xsl:attribute>
  </link>
  <xsl:call-template name="headscripts"/>
   <!-- Include JavaScript -->
  <script src="javascript/cdashTestGraph.js" type="text/javascript" charset="utf-8"></script>

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
<h3>Testing summary for
<u><xsl:value-of select="cdash/testName"/></u>
 performed between <xsl:value-of select="cdash/builds/teststarttime"/> and <xsl:value-of select="cdash/builds/testendtime"/>
</h3>

<!-- Failure Graph -->
<a>
<xsl:attribute name="href">javascript:showtestfailuregraph_click('<xsl:value-of select="/cdash/dashboard/projectid"/>','<xsl:value-of select="/cdash/testName"/>','<xsl:value-of select="/cdash/builds/currentstarttime"/>')</xsl:attribute>
[Show Test Failure Trend]
</a>
<div id="testfailuregraphoptions"></div>
<div id="testfailuregraph"></div>
<center>
<div id="testfailuregrapholder"></div>
</center>
<br/>
<!-- Test Summary table -->
<table id="testSummaryTable" cellspacing="0" cellpadding="3" class="tabb">
<thead>
  <tr class="table-heading1">
    <th id="sort_0">Site</th>
    <th id="sort_1">Build Name</th>
    <th id="sort_2">Build Stamp</th>
    <th id="sort_3">Status</th>
    <th id="sort_4">Time (s)</th>
    <th id="sort_5" class="nob">Detail</th>
  </tr>
</thead>

<xsl:for-each select="cdash/builds/build">
  <tr>
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
