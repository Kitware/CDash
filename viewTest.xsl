<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
  
<xsl:include href="header.xsl"/>
<xsl:include href="footer.xsl"/>

<!-- Local includes -->
<xsl:include href="local/footer.xsl"/>
<xsl:include href="local/header.xsl"/> 
   
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
<h3>Testing started on <xsl:value-of select="cdash/build/testtime"/></h3>
<table border="0">
<tr><td align="right"><b>Site Name:</b></td><td><xsl:value-of select="cdash/build/site"/></td></tr>
<tr><td align="right"><b>Build Name:</b></td><td><xsl:value-of select="cdash/build/buildname"/></td></tr>
<tr><td align="right"><b>Total time:</b></td><td><xsl:value-of select="cdash/tests/totaltime"/></td></tr>
<!-- Display Operating System information  -->
<xsl:if test="cdash/build/osname">
  <tr><td align="right"><b>OS Name:</b></td><td><xsl:value-of select="cdash/build/osname"/></td></tr>
</xsl:if>
<xsl:if test="cdash/build/osplatform">
  <tr><td align="right"><b>OS Platform:</b></td><td><xsl:value-of select="cdash/build/osplatform"/></td></tr>
</xsl:if>
<xsl:if test="cdash/build/osrelease">
  <tr><td align="right"><b>OS Release:</b></td><td><xsl:value-of select="cdash/build/osrelease"/></td></tr>
</xsl:if>
<xsl:if test="cdash/build/osversion">
  <tr><td align="right"><b>OS Version:</b></td><td><xsl:value-of select="cdash/build/osversion"/></td></tr>
</xsl:if>  
  
<!-- Display Compiler information  -->
<xsl:if test="cdash/build/compilername">
  <tr><td align="right"><b>Compiler Name:</b></td><td><xsl:value-of select="cdash/build/compilername"/></td></tr>
</xsl:if>
<xsl:if test="cdash/build/compilerversion">
  <tr><td align="right"><b>Compiler Version:</b></td><td><xsl:value-of select="cdash/build/compilerversion"/></td></tr>
</xsl:if>  

</table>

<h3>
<xsl:if test="cdash/onlypassed=1">
  <xsl:value-of select="cdash/numPassed"/> tests passed.
</xsl:if>
<xsl:if test="cdash/onlyfailed=1">
  <xsl:value-of select="cdash/numFailed"/> tests failed.
</xsl:if>
<xsl:if test="cdash/onlynotrun=1">
  <xsl:value-of select="cdash/numNotRun"/> tests not run.
</xsl:if>
<xsl:if test="cdash/onlytimestatus=1">
  <xsl:value-of select="cdash/numTimeFailed"/> tests failed for timing reasons.
</xsl:if>
<xsl:if test="cdash/onlypassed!=1 and cdash/onlyfailed!=1">
  <xsl:value-of select="cdash/numPassed"/> passed, 
  <xsl:value-of select="cdash/numFailed"/> failed,
  <xsl:value-of select="cdash/numTimeFailed"/> failed for timing,
  <xsl:value-of select="cdash/numNotRun"/> not run.
</xsl:if>
</h3><br/>

<!-- Hide a div for javascript to know if time status is on -->
<xsl:if test="/cdash/project/showtesttime=1">   
<div id="showtesttimediv" style="display:none"></div>
</xsl:if>  

<table id="viewTestTable" cellspacing="0" class="tabb">
<!-- <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_1</xsl:attribute> -->
<thead> 
  <tr class="table-heading1">
    <th id="sort_0">Name</th>
    <th id="sort_1">Status</th>
<xsl:if test="cdash/project/showtesttime=1">    
    <th id="sort_2">Time Status</th>
    <th id="sort_3">Time</th>
    <xsl:if test="/cdash/build/displaylabels=0 and cdash/displaydetails=1">
      <th id="sort_4" class="nob">Details</th>
    </xsl:if>
    <xsl:if test="/cdash/build/displaylabels=1">
      <xsl:if test="cdash/displaydetails=1">
        <th id="sort_4" >Details</th>
      </xsl:if>
      <th class="nob">Labels</th>
    </xsl:if>
</xsl:if>        
<xsl:if test="cdash/project/showtesttime=0">    
    <th id="sort_2">Time (s)</th>
    <xsl:if test="/cdash/build/displaylabels=0 and cdash/displaydetails=1">
      <th id="sort_3" class="nob">Details</th>
    </xsl:if>
    <xsl:if test="/cdash/build/displaylabels=1">
      <xsl:if test="cdash/displaydetails=1">
        <th id="sort_3">Details</th>
      </xsl:if>
      <th class="nob">Labels</th>
    </xsl:if>
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
  
    <xsl:if test="/cdash/displaydetails=1">
    <td>
      <xsl:value-of select="details"/>
    </td>
    </xsl:if>
    
    <xsl:if test="/cdash/build/displaylabels=1">
    <td align="left" class="nob">
      <xsl:for-each select="labels/label">
        <xsl:if test="position() > 1">,
        <xsl:text disable-output-escaping="yes"> </xsl:text>
        </xsl:if>
        <nobr><xsl:value-of select="."/></nobr>
      </xsl:for-each>
    </td>
    </xsl:if>  
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

<font size="1">Generated in <xsl:value-of select="/cdash/generationtime"/> seconds</font>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
