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
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
       <xsl:call-template name="headscripts"/>
       <script src="javascript/cdashCoverageGraph.js" type="text/javascript" charset="utf-8"></script>
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
      <td valign="Top" align="right">
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
<img src="images/graph.png" title="graph"/>
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
<a><xsl:attribute name="href">viewCoverage.php?buildid=<xsl:value-of select="/cdash/coverage/buildid"/></xsl:attribute>Low (<xsl:value-of select="cdash/coveragefilestatus/low"/>)</a> |
<a><xsl:attribute name="href">viewCoverage.php?buildid=<xsl:value-of select="/cdash/coverage/buildid"/>&#38;status=1</xsl:attribute>Medium (<xsl:value-of select="cdash/coveragefilestatus/medium"/>)</a> |
<a><xsl:attribute name="href">viewCoverage.php?buildid=<xsl:value-of select="/cdash/coverage/buildid"/>&#38;status=2</xsl:attribute>Satisfactory (<xsl:value-of select="cdash/coveragefilestatus/satisfactory"/>)</a>
<br/>

<!--  Coverage table -->
<input type="hidden" name="coverageType" id="coverageType">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coverage/coveragetype"/>
</xsl:attribute>
</input>

<table id="coverageTable" cellspacing="0" cellpadding="3">
 <xsl:attribute name="class">
   tabb {sortlist: [[1,0],[2,0]]}
</xsl:attribute>

<thead>
  <tr class="table-heading1">
    <th id="sort_0">Filename</th>
    <th id="sort_1">Status</th>
    <th id="sort_2">Percentage</th>

    <!-- gcov -->
    <xsl:if test="cdash/coverage/coveragetype='gcov'">
      <th id="sort_3">Line not covered</th>
      <th id="sort_4">Priority</th>
      <xsl:if test="/cdash/coverage/userid!=0">
         <th id="sort_5">Author</th>
      </xsl:if>
      <xsl:if test="count(//labels/label) > 0">
          <th id="sort_6">Labels</th>
       </xsl:if>
    </xsl:if>

    <!-- bullseye -->
    <xsl:if test="cdash/coverage/coveragetype='bullseye'">
        <th id="sort_3">Branch Points not covered</th>
        <th id="sort_4">Functions not covered</th>
        <th id="sort_5">Priority</th>
        <xsl:if test="/cdash/coverage/userid!=0">
          <th id="sort_6">Author</th>
        </xsl:if>
        <xsl:if test="count(//labels/label) > 0">
          <th id="sort_7">Labels</th>
        </xsl:if>
    </xsl:if>
  </tr>
