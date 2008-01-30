<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
    <xsl:template name="builds">
    <xsl:param name="type"/>
   <xsl:if test="count($type/build)=0">
			
   <tr class="table-heading1" >
      <td colspan="1" id="nob">
          <h3>No <xsl:value-of select="name"/> Builds</h3>
      </td>
		
		<!-- quick links -->
		<td colspan="12" align="right" id="nob">
			<div>
			<xsl:attribute name="id"><xsl:value-of select="name"/></xsl:attribute>
			<xsl:for-each select="/cdash/buildgroup">
			    <xsl:if test="name!=$type/name">
         [<a>
				 <xsl:attribute name="href">#<xsl:value-of select="name"/></xsl:attribute>
				 <xsl:value-of select="name"/></a>]
				  </xsl:if>
		  </xsl:for-each>	
			[<a href="#Coverage">Coverage</a>]
			[<a href="#DynamicAnalysis">Dynamic Analysis</a>]
    </div> 
    </td>
		
   </tr>
   </xsl:if>
   
    <xsl:if test="count($type/build)>0">
        <tr class="table-heading1" >
      <td colspan="1" id="nob">
          <h3><xsl:value-of select="$type/name"/></h3>
      </td>
		<!-- quick links -->
		<td colspan="12" align="right" id="nob">
			<div>
			<xsl:attribute name="id"><xsl:value-of select="name"/></xsl:attribute>
			<xsl:for-each select="/cdash/buildgroup">
			    <xsl:if test="name!=$type/name">
         [<a>
				 <xsl:attribute name="href">#<xsl:value-of select="name"/></xsl:attribute>
				 <xsl:value-of select="name"/></a>]
				  </xsl:if>
		  </xsl:for-each>
			[<a href="#Coverage">Coverage</a>]
			[<a href="#DynamicAnalysis">Dynamic Analysis</a>]
    </div> 
    </td>
   </tr>
   <tr class="table-heading">
      <td align="center" rowspan="2">Site</td>
      <td align="center" rowspan="2">Build Name</td>

      <td align="center" rowspan="2">Update</td>
      <td align="center" rowspan="2">Cfg</td>
      <td align="center" colspan="3" class="botl">Build</td>
      <td align="center" colspan="5" class="botl">Test</td>
      <td align="center" rowspan="2" id="nob">Build Date</td>
      <!-- <td align="center" rowspan="2" id="nob">Submit Date</td> -->

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

      <xsl:for-each select="$type/build">
   <tr valign="middle">
			<xsl:attribute name="class"><xsl:value-of select="rowparity"/></xsl:attribute>
			
			
      <td align="left" class="paddt">
      <a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="siteid"/></xsl:attribute><xsl:value-of select="site"/></a>
      </td>
      <td align="center">
						<a><xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="buildname"/></a>
        <xsl:text>&#x20;</xsl:text>
      <xsl:if test="string-length(note)>0">
      <a><xsl:attribute name="href">viewNotes.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><img SRC="images/Document.gif" ALT="Notes" border="0"/></a>
      </xsl:if> 
     
      <xsl:if test="string-length(generator)>0">
      <a><xsl:attribute name="href">javascript:alert("<xsl:value-of select="generator"/>");</xsl:attribute>
      <img SRC="images/Generator.png" border="0">
      <xsl:attribute name="alt"><xsl:value-of select="generator"/></xsl:attribute>
      </img>
      </a>
      </xsl:if> 
      
      <!-- If the build has errors or test failing -->
       <xsl:if test="compilation/error > 0 or test/fail > 0">
      <a href="javascript:;">
      <xsl:attribute name="onclick">javascript:buildinfo_click(<xsl:value-of select="buildid"/>)</xsl:attribute>
      <img name="buildgroup" SRC="images/Info.png" border="0"></img>
      </a>
      </xsl:if>
      
      <!-- If the build is expected -->
      <xsl:if test="expected=1">
      <a>
      <xsl:attribute name="href">javascript:expectedinfo_click('<xsl:value-of select="siteid"/>','<xsl:value-of select="buildname"/>','<xsl:value-of select="expecteddivname"/>','<xsl:value-of select="/cdash/dashboard/projectid"/>','<xsl:value-of select="buildtype"/>','<xsl:value-of select="/cdash/dashboard/unixtimestamp"/>')</xsl:attribute>
      <img name="buildgroup" SRC="images/Info.png" border="0"></img>
      </a>
      </xsl:if>
      
      <!-- If user is admin of the project propose to group this build -->
      <xsl:if test="/cdash/user/admin=1">
        <xsl:if test="string-length(buildid)>0">
        <a>
        <xsl:attribute name="href">javascript:buildgroup_click(<xsl:value-of select="buildid"/>)</xsl:attribute>
        <img name="buildgroup" SRC="images/folder.png" border="0"></img>
        </a>
        </xsl:if>
					  	<xsl:if test="string-length(buildid)=0">
        <a>
        <xsl:attribute name="href">javascript:buildnosubmission_click('<xsl:value-of select="siteid"/>','<xsl:value-of select="buildname"/>','<xsl:value-of select="expecteddivname"/>','<xsl:value-of select="buildgroupid"/>','<xsl:value-of select="buildtype"/>')</xsl:attribute>
        <img name="buildgroup" SRC="images/folder.png" border="0"></img>
        </a>
        </xsl:if>
						</xsl:if> <!-- end admin -->
        
      <div>
      <xsl:attribute name="id">buildgroup_<xsl:value-of select="buildid"/></xsl:attribute>
      </div>
      
      <div>
      <xsl:attribute name="id">infoexpected_<xsl:value-of select="expecteddivname"/></xsl:attribute>
      </div>
      
      </td>
      <td align="center">
						<xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="updateerrors > 0">
            error
            </xsl:when>
           <xsl:when test="updateerrors=0">
            <xsl:value-of select="rowparity"/>
            </xsl:when>
        </xsl:choose>
      </xsl:attribute>
						<b><a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="update"/> </a></b>
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="configure > 0">
            error
            </xsl:when>
           <xsl:when test="string-length(configure)=0">
           <xsl:value-of select="rowparity"/>
            </xsl:when>     
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b>
      <a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/>
      </xsl:attribute><xsl:value-of select="configure"/></a></b>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="compilation/error > 0">
            error
            </xsl:when>
           <xsl:when test="string-length(compilation/error)=0">
           <xsl:value-of select="rowparity"/>
            </xsl:when>     
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="compilation/error"/> </a></b>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="compilation/warning > 0">
            warning
            </xsl:when>
           <xsl:when test="string-length(compilation/warning)=0">
            <xsl:value-of select="rowparity"/>
            </xsl:when>   
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="compilation/warning"/></a></b>
      </td>
      <td align="right"><xsl:value-of select="compilation/time"/></td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/notrun > 0">
            error
            </xsl:when>
          <xsl:when test="string-length(test/notrun)=0">
           <xsl:value-of select="rowparity"/>
            </xsl:when>    
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/notrun"/></a></b>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
            warning
            </xsl:when>
          <xsl:when test="string-length(test/fail)=0">
            <xsl:value-of select="rowparity"/>
            </xsl:when>  
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/fail"/></a></b>
      </td>

      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
            warning
            </xsl:when>
             <xsl:when test="string-length(test/fail)=0">
            <xsl:value-of select="rowparity"/>
            </xsl:when>       
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/pass"/></a></b>
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
             <xsl:when test="string-length(test/na)=0">
            <xsl:value-of select="rowparity"/>
            </xsl:when>       
          <xsl:otherwise>
           na
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b>
      <a>
      <xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/>
      </xsl:attribute><xsl:value-of select="test/na"/>
      
      </a></b>
      </td>
      <td align="right"><xsl:value-of select="test/time"/></td>
      <td id="nob"><xsl:value-of select="builddate"/></td>
						<!--
						<td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="expected=1">
            warning
            </xsl:when>
          <xsl:otherwise>
             <xsl:if test="clockskew=1">
													error
													</xsl:if>
													<xsl:if test="clockskew=0">
													tr-odd
													</xsl:if>
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="submitdate"/></td>
      -->
      
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
                  
         <!-- Include BuildGroup JavaScript -->
         <script src="javascript/cdashBuildGroup.js" type="text/javascript" charset="utf-8"></script> 
       </head>
       <body bgcolor="#ffffff">
       <xsl:call-template name="header"/>

