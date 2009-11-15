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

<h3>Coverage started on <xsl:value-of select="cdash/coverage/starttime"/></h3>
<table cellpadding="30">
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
                  <xsl:when test="cdash/coverage/percentcoverage > cdash/coverage/percentagegreen">
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

               <td align="left"> Tested lines</td>
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
      <td valign="Top">
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
                  Unstatisfactory coverage           
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

<br/>

<!--  Coverage table -->
<input type="hidden" name="coverageType" id="coverageType">
<xsl:attribute name="value">
   <xsl:value-of select="cdash/coverage/coveragetype"/>
</xsl:attribute>
</input>
      
<table id="coverageTable" cellspacing="0" cellpadding="3">
 <xsl:attribute name="class">
   tabb {sortlist: [[1,0]]}
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
          <xsl:when test="coveragemetric &lt; /cdash/coverage/metricerror">
             Low
           </xsl:when>
         <xsl:when test="coveragemetric >= /cdash/coverage/metricpass">
            Satisfactory
            </xsl:when>  
          <xsl:otherwise>
            Medium
           </xsl:otherwise>
        </xsl:choose>
    </td> 
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
      <xsl:value-of select="percentcoverage"/>%
      </xsl:if>
      <xsl:if test="covered=0">
      UNTESTED
      </xsl:if>
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
       <xsl:value-of select="locuntested"/>
      </xsl:if>
       <xsl:if test="covered=0">
       UNTESTED
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
       <xsl:value-of select="branchesuntested"/>
      </xsl:if>
       <xsl:if test="covered=0">
       UNTESTED
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
       <xsl:value-of select="functionsuntested"/>
      </xsl:if>
       <xsl:if test="covered=0">
       UNTESTED
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