</thead>

 <xsl:for-each select="cdash/coveragefile">
 <tr>
   <td align="left">
     <xsl:if test="covered=0">
       <xsl:value-of select="fullpath"/>
     </xsl:if>
     <xsl:if test="covered=1">
      <a>
      <xsl:attribute name="href">viewCoverageFile.php?buildid=<xsl:value-of select="/cdash/coverage/buildid"/>&#38;fileid=<xsl:value-of select="fileid"/></xsl:attribute>
      <xsl:value-of select="fullpath"/>
      </a>
     </xsl:if>
    </td>
    <td align="center">
      <xsl:choose>
          <xsl:when test="covered=0 or coveragemetric &lt; /cdash/coverage/metricerror">
             Low
           </xsl:when>
         <xsl:when test="covered=1 and coveragemetric >= /cdash/coverage/metricpass">
            Satisfactory
            </xsl:when>
          <xsl:otherwise>
            Medium
           </xsl:otherwise>
        </xsl:choose>
    </td>
    <td>
      <div style="position:relative; width: 190px;">
       <div style="position:relative; float:left;
       width: 123px; height: 12px; background: #bdbdbd url('images/progressbar.gif') top left no-repeat;">
       <div>
         <xsl:attribute name="style">height: 12px;margin-left:1px;
         <xsl:choose>
          <xsl:when test="coveragemetric &lt; /cdash/coverage/metricerror">
            background: #bdbdbd url('images/progressbg_red.gif') top left no-repeat;
            </xsl:when>
         <xsl:when test="coveragemetric >= /cdash/coverage/metricpass">
            background: #bdbdbd url('images/progressbg_green.gif') top left no-repeat;
            </xsl:when>
          <xsl:otherwise>
            background: #bdbdbd url('images/progressbg_orange.gif') top left no-repeat;
           </xsl:otherwise>
        </xsl:choose>

         width:<xsl:value-of select="percentcoveragerounded"/>%;</xsl:attribute>
       </div>
       </div>
       <div class="percentvalue" style="position:relative; float:left; margin-left:10px"><xsl:value-of select="percentcoverage"/>%</div>
      </div>
    </td>

       <!-- gcov -->
      <xsl:if test="/cdash/coverage/coveragetype='gcov'">
       <td align="center">
        <xsl:if test="covered>0">
       <xsl:attribute name="class">
         <xsl:choose>
           <xsl:when test="coveragemetric &lt; /cdash/coverage/metricerror">
             error
             </xsl:when>
          <xsl:when test="coveragemetric >= /cdash/coverage/metricpass">
             normal
             </xsl:when>
           <xsl:otherwise>
             warning
            </xsl:otherwise>
         </xsl:choose>
       </xsl:attribute>
       <xsl:value-of select="locuntested"/>/<xsl:value-of select="totalloc"/>
      </xsl:if>
       <xsl:if test="covered=0">
         <xsl:attribute name="class">error</xsl:attribute>
         <xsl:value-of select="locuntested"/>
       </xsl:if>
     </td>
     </xsl:if>

     <!-- bullseye -->
     <xsl:if test="/cdash/coverage/coveragetype='bullseye'">
     <!-- branches -->
     <td align="center">
        <xsl:if test="covered>0">
       <xsl:attribute name="class">
         <xsl:choose>
           <xsl:when test="coveragemetric &lt;  /cdash/coverage/metricerror">
             error
             </xsl:when>
          <xsl:when test="coveragemetric >= /cdash/coverage/metricpass">
             normal
             </xsl:when>
           <xsl:otherwise>
             warning
            </xsl:otherwise>
         </xsl:choose>
       </xsl:attribute>
       <xsl:value-of select="branchesuntested"/>/<xsl:value-of select="totalbranches"/>
      </xsl:if>
       <xsl:if test="covered=0">
       <xsl:attribute name="class">error</xsl:attribute><xsl:value-of select="branchesuntested"/>
       </xsl:if>
     </td>

      <!-- functions -->
       <td align="center">
       <xsl:if test="covered>0">
       <xsl:attribute name="class">
         <xsl:choose>
           <xsl:when test="coveragemetric &lt;  /cdash/coverage/metricerror">
             error
             </xsl:when>
          <xsl:when test="coveragemetric >= /cdash/coverage/metricpass">
             normal
             </xsl:when>
           <xsl:otherwise>
             warning
            </xsl:otherwise>
         </xsl:choose>
       </xsl:attribute>
       <xsl:value-of select="functionsuntested"/>/<xsl:value-of select="totalfunctions"/>
      </xsl:if>
       <xsl:if test="covered=0">
         <xsl:attribute name="class">error</xsl:attribute>0
       </xsl:if>
     </td>
     </xsl:if>

    <!-- Priority -->
     <td align="center">
     <xsl:attribute name="class">
        <xsl:choose>
           <xsl:when test="priority='Urgent' or priority='High'">
             error
           </xsl:when>
           <xsl:when test="priority='Medium'">
             warning
           </xsl:when>
         </xsl:choose>
       </xsl:attribute>
    <xsl:value-of select="priority"/></td>

    <!-- Authors -->
    <xsl:if test="/cdash/coverage/userid!=0">
      <td align="center">
      <xsl:for-each select="author">
        <xsl:value-of select="name"/>
      </xsl:for-each>
      </td>
    </xsl:if>

     <!-- Labels -->
     <xsl:if test="count(//labels/label) > 0">
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
