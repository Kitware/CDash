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
<h2>Testing started on <xsl:value-of select="cdash/build/testtime"/></h2>
<p><b>Site Name: </b><xsl:value-of select="cdash/build/site"/></p>
<p><b>Build Name: </b><xsl:value-of select="cdash/build/buildname"/></p><br/>
<h3>
<xsl:if test="cdash/onlypassed=1">
  <xsl:value-of select="cdash/numPassed"/> tests passed.
</xsl:if>
<xsl:if test="cdash/onlyfailed=1">
  <xsl:value-of select="cdash/numFailed"/> tests failed.
</xsl:if>
<xsl:if test="cdash/onlytimestatus=1">
  <xsl:value-of select="cdash/numTimeFailed"/> tests failed.
</xsl:if>
<xsl:if test="cdash/onlypassed!=1 and cdash/onlyfailed!=1">
  <xsl:value-of select="cdash/numPassed"/> passed, 
  <xsl:value-of select="cdash/numFailed"/> failed,
  <xsl:value-of select="cdash/numTimeFailed"/> failed for timing,
  <xsl:value-of select="cdash/numNotRun"/> not run.
</xsl:if>
</h3><br/>

<table id="viewTestTable" cellspacing="0" class="tabb">
<!-- <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_1</xsl:attribute> -->
<thead> 
  <tr class="table-heading1">
    <th id="sort_0">Name</th>
    <th id="sort_1">Status</th>
<xsl:if test="cdash/project/showtesttime=1">    
    <th id="sort_2">Time Status</th>
    <th id="sort_3">Time</th>
    <th id="sort_4" class="nob">Details</th>
</xsl:if>        
<xsl:if test="cdash/project/showtesttime=0">    
    <th id="sort_2">Time</th>
    <th id="sort_3" class="nob">Details</th>
</xsl:if>        
  </tr>
</thead>
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
        <xsl:value-of select="statusclass"/>
      </xsl:attribute>
      <a>
 <xsl:attribute name="href">
   <xsl:value-of select="detailsLink"/>
 </xsl:attribute>
        <xsl:value-of select="status"/>
      </a>
    </td>
    <xsl:if test="/cdash/project/showtesttime=1">          
     <td>
      <xsl:attribute name="align">center</xsl:attribute>
      <xsl:attribute name="class">
        <xsl:value-of select="timestatusclass"/>
      </xsl:attribute>
      <a>
 <xsl:attribute name="href">
   <xsl:value-of select="detailsLink"/>
 </xsl:attribute>
        <xsl:value-of select="timestatus"/>
      </a>
    </td>
</xsl:if>  
    <td align="right">
      <xsl:value-of select="execTime"/>
    </td>
    <td align="right" class="nob">
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
