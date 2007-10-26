<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
    <xsl:template name="builds">
    <xsl:param name="type"/>
    <xsl:param name="type_name"/>
   <xsl:if test="count($type)=0">
   <tr class="table-heading">
      <td colspan="14">
          <h3>No <xsl:value-of select="$type_name"/> Builds</h3>
      </td>
   </tr>
   </xsl:if>
   
    <xsl:if test="count($type)>0">
        <tr class="table-heading">
      <td colspan="14">
          <h3><xsl:value-of select="$type_name" /> Builds</h3>
      </td>
   </tr>
   <tr class="table-heading">
      <th align="center" rowspan="2">Site</th>
      <th align="center" rowspan="2">Build Name</th>

      <th align="center" rowspan="2">Update</th>
      <th align="center" rowspan="2">Cfg</th>
      <th align="center" colspan="3">Build</th>
      <th align="center" colspan="5">Test</th>
      <th align="center" rowspan="2">Build Date</th>
      <th align="center" rowspan="2">Submit Date</th>

   </tr>
   <tr class="table-heading">
      <th align="center">Error</th>
      <th align="center">Warn</th>
      <th align="center">Min</th>
      <th align="center">NotRun</th>
      <th align="center">Fail</th>

      <th align="center">Pass</th>
      <th align="center">NA</th>
      <th align="center">Min</th>
   </tr>
   
      <xsl:for-each select="$type">
   <tr valign="top">
      <td align="left" bgcolor="#ffffff">
      <a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="siteid"/></xsl:attribute><xsl:value-of select="site"/></a>
      </td>
      <td align="left" bgcolor="#ffffff"><xsl:value-of select="buildname"/>
        <xsl:text>&#x20;</xsl:text>
      <xsl:if test="string-length(notes)>0">
      <a><xsl:attribute name="href">viewNotes.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><img SRC="images/Document.gif" ALT="Notes" border="0"/></a>
      </xsl:if> 
     
      <xsl:if test="string-length(generator)>0">
      <a><xsl:attribute name="href">javascript:alert("<xsl:value-of select="generator"/>");</xsl:attribute>
      <img SRC="images/Generator.png" border="0">
      <xsl:attribute name="alt"><xsl:value-of select="generator"/></xsl:attribute>
      </img>
      </a> 
      </xsl:if> 
      
      </td>
      <td align="right" bgcolor="#ffffff"><b><a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="update"/> </a></b>
      </td>
      <td align="right" class="normal"><b><a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="configure"/> </a></b>
      </td>
      <td>
      <xsl:attribute name="align">right</xsl:attribute>
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="build/error > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="build/error"/> </a></b>
      </td>
      <td align="right">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="build/warning > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="build/warning"/></a></b>
      </td>
      <td align="right" bgcolor="#FFFFFF"><xsl:value-of select="build/time"/></td>
      <td align="right">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/notrun > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/notrun"/></a></b>
      </td>
      <td align="right">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/fail"/></a></b>
      </td>

      <td align="right">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/pass"/></a></b>
      </td>
      <td align="right" class="na"><b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/na"/></a></b>
      </td>
      <td align="right" bgcolor="#FFFFFF"><xsl:value-of select="test/time"/></td>
      <td bgcolor="#ffffff"><xsl:value-of select="builddate"/></td>
      <td bgcolor="#ffffff"><xsl:value-of select="submitdate"/></td>
   </tr>
  </xsl:for-each>
  </xsl:if>
</xsl:template>
    
   
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
    
    <xsl:output method="html"/>
    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
       </head>
       <body bgcolor="#ffffff">
   
       <xsl:call-template name="header"/>
<br/>

<xsl:if test="cdash/builds/updates">
<table xmlns:lxslt="http://xml.apache.org/xslt" border="0" width="100%" cellpadding="3" cellspacing="1" bgcolor="#0000aa">
   <tr class="table-heading">
      <td>
         <h3><a href="Update.html">1 Files Changed
               </a>
            by 1 Authors
            as of 2007-10-10 01:00 GMT
         </h3>
      </td>
   </tr>
</table>
<br/>
</xsl:if>

<table xmlns:lxslt="http://xml.apache.org/xslt" border="0" width="100%" cellpadding="3" cellspacing="1" bgcolor="#0000aa">