<xsl:if test="cdash/updates">
<table width="100%" cellpadding="11" cellspacing="0">
  <tr>
    <td height="25" align="left" valign="bottom">
				<a><xsl:attribute name="href"><xsl:value-of select="cdash/updates/url"/></xsl:attribute>
         Nightly Changes</a> as of
         <xsl:value-of select="cdash/updates/timestamp"/></td>
  </tr>
</table>
</xsl:if>

<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<xsl:for-each select="cdash/buildgroup">
  <xsl:call-template name="builds">
  <xsl:with-param name="type" select="."/>
  </xsl:call-template>
</xsl:for-each>

<!-- Row displaying the totals -->
<xsl:if test="count(cdash/buildgroup/build/buildid)>0">
   <tr class="total">
      <td align="left">Totals</td>
      <td align="center"><b><xsl:value-of select = "count(cdash/buildgroup/build/buildid)" /> Builds</b></td>
      <td ></td>
      <td align="center">
       <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="cdash/totalConfigure > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "cdash/totalConfigure"/></b>  
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/totalError > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "cdash/totalError"/></b>
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/totalWarning > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>  
      <b><xsl:value-of select = "cdash/totalWarning"/></b>
      </td>
      <td></td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/totalNotRun > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "cdash/totalNotRun"/></b>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/totalFail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "cdash/totalFail"/></b>  
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="cdash/totalFail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "cdash/totalPass"/></b>
      </td>
      <td></td>
      <td></td>
      <td id="nob"></td>
      <!-- <td bgcolor="#ffffff"></td> -->
   </tr>
