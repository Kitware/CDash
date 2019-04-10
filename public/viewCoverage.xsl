<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="filterdataTemplate.xsl"/>
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
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
       <xsl:call-template name="headscripts"/>
       <script src="js/cdashCoverageGraph.js" type="text/javascript" charset="utf-8"></script>
       <script src="js/cdashFilters.js" type="text/javascript" charset="utf-8"></script>
       <script src="js/cdashViewCoverage.js" type="text/javascript" charset="utf-8"></script>
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

<h3>Coverage started on <xsl:value-of select="cdash/coverage/starttime"/></h3>

<table width="100%">
   <tr>
      <td>
         <table border="0" cellpadding="3" cellspacing="1" bgcolor="#0000aa" width="300">
            <tr>
               <th colspan="2" class="table-heading1">Coverage Summary</th>
            </tr>
            <tr class="treven">
               <td align="left" width="60%"> Total Coverage</td>
               <td align="center">
               <xsl:attribute name="class">
                <xsl:choose>
                  <xsl:when test="cdash/coverage/percentcoverage >= cdash/coverage/percentagegreen">
                    normal
                    </xsl:when>
                  <xsl:otherwise>
                    warning
                   </xsl:otherwise>
                </xsl:choose>
                </xsl:attribute>
               <xsl:value-of select="cdash/coverage/percentcoverage"/>
               </td>
            </tr>
            <tr class="trodd">

               <td align="left">Tested lines</td>
               <td align="right"><xsl:value-of select="cdash/coverage/loctested"/></td>
            </tr>
            <tr class="treven">
               <td align="left">Untested lines</td>
               <td align="right"><xsl:value-of select="cdash/coverage/locuntested"/></td>

            </tr>
            <xsl:if test="(cdash/coverage/branchstested + cdash/coverage/branchsuntested) > 0">
              <tr class="trodd">

                 <td align="left">Tested branches</td>
                 <td align="right"><xsl:value-of select="cdash/coverage/branchstested"/></td>
              </tr>
              <tr class="treven">
                 <td align="left">Untested branches</td>
                 <td align="right"><xsl:value-of select="cdash/coverage/branchsuntested"/></td>

              </tr>
            </xsl:if>
            <tr class="trodd">
               <td align="left">Files Covered</td>
               <td align="center"><xsl:value-of select="cdash/coverage/totalcovered"/> of <xsl:value-of select="cdash/coverage/totalfiles"/></td>
            </tr>
            <tr class="treven">
               <td align="left">Files Satisfactorily Covered</td>

               <td align="right"><xsl:value-of select="cdash/coverage/totalsatisfactorilycovered"/></td>
            </tr>
            <tr class="trodd">
               <td align="left">Files Unsatisfactorily Covered</td>
               <td align="right"><xsl:value-of select="cdash/coverage/totalunsatisfactorilycovered"/></td>
            </tr>
         </table>

      </td>

      <xsl:if test="count(cdash/coverage/labels/label) > 0">
      <td align="top">
         <table id="viewCoverage" border="0" cellpadding="3" cellspacing="1" bgcolor="#0000aa">
         <xsl:attribute name="class">tabb <xsl:value-of select="/cdash/sortlist"/></xsl:attribute>
         <thead>
            <tr>
               <th colspan="2" class="table-heading1">Summary By Label</th>
            </tr>

            <tr class="table-heading1">
               <th id="sort_0">Label</th>
               <th id="sort_1">Percent Coverage</th>
            </tr>
         </thead>

            <xsl:for-each select="cdash/coverage/labels/label">
            <tr class="treven">
               <td align="right"><xsl:value-of select="./name"/></td>
               <td align="center">
               <xsl:attribute name="class">
                <xsl:choose>
                  <xsl:when test="./percentcoverage >= /cdash/coverage/percentagegreen">
                    normal
                  </xsl:when>
                  <xsl:otherwise>
                    warning
                  </xsl:otherwise>
                </xsl:choose>
                </xsl:attribute>
               <xsl:value-of select="./percentcoverage"/>
               </td>
            </tr>
            </xsl:for-each>
         </table>
      </td>
      </xsl:if>

      <td valign="top" align="right">
         <table border="0" cellpadding="3" cellspacing="1" bgcolor="#0000aa" width="350">
            <tr class="table-heading1">
               <th>Coverage Legend</th>
            </tr>
            <tr>
               <td class="normal" align="center">
                  Satisfactory coverage
               </td>
            </tr>
            <tr>
               <td class="warning" align="center">
                  Unsatisfactory coverage
               </td>
            </tr>
            <tr>
               <td class="error" align="center">
                  Dangerously low coverage
               </td>
            </tr>
         </table>
      </td>
   </tr>
</table>

<!-- Graph -->
<img src="img/graph.png" title="graph"/>
<a>
<xsl:attribute name="href">javascript:showcoveragegraph_click(<xsl:value-of select="cdash/buildid"/>)</xsl:attribute>
Show coverage over time
</a>
<div id="graphoptions"></div>
<div id="graph"></div>
<center>
<div id="grapholder"></div>
</center>
<br/>