<xsl:call-template name="builds">
<xsl:with-param name="type" select="cdash/builds/nightly"/>
<xsl:with-param name="type_name">Nightly</xsl:with-param>
</xsl:call-template>

<xsl:call-template name="builds">
<xsl:with-param name="type" select="cdash/builds/continuous"/>
<xsl:with-param name="type_name">Continuous</xsl:with-param>
</xsl:call-template>

<xsl:call-template name="builds">
<xsl:with-param name="type" select="cdash/builds/experimental"/>
<xsl:with-param name="type_name">Experimental</xsl:with-param>
</xsl:call-template>

<xsl:if test="count(cdash/builds/nightly)+count(cdash/builds/continuous)+count(cdash/builds/experimental)>0">
   <tr>
      <td align="left" bgcolor="#ffffff">
         Totals
         
      </td>

      <td align="center" bgcolor="#ffffff"><b><xsl:value-of select = "count(cdash/builds/nightly)+count(cdash/builds/continuous)+count(cdash/builds/experimental)" /> Builds</b></td>
      <td bgcolor="#ffffff"></td>
      <td align="right">
       <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="cdash/builds/totalConfigure > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "cdash/builds/totalConfigure"/></b>  
      </td>
      <td align="right">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/builds/totalError > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "cdash/builds/totalError"/></b>
      </td>
      <td align="right">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/builds/totalWarning > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>  
      <b><xsl:value-of select = "cdash/builds/totalWarning"/></b>

      </td>
      <td bgcolor="#ffffff"></td>
      <td align="right">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/builds/totalNotRun > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "cdash/builds/totalNotRun"/></b>
      </td>
      <td align="right">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/builds/totalFail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "cdash/builds/totalFail"/></b>  
      </td>
      <td align="right">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/builds/totalFail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "cdash/builds/totalPass"/></b>
      </td>
      <td bgcolor="#ffffff"></td>
      <td bgcolor="#ffffff"></td>
      <td bgcolor="#ffffff"></td>
      <td bgcolor="#ffffff"></td>
   </tr>
</xsl:if>  
</table>

<br/>

<!-- COVERAGE -->
<table xmlns:lxslt="http://xml.apache.org/xslt" border="0" width="100%" cellpadding="3" cellspacing="1" bgcolor="#0000aa">   
    <xsl:if test="count(cdash/builds/coverage)=0">
   <tr class="table-heading">
      <td colspan="14">
          <h3>No Coverage</h3>
      </td>
   </tr>
   </xsl:if>
   
    <xsl:if test="count(cdash/builds/coverage)>0">
        <tr class="table-heading">
      <td colspan="14">
          <h3>Coverage</h3>
      </td>
   </tr>

   <tr class="table-heading">
      <th align="center">Site</th>
      <th align="center">Build Name</th>
      <th align="center" width="80">Percentage</th>

      <th align="center">Passed</th>
      <th align="center">Failed</th>
      <th align="center">Date</th>
      <th align="center">Submission Date</th>
   </tr>
  <xsl:for-each select="cdash/builds/coverage">
   
   <tr>
      <td align="left" bgcolor="#ffffff"><xsl:value-of select="site"/></td>
      <td align="left" bgcolor="#ffffff"><xsl:value-of select="buildname"/></td>
      <td align="center" class="warning"><a><xsl:attribute name="href">viewCoverage.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><b><xsl:value-of select="percentage"/>%</b></a></td>
      <td align="right" bgcolor="#ffffff"><b><xsl:value-of select="pass"/></b></td>
      <td align="right" bgcolor="#ffffff"><b><xsl:value-of select="fail"/></b></td>
      <td align="left" bgcolor="#ffffff"><xsl:value-of select="date"/></td>
      <td align="left" bgcolor="#ffffff"><xsl:value-of select="submitdate"/></td>

   </tr>
  </xsl:for-each>

</xsl:if>

</table>

<br/>

<!-- Dynamic analysis -->
<table xmlns:lxslt="http://xml.apache.org/xslt" border="0" width="100%" cellpadding="3" cellspacing="1" bgcolor="#0000aa">
   <tr class="table-heading">
      <td>
         <h3>No DynamicAnalysis information</h3>
      </td>
   </tr>
</table>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