</xsl:if>
</tbody>
</table>


<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="13" align="left" bgcolor="#888888"></td>
</tr>
</table>

<br/>

<!-- COVERAGE -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <xsl:if test="count(cdash/buildgroup/coverage)=0">
   <tr class="table-heading2">
      <td colspan="1" id="nob">
          <h3>No Coverage</h3>
      </td>
			<!-- quick links -->
		<td colspan="12" align="right" id="nob">
			<div>
			<xsl:attribute name="id"><xsl:value-of select="name"/></xsl:attribute>
			<xsl:for-each select="/cdash/buildgroup">
         [<a>
				 <xsl:attribute name="href">#<xsl:value-of select="name"/></xsl:attribute>
				 <xsl:value-of select="name"/></a>]
		  </xsl:for-each>
			[<a href="#DynamicAnalysis">Dynamic Analysis</a>]
    </div> 
    </td>
   </tr>
   </xsl:if>
   
    <xsl:if test="count(cdash/buildgroup/coverage)>0">
        <tr class="table-heading2">
      <td colspan="1" id="nob">
          <h3>Coverage</h3>
      </td>
			<!-- quick links -->
		<td colspan="12" align="right" id="nob">
			<div id="Coverage">
			<xsl:for-each select="/cdash/buildgroup">
         [<a>
				 <xsl:attribute name="href">#<xsl:value-of select="name"/></xsl:attribute>
				 <xsl:value-of select="name"/></a>]
		  </xsl:for-each>
			[<a href="#DynamicAnalysis">Dynamic Analysis</a>]
    </div> 
    </td>
   </tr>

   <tr class="table-heading">
      <th align="center">Site</th>
      <th align="center">Build Name</th>
      <th align="center" width="80">Percentage</th>

      <th align="center">Passed</th>
      <th align="center">Failed</th>
      <th align="center" id="nob">Date</th>
     <!-- <th align="center">Submission Date</th> -->
   </tr>
  <xsl:for-each select="cdash/buildgroup/coverage">
   
   <tr>
						<xsl:attribute name="class"><xsl:value-of select="rowparity"/></xsl:attribute>

      <td align="left" class="paddt"><xsl:value-of select="site"/></td>
      <td align="left" class="paddt"><xsl:value-of select="buildname"/></td>
      <td align="center">
        <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="percentage > percentagegreen">
            normal
            </xsl:when>
          <xsl:otherwise>
            warning
           </xsl:otherwise>
        </xsl:choose>
        </xsl:attribute>
      <a><xsl:attribute name="href">viewCoverage.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><b><xsl:value-of select="percentage"/>%</b></a></td>
      <td align="center" ><b><xsl:value-of select="pass"/></b></td>
      <td align="center" ><b><xsl:value-of select="fail"/></b></td>
      <td align="left"  id="nob"><xsl:value-of select="date"/></td>
   </tr>
  </xsl:for-each>
