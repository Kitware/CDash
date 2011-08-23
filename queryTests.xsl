<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

<xsl:include href="filterdataTemplate.xsl"/>
<xsl:include href="header.xsl"/>
<xsl:include href="footer.xsl"/>

<xsl:include href="local/header.xsl"/>
<xsl:include href="local/footer.xsl"/>

<xsl:output method="xml" indent="yes"
  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
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
  <script src="javascript/cdashFilters.js" type="text/javascript" charset="utf-8"></script>
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
<h3>Query <xsl:value-of select="cdash/dashboard/projectname"/> Tests:
 <xsl:value-of select="count(cdash/builds/build)"/> matches</h3>

<!-- Filters? -->
<xsl:if test="count(cdash/filterdata) = 1">
  <xsl:call-template name="filterdata" select="."/>
  <br/>
</xsl:if>

<!-- Hide a div for javascript to know if time status is on -->
<xsl:if test="/cdash/project/showtesttime=1">
  <div id="showtesttimediv" style="display:none"></div>
</xsl:if>

<!-- Results -->
<table id="queryTestsTable" cellspacing="0" cellpadding="3" class="tabb">
<thead>
  <tr class="table-heading1">
    <th id="sort_0">Site</th>
    <th id="sort_1">Build Name</th>
    <th id="sort_2">Test Name</th>
    <th id="sort_3">Status</th>
    <xsl:if test="/cdash/project/showtesttime=1">
      <th id="sort_4">Time Status</th>
      <th id="sort_5">Time</th>
      <th id="sort_6">Details</th>
      <th id="sort_7" class="nob">Build Time</th>
    </xsl:if>
    <xsl:if test="/cdash/project/showtesttime!=1">
      <th id="sort_4">Time</th>
      <th id="sort_5">Details</th>
      <th id="sort_6" class="nob">Build Time</th>
    </xsl:if>
  </tr>
</thead>

<xsl:for-each select="cdash/builds/build">
  <tr>
    <td><a>
      <xsl:attribute name="href">
        <xsl:value-of select="siteLink"/>
      </xsl:attribute>
      <xsl:value-of select="site"/>
    </a></td>

    <td><a>
      <xsl:attribute name="href">
        <xsl:value-of select="buildSummaryLink"/>
      </xsl:attribute>
      <xsl:value-of select="buildName"/>
    </a></td>

    <td>
      <xsl:value-of select="testname"/>
    </td>

    <td align="center">
      <xsl:attribute name="class">
        <xsl:value-of select="statusclass"/>
      </xsl:attribute>
      <a>
      <xsl:attribute name="href">
        <xsl:value-of select="testDetailsLink"/>
      </xsl:attribute>
      <xsl:value-of select="status"/>
      </a>
    </td>

    <xsl:if test="/cdash/project/showtesttime=1">
    <td align="center">
      <xsl:attribute name="class">
        <xsl:value-of select="timestatusclass"/>
      </xsl:attribute>
      <a>
      <xsl:attribute name="href">
        <xsl:value-of select="testDetailsLink"/>
      </xsl:attribute>
      <xsl:value-of select="timestatus"/>
      </a>
    </td>
    </xsl:if>

    <td>
      <xsl:value-of select="time"/>
    </td>

    <td>
      <xsl:value-of select="details"/>
    </td>

    <td class="nob">
      <xsl:value-of select="buildstarttime"/>
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