<!-- Links -->
<a><xsl:attribute name="href">javascript:filters_preserve_link(-1)</xsl:attribute>Directories (<xsl:value-of select="cdash/coveragefilestatus/directories"/>)</a> |
<a><xsl:attribute name="href">javascript:filters_preserve_link(0)</xsl:attribute>No Executable Code (<xsl:value-of select="cdash/coveragefilestatus/no"/>)</a> |
<a><xsl:attribute name="href">javascript:filters_preserve_link(1)</xsl:attribute>Zero (<xsl:value-of select="cdash/coveragefilestatus/zero"/>)</a> |
<a><xsl:attribute name="href">javascript:filters_preserve_link(2)</xsl:attribute>Low (<xsl:value-of select="cdash/coveragefilestatus/low"/>)</a> |
<a><xsl:attribute name="href">javascript:filters_preserve_link(3)</xsl:attribute>Medium (<xsl:value-of select="cdash/coveragefilestatus/medium"/>)</a> |
<a><xsl:attribute name="href">javascript:filters_preserve_link(4)</xsl:attribute>Satisfactory (<xsl:value-of select="cdash/coveragefilestatus/satisfactory"/>)</a> |
<a><xsl:attribute name="href">javascript:filters_preserve_link(5)</xsl:attribute>Complete (<xsl:value-of select="cdash/coveragefilestatus/complete"/>)</a> |
<a><xsl:attribute name="href">javascript:filters_preserve_link(6)</xsl:attribute>All (<xsl:value-of select="cdash/coveragefilestatus/all"/>)</a>
<br/>

<div id="labelshowfilters">
<a id="label_showfilters" href="javascript:filters_toggle();">
<xsl:if test="cdash/filterdata/showfilters = 0">Show Filters<xsl:if test="cdash/filtercount > 0"> (<xsl:value-of select="cdash/filtercount"/>)</xsl:if></xsl:if>
<xsl:if test="cdash/filterdata/showfilters != 0">Hide Filters</xsl:if>
</a>
</div>

<!-- Filters? -->
<xsl:if test="count(cdash/filterdata) = 1">
  <xsl:call-template name="filterdata" select="."/>
</xsl:if>

<!--  Coverage table -->
<input type="hidden" name="coverageType" id="coverageType">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coverage/coveragetype"/>
</xsl:attribute>
</input>
<input type="hidden" name="buildid" id="buildid">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coverage/buildid"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageStatus" id="coverageStatus">
<xsl:attribute name="value">
   <xsl:value-of select="/cdash/coverage/status"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageDir" id="coverageDir">
<xsl:attribute name="value">
   <xsl:value-of select="/cdash/coverage/dir"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNDirectories" id="coverageNDirectories">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/directories"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNNo" id="coverageNNo">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/no"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNZero" id="coverageNZero">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/zero"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNLow" id="coverageNLow">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/low"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNMedium" id="coverageNMedium">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/medium"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNSatisfactory" id="coverageNSatisfactory">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/satisfactory"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNComplete" id="coverageNComplete">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/complete"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageNAll" id="coverageNAll">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coveragefilestatus/all"/>
</xsl:attribute>
</input>
<input type="hidden" name="coverageMetricError" id="coverageMetricError">
<xsl:attribute name="value">
   <xsl:value-of select="/cdash/coverage/metricerror"/></xsl:attribute>
</input>
<input type="hidden" name="coverageMetricPass" id="coverageMetricPass">
<xsl:attribute name="value">
   <xsl:value-of select="/cdash/coverage/metricpass"/>
</xsl:attribute>
</input>
<input type="hidden" name="userid" id="userid">
<xsl:attribute name="value">
   <xsl:value-of select="/cdash/coverage/userid"/>
</xsl:attribute>
</input>
<input type="hidden" name="displaylabels" id="displaylabels">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coverage/displaylabels"/>
</xsl:attribute>
</input>

<table id="coverageTable" cellspacing="0" cellpadding="3">
<thead>
  <tr class="table-heading1">
    <xsl:choose>
    <xsl:when test="cdash/coverage/status=-1">
      <th width="50%">Directory</th>
    </xsl:when>
    <xsl:otherwise>
       <th width="50%">Filename</th>
    </xsl:otherwise>
    </xsl:choose>
  <th width="10%" align="center">Status</th>
    <th align="center">Percentage</th>

    <!-- gcov -->
    <xsl:if test="cdash/coverage/coveragetype='gcov'">
      <th width="10%" align="center">Lines not covered</th>
      <xsl:if test="(cdash/coverage/branchstested + cdash/coverage/branchsuntested) > 0">
        <th width="10%" align="center">Branch percentage</th>
        <th width="10%" align="center">Branches not covered</th>
      </xsl:if>  
      <th width="10%" align="center">Priority</th>
      <xsl:if test="/cdash/coverage/userid!=0">
         <th>Author</th>
      </xsl:if>
      <xsl:if test="cdash/coverage/displaylabels=1">
          <th>Labels</th>
       </xsl:if>
    </xsl:if>

    <!-- bullseye -->
    <xsl:if test="cdash/coverage/coveragetype='bullseye'">
        <th width="10%" align="center">Branch Points not covered</th>
        <th width="10%" align="center">Functions not covered</th>
        <th>Priority</th>
        <xsl:if test="/cdash/coverage/userid!=0">
          <th>Author</th>
        </xsl:if>
         <xsl:if test="cdash/coverage/displaylabels=1">
          <th>Labels</th>
        </xsl:if>
    </xsl:if>
  </tr>
</thead>
</table>

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