<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="14" align="left" bgcolor="#888888"></td>
</tr>
</table>
</xsl:if>

</tbody>
</table>

<br/>

<!-- Dynamic analysis -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb"> 
<tbody>
    <xsl:if test="count(cdash/buildgroup/dynamicanalysis)=0">
   <tr class="table-heading3" >
      <td colspan="1" id="nob">
          <h3>No Dynamic Analysis</h3>
      </td>
			<!-- quick links -->
		<td colspan="12" align="right" id="nob">
			<div>
			<xsl:attribute name="id"><xsl:value-of select="name"/></xsl:attribute>
			<xsl:for-each select="/cdash/buildgroup">
         [<a>
				 <xsl:attribute name="href">#<xsl:value-of select="name"/></xsl:attribute>
				 <xsl:value-of select="name"/></a>]
		  </xsl:for-each>
			[<a href="#Coverage">Coverage</a>]
    </div> 
    </td>
   </tr>
   </xsl:if>
   
    <xsl:if test="count(cdash/buildgroup/dynamicanalysis)>0">
        <tr class="table-heading3" id="nob">
      <td colspan="1" id="nob">
          <h3>Dynamic Analysis</h3>
      </td>
						<!-- quick links -->
		<td colspan="12" align="right" id="nob">
			<div id="DynamicAnalysis">
			<xsl:for-each select="/cdash/buildgroup">
         [<a>
				 <xsl:attribute name="href">#<xsl:value-of select="name"/></xsl:attribute>
				 <xsl:value-of select="name"/></a>]
		  </xsl:for-each>
			[<a href="#Coverage">Coverage</a>]
    </div> 
    </td>
   </tr>

   <tr class="table-heading">
      <th align="center">Site</th>
      <th align="center">Build Name</th>
      <th align="center" width="80">Checker</th>

      <th align="center">Defect Count</th>
      <th align="center" id="nob">Date</th>
    <!--  <th align="center">Submission Date</th> -->
   </tr>
  <xsl:for-each select="cdash/buildgroup/dynamicanalysis">
   
   <tr>
					<xsl:attribute name="class"><xsl:value-of select="rowparity"/></xsl:attribute>

      <td align="left"><xsl:value-of select="site"/></td>
      <td align="left"><xsl:value-of select="buildname"/></td>
      <td align="center"><xsl:value-of select="checker"/></td>
      <td align="center">
        <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="defectcount > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
        </xsl:attribute>
        <a><xsl:attribute name="href">viewDynamicAnalysis.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><b><xsl:value-of select="defectcount"/></b></a>
      </td>
      <td align="left" id="nob"><xsl:value-of select="date"/></td>
      <!--
						<td align="left">
						<xsl:attribute name="class">
						<xsl:if test="clockskew=1">
													error
													</xsl:if>
													<xsl:if test="clockskew=0">
													tr-odd
													</xsl:if>
      </xsl:attribute>
						<xsl:value-of select="submitdate"/></td> -->
   </tr>
  </xsl:for-each>

<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="14" align="left" bgcolor="#888888"></td>
</tr>
</table>

</xsl:if>
</tbody>
</table>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
