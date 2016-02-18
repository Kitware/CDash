<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/>

   <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headerback.xsl"/>

 <!-- HEADER -->
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
  <xsl:call-template name="headerback_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headerback"/>
</xsl:otherwise>
</xsl:choose>

<br/>

<!-- If a project has been selected -->
<xsl:if test="count(cdash/project)>0">
<form method="post">
Date Range:
<select onChange="form.submit()" name="range">
<option value="thisweek">
<xsl:if test="/cdash/datarange='thisweek'"><xsl:attribute name="selected"></xsl:attribute></xsl:if>
This Week
</option>
<option value="lastweek">
<xsl:if test="/cdash/datarange='lastweek'"><xsl:attribute name="selected"></xsl:attribute></xsl:if>
Last Week</option>
<option value="thismonth">
<xsl:if test="/cdash/datarange='thismonth'"><xsl:attribute name="selected"></xsl:attribute></xsl:if>
This Month</option>
<option value="lastmonth">
<xsl:if test="/cdash/datarange='lastmonth'"><xsl:attribute name="selected"></xsl:attribute></xsl:if>
Last Month</option>
<option value="thisyear">
<xsl:if test="/cdash/datarange='thisyear'"><xsl:attribute name="selected"></xsl:attribute></xsl:if>
This Year</option>
<option value="lastyear">
<xsl:if test="/cdash/datarange='lastyear'"><xsl:attribute name="selected"></xsl:attribute></xsl:if>
Last Year</option>
</select>
</form>
<br/>

<table id="userStatistics" cellspacing="0" width="100%">
<xsl:attribute name="class">tabb <xsl:value-of select="/cdash/sortlist"/></xsl:attribute>
<thead>
  <tr class="table-heading1">
    <th id="sort_0" width="20%">Developer</th>
    <th id="sort_1" width="10%">Score</th>
    <th id="sort_2" width="10%">Updated Files</th>
    <th id="sort_3" width="10%">Failed Errors</th>
    <th id="sort_4" width="10%">Fixed Errors</th>
    <th id="sort_5" width="10%">Failed Warnings</th>
    <th id="sort_6" width="10%">Fixed Warnings</th>
    <th id="sort_7" width="10%">Failed Tests</th>
    <th id="sort_8" class="nob" width="10%">Fixed Tests</th>
  </tr>
</thead>
<xsl:for-each select="cdash/user">
  <tr>
   <td align="center"><xsl:value-of select="name"/></td>
   <td align="center">
     <xsl:attribute name="class">
       <xsl:choose><xsl:when test="score &lt; 0">error</xsl:when></xsl:choose>
       <xsl:choose><xsl:when test="score >= 0">normal</xsl:when></xsl:choose>
     </xsl:attribute>
     <xsl:choose><xsl:when test="score >= 0"><image src="img/smiley-good.png"/></xsl:when></xsl:choose>
     <xsl:choose><xsl:when test="score >= 0.5"><image src="img/smiley-good.png"/></xsl:when></xsl:choose>
     <xsl:choose><xsl:when test="score >= 0.8"><image src="img/smiley-good.png"/></xsl:when></xsl:choose>
     <xsl:choose><xsl:when test="score &lt; 0"><image src="img/smiley-bad.png"/></xsl:when></xsl:choose>
     <xsl:choose><xsl:when test="score &lt; -0.3"><image src="img/smiley-bad.png"/></xsl:when></xsl:choose>
     <xsl:choose><xsl:when test="score &lt; -0.4"><image src="img/smiley-bad.png"/></xsl:when></xsl:choose>
     <xsl:choose><xsl:when test="score &lt; -0.7"><image src="img/smiley-bad.png"/></xsl:when></xsl:choose>
     <a href="" style="display:none"><xsl:value-of select="score"/></a>
   </td>
   <td align="center"><xsl:value-of select="totalupdatedfiles"/></td>
   <td align="center">
     <xsl:attribute name="class">
       <xsl:choose><xsl:when test="failed_errors > 0">error</xsl:when></xsl:choose>
     </xsl:attribute>
     <xsl:value-of select="failed_errors"/>
   </td>
   <td align="center">
     <xsl:attribute name="class">
       <xsl:choose><xsl:when test="fixed_errors > 0">normal</xsl:when></xsl:choose>
     </xsl:attribute>
     <xsl:value-of select="fixed_errors"/>
   </td>
   <td align="center">
     <xsl:attribute name="class">
       <xsl:choose><xsl:when test="failed_warnings > 0">error</xsl:when></xsl:choose>
     </xsl:attribute>
     <xsl:value-of select="failed_warnings"/>
   </td>
   <td align="center"><xsl:attribute name="class">
       <xsl:choose><xsl:when test="fixed_warnings > 0">normal</xsl:when></xsl:choose>
     </xsl:attribute>
     <xsl:value-of select="fixed_warnings"/>
   </td>
   <td align="center">
     <xsl:attribute name="class">
       <xsl:choose><xsl:when test="failed_tests > 0">error</xsl:when></xsl:choose>
     </xsl:attribute>
   <xsl:value-of select="failed_tests"/>
   </td>
   <td align="center">
     <xsl:attribute name="class">
       <xsl:choose><xsl:when test="fixed_tests > 0">normal</xsl:when></xsl:choose>
     </xsl:attribute>
   <xsl:value-of select="fixed_tests"/>
   </td>

  </tr>
</xsl:for-each>
</table>
<br/>
   </xsl:if> <!-- if we have a project selected -->


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
