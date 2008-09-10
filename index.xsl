<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
   <xsl:template name="builds">
   <xsl:param name="type"/>
   <xsl:if test="count($type/build)=0">
  
   <tr class="table-heading1">
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger">No <xsl:value-of select="name"/> Builds</a></h3>
      </td>
  
  <!-- quick links -->
  <td colspan="15" align="right" class="nob">
   <div>
   <xsl:attribute name="id"><xsl:value-of select="linkname"/></xsl:attribute>
   </div> 
   <div class="quicklink">
   <xsl:for-each select="/cdash/buildgroup">
       <xsl:if test="name!=$type/name">
        <a>
     <xsl:attribute name="href">#<xsl:value-of select="linkname"/></xsl:attribute>
     <xsl:value-of select="name"/></a> | 
      </xsl:if>
    </xsl:for-each> 
   <a href="#Coverage">Coverage</a> | 
   <a href="#DynamicAnalysis">Dynamic Analysis</a>
    </div> 
    </td>
  
   </tr>
   </xsl:if>
   
    <xsl:if test="count($type/build)>0">
     <thead> 
        <tr class="table-heading1" >
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger"><xsl:value-of select="$type/name"/></a></h3>
      </td>
  <td colspan="15" align="right" class="nob">
   <div>
   <xsl:attribute name="id"><xsl:value-of select="linkname"/></xsl:attribute>
   </div> 
   <div class="quicklink">
   <xsl:for-each select="/cdash/buildgroup">
       <xsl:if test="name!=$type/name">
         <a>
     <xsl:attribute name="href">#<xsl:value-of select="linkname"/></xsl:attribute>
     <xsl:value-of select="name"/></a> | 
      </xsl:if>
    </xsl:for-each>
   <a href="#Coverage">Coverage</a> | 
   <a href="#DynamicAnalysis">Dynamic Analysis</a>
    </div> 
    </td>
   </tr>
   
   <tr class="table-heading">
      <th align="center" rowspan="2" width="20%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_0</xsl:attribute>
      Site</th>
      <th align="center" rowspan="2" width="20%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_1</xsl:attribute>
      Build Name</th>
      <td align="center" colspan="2" width="5%" class="botl">Update</td>
      <td align="center" colspan="3" width="15%" class="botl">Configure</td>
      <td align="center" colspan="3" width="15%" class="botl">Build</td>
      <td align="center" colspan="4" width="15%" class="botl">Test</td>
      <th align="center" rowspan="2" width="10%" class="nob">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_14</xsl:attribute>
      Build Time</th>
      <!-- <td align="center" rowspan="2" class="nob">Submit Date</td> -->

   </tr>

   <tr class="table-heading">
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_2</xsl:attribute>
      Files</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_3</xsl:attribute>
      Min</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_4</xsl:attribute>
      Error</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_5</xsl:attribute>
      Warn</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_6</xsl:attribute>
      Min</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_7</xsl:attribute>
      Error</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_8</xsl:attribute>
      Warn</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_9</xsl:attribute>
      Min</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_10</xsl:attribute>
      NotRun</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_11</xsl:attribute>
      Fail</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_12</xsl:attribute>
      Pass</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_13</xsl:attribute>
      Min</th>
   </tr>
      </thead>
       <tbody> 
      <xsl:for-each select="$type/build">
   <tr valign="middle">
<!--   <xsl:attribute name="class"><xsl:value-of select="rowparity"/></xsl:attribute>
  --> 
   
      <td align="left" class="paddt">
      <a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="siteid"/>&#38;project=<xsl:value-of select="/cdash/dashboard/projectid"/>&#38;currenttime=<xsl:value-of select="/cdash/dashboard/unixtimestamp"/></xsl:attribute><xsl:value-of select="site"/></a>
      </td>
      <td align="left">
      <xsl:if test="string-length(buildid)>0">
      <a><xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="buildname"/></a>
     </xsl:if>
      <xsl:if test="string-length(buildid)=0">
     <xsl:value-of select="buildname"/>
     </xsl:if>
        <xsl:text>&#x20;</xsl:text>
      <xsl:if test="string-length(note)>0">
      <a><xsl:attribute name="href">viewNotes.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><img src="images/Document.gif" alt="Notes" border="0"/></a>
      </xsl:if> 
     
      <xsl:if test="string-length(generator)>0">
      <a><xsl:attribute name="href">javascript:alert("<xsl:value-of select="generator"/>");</xsl:attribute>
      <img src="images/Generator.png" border="0">
      <xsl:attribute name="alt"><xsl:value-of select="generator"/></xsl:attribute>
      </img>
      </a>
      </xsl:if> 
      
      <!-- If the build has errors or test failing -->
      <xsl:if test="compilation/error > 0 or test/fail > 0">
      <a href="javascript:;">
      <xsl:attribute name="onclick">javascript:buildinfo_click(<xsl:value-of select="buildid"/>)</xsl:attribute>
      <img src="images/Info.png" alt="info" border="0"></img>
      </a>
      </xsl:if>
      
      <!-- If the build is expected -->
      <xsl:if test="expected=1">
      <a>
      <xsl:attribute name="href">javascript:expectedinfo_click('<xsl:value-of select="siteid"/>','<xsl:value-of select="buildname"/>','<xsl:value-of select="expecteddivname"/>','<xsl:value-of select="/cdash/dashboard/projectid"/>','<xsl:value-of select="buildtype"/>','<xsl:value-of select="/cdash/dashboard/unixtimestamp"/>')</xsl:attribute>
      <img src="images/Info.png" border="0" alt="info"></img>
      </a>
      </xsl:if>
      
      <!-- Display the note icon -->
      <xsl:if test="buildnote>0">
      <a name="Build Notes" class="jTip">
      <xsl:attribute name="id">buildnote_<xsl:value-of select="buildid"/></xsl:attribute>
      <xsl:attribute name="href">ajax/buildnote.php?buildid=<xsl:value-of select="buildid"/>&amp;width=350&amp;link=buildSummary.php%3Fbuildid%3D<xsl:value-of select="buildid"/></xsl:attribute>
      <img src="images/note.png" border="0"></img>
      </a>
      </xsl:if>
      
      <!-- If user is admin of the project propose to group this build -->
      <xsl:if test="/cdash/user/admin=1">
        <xsl:if test="string-length(buildid)>0">
        <a>
        <xsl:attribute name="href">javascript:buildgroup_click(<xsl:value-of select="buildid"/>)</xsl:attribute>
        <img src="images/folder.png" border="0"></img>
        </a>
        </xsl:if>
        <xsl:if test="string-length(buildid)=0">
        <a>
        <xsl:attribute name="href">javascript:buildnosubmission_click('<xsl:value-of select="siteid"/>','<xsl:value-of select="buildname"/>','<xsl:value-of select="expecteddivname"/>','<xsl:value-of select="buildgroupid"/>','<xsl:value-of select="buildtype"/>')</xsl:attribute>
        <img src="images/folder.png" border="0"></img>
        </a>
        </xsl:if>
      </xsl:if> <!-- end admin -->
      
      <xsl:if test="string-length(buildid)>0"> 
      <div>
      <xsl:attribute name="id">buildgroup_<xsl:value-of select="buildid"/></xsl:attribute>
      </div>
      </xsl:if>
     
      <xsl:if test="string-length(expecteddivname)>0"> 
      <div>
      <xsl:attribute name="id">infoexpected_<xsl:value-of select="expecteddivname"/></xsl:attribute>
      </div>
     </xsl:if>
      
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="update/errors = 1">
            error
            </xsl:when>
            <xsl:otherwise>
            <xsl:choose>
            <xsl:when test="update/warning=1">
            warning
            </xsl:when>
            <xsl:otherwise>
            <xsl:if test="string-length(update/files)>0">
            normal
            </xsl:if>
            </xsl:otherwise>
            </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="update/files"/> </a></b>
      <xsl:if test="string-length(update/files)=0"><xsl:text>&#160;</xsl:text></xsl:if>  
      </td>
      <td align="right">
      <xsl:value-of select="update/time"/>
      <xsl:if test="string-length(update/time)=0"><xsl:text>&#160;</xsl:text></xsl:if> 
      </td>    
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="configure/error>0">
            error
            </xsl:when>
           <xsl:when test="string-length(configure/error)>0">
           normal 
           </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <b>
      <a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/>
      </xsl:attribute><xsl:value-of select="configure/error"/></a></b>
      <xsl:if test="string-length(configure/error)=0"><xsl:text>&#160;</xsl:text></xsl:if>   
       <xsl:if test="string-length(configure/warning)=0"><xsl:text>&#160;</xsl:text></xsl:if>   
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="configure/warning > 0">
            warning
            </xsl:when>
           <xsl:when test="string-length(configure/warning)>0">
           normal 
           </xsl:when>     
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="configure/warning"/></a></b>
      <xsl:if test="string-length(configure/warning)=0"><xsl:text>&#160;</xsl:text></xsl:if>   
      <xsl:if test="configure/nwarningdiff > 0"><sub>+<xsl:value-of select="configure/nwarningdiff"/></sub></xsl:if>
      <xsl:if test="configure/nwarningdiff &lt; 0"><sub><xsl:value-of select="configure/nwarningdiff"/></sub></xsl:if>
      </td>
      <td align="right">
      <xsl:value-of select="configure/time"/>
      <xsl:if test="string-length(configure/time)=0"><xsl:text>&#160;</xsl:text></xsl:if> 
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="compilation/error > 0">
            error
            </xsl:when>
           <xsl:when test="string-length(compilation/error)>0">
           normal 
           </xsl:when>     
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="compilation/error"/></a></b>
      <xsl:if test="string-length(compilation/error)=0"><xsl:text>&#160;</xsl:text></xsl:if>   
      <xsl:if test="compilation/nerrordiff > 0"><sub>+<xsl:value-of select="compilation/nerrordiff"/></sub></xsl:if>
      <xsl:if test="compilation/nerrordiff &lt; 0"><sub><xsl:value-of select="compilation/nerrordiff"/></sub></xsl:if>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="compilation/warning > 0">
            warning
            </xsl:when>
           <xsl:when test="string-length(compilation/warning)>0">
           normal 
           </xsl:when>   
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="buildid"/> </xsl:attribute><xsl:value-of select="compilation/warning"/></a></b>
      <xsl:if test="string-length(compilation/warning)=0"><xsl:text>&#160;</xsl:text></xsl:if>  
      <xsl:if test="compilation/nwarningdiff > 0"><sub>+<xsl:value-of select="compilation/nwarningdiff"/></sub></xsl:if>
      <xsl:if test="compilation/nwarningdiff &lt; 0"><sub><xsl:value-of select="compilation/nwarningdiff"/></sub></xsl:if>
      </td>
      <td align="right"><xsl:value-of select="compilation/time"/>
      <xsl:if test="string-length(compilation/time)=0"><xsl:text>&#160;</xsl:text></xsl:if>   
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/notrun > 0">
            error
            </xsl:when>
            <xsl:when test="string-length(test/notrun)>0">
            normal
            </xsl:when>    
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/notrun"/></a></b>
      <xsl:if test="string-length(test/notrun)=0"><xsl:text>&#160;</xsl:text></xsl:if>   
      <xsl:if test="test/nnotrundiff > 0"><sub>+<xsl:value-of select="test/nnotrundiff"/></sub></xsl:if>
      <xsl:if test="test/nnotrundiff &lt; 0"><sub><xsl:value-of select="test/nnotrundiff"/></sub></xsl:if>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
            warning
            </xsl:when>
          <xsl:when test="string-length(test/fail)>0">
          normal  
          </xsl:when>  
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?onlyfailed&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/fail"/></a></b>
      <xsl:if test="string-length(test/fail)=0"><xsl:text>&#160;</xsl:text></xsl:if>  
      <xsl:if test="test/nfaildiff > 0"><sub>+<xsl:value-of select="test/nfaildiff"/></sub></xsl:if>
      <xsl:if test="test/nfaildiff &lt; 0"><sub><xsl:value-of select="test/nfaildiff"/></sub></xsl:if>
      </td>

      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
            warning
            </xsl:when>
             <xsl:when test="string-length(test/fail)>0">
             normal
             </xsl:when>       
        </xsl:choose>
      </xsl:attribute>
      <b><a><xsl:attribute name="href">viewTest.php?onlypassed&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/pass"/></a></b>
      <xsl:if test="string-length(test/fail)=0"><xsl:text>&#160;</xsl:text></xsl:if>  
      <xsl:if test="test/npassdiff > 0"><sub>+<xsl:value-of select="test/npassdiff"/></sub></xsl:if>
      <xsl:if test="test/npassdiff &lt; 0"><sub><xsl:value-of select="test/npassdiff"/></sub></xsl:if>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/timestatus > 0">
            warning
            </xsl:when>
             <xsl:when test="string-length(test/timestatus)>0">
             normal
             </xsl:when>  
        </xsl:choose>
      </xsl:attribute>
      <xsl:choose>
          <xsl:when test="test/timestatus > 0">
             <b><a><xsl:attribute name="href">viewTest.php?onlytimestatus&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/timestatus"/></a></b>  
             <xsl:if test="test/ntimediff > 0"><sub>+<xsl:value-of select="test/ntimediff"/></sub></xsl:if>
             <xsl:if test="test/ntimediff &lt; 0"><sub><xsl:value-of select="test/ntimediff"/></sub></xsl:if>
          </xsl:when>
           <xsl:when test="string-length(test/timestatus)>0">
             <xsl:value-of select="test/time"/>
             <xsl:if test="string-length(test/time)=0"><xsl:text>&#160;</xsl:text></xsl:if>  
             <xsl:if test="test/ntimediff > 0"><sub>+<xsl:value-of select="test/ntimediff"/></sub></xsl:if>
             <xsl:if test="test/ntimediff &lt; 0"><sub><xsl:value-of select="test/ntimediff"/></sub></xsl:if>
           </xsl:when>  
             <xsl:when test="string-length(test/timestatus)=0">
               <xsl:value-of select="test/time"/>
               <xsl:if test="string-length(test/time)=0"><xsl:text>&#160;</xsl:text></xsl:if>  
             </xsl:when>  
        </xsl:choose>
      </td>
      <td class="nob"><xsl:value-of select="builddate"/></td>
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
  </tbody>
<!-- Row displaying the totals -->
<xsl:if test="count(/cdash/buildgroup/build/buildid)>0 and $type/last=1">
 <tbody> 
   <tr class="total">
      <td align="left">Totals</td>
      <td align="center"><b><xsl:value-of select = "count(/cdash/buildgroup/build/buildid)" /> Builds</b></td>
      <td><xsl:text>&#160;</xsl:text></td>
      <td><xsl:text>&#160;</xsl:text></td>
      <td align="center">
       <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="/cdash/totalConfigureError!=0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "/cdash/totalConfigureError"/></b>  
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalConfigureWarning > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>  
      <b><xsl:value-of select = "/cdash/totalConfigureWarning"/></b>
      </td>
      <td><xsl:text>&#160;</xsl:text></td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalError > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "/cdash/totalError"/></b>
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalWarning > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>  
      <b><xsl:value-of select = "/cdash/totalWarning"/></b>
      </td>
      <td><xsl:text>&#160;</xsl:text></td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalNotRun > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "/cdash/totalNotRun"/></b>
      </td>
      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalFail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "/cdash/totalFail"/></b>  
      </td>
      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalFail > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "/cdash/totalPass"/></b>
      </td>
      <td><xsl:text>&#160;</xsl:text></td>
      <td class="nob"></td>
      <!-- <td bgcolor="#ffffff"></td> -->
   </tr>
</tbody>
</xsl:if>


  </xsl:if>

</xsl:template>
<!-- end template -->    
   
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
   
   <!-- Include local common files -->
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
 
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

<xsl:if test="cdash/dashboard/future=0">

<xsl:if test="cdash/updates">
<table width="100%" cellpadding="11" cellspacing="0">
  <tr>
    <td height="25" align="left" valign="bottom">
    <xsl:if test="cdash/updates/nchanges=-1">No update data</xsl:if>
    <xsl:if test="cdash/updates/nchanges=0">No file changed</xsl:if>
    <xsl:if test="cdash/updates/nchanges>0">
      <a><xsl:attribute name="href"><xsl:value-of select="cdash/updates/url"/></xsl:attribute>
         <xsl:value-of select="cdash/updates/nchanges"/> file<xsl:if test="cdash/updates/nchanges>1">s</xsl:if>
          changed  </a> 
         by <xsl:value-of select="cdash/updates/nauthors"/> author<xsl:if test="cdash/updates/nauthors>1">s</xsl:if>
    </xsl:if>
         as of
         <xsl:value-of select="cdash/updates/timestamp"/></td>
         <td><a href="#" class="keytrigger">Help</a>
         <div class="jqmWindow" id="key">Loading key...</div>
         <div class="jqmWindow" id="groupsdescription">Loading key...</div>
         </td>
  </tr>
</table>
</xsl:if>

<!-- Look each group -->
<xsl:for-each select="cdash/buildgroup">
  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>
  <xsl:call-template name="builds">
  <xsl:with-param name="type" select="."/>
  </xsl:call-template>
  </table>
</xsl:for-each>

<br/>

<!-- COVERAGE -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <xsl:if test="count(cdash/buildgroup/coverage)=0">
   <tr class="table-heading2">
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger">No Coverage</a></h3>
      </td>
   <!-- quick links -->
  <td colspan="5" align="right" class="nob">
   <div id="Coverage">
   </div>
   <div class="quicklink">
   <xsl:for-each select="/cdash/buildgroup">
     <a>
     <xsl:attribute name="href">#<xsl:value-of select="linkname"/></xsl:attribute>
     <xsl:value-of select="name"/></a> | 
    </xsl:for-each>
    <a href="#DynamicAnalysis">Dynamic Analysis</a>
    </div> 
    </td>
   </tr>
   </xsl:if>
   
    <xsl:if test="count(cdash/buildgroup/coverage)>0">
        <tr class="table-heading2">
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger">Coverage</a></h3>
      </td>
   <!-- quick links -->
  <td colspan="5" align="right" class="nob">
   <div id="Coverage">
   </div>
   <div class="quicklink">
   <xsl:for-each select="/cdash/buildgroup">
     <a>
     <xsl:attribute name="href">#<xsl:value-of select="linkname"/></xsl:attribute>
     <xsl:value-of select="name"/></a> | 
    </xsl:for-each>
    <a href="#DynamicAnalysis">Dynamic Analysis</a>
    </div> 
    </td>
   </tr>

   <tr class="table-heading">
      <th align="center" width="20%">Site</th>
      <th align="center" width="30%">Build Name</th>
      <th align="center" width="10%">Percentage</th>

      <th align="center"  width="10%">Passed</th>
      <th align="center"  width="10%">Failed</th>
      <th align="center" class="nob"  width="20%">Date</th>
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
      <a><xsl:attribute name="href">viewCoverage.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><b><xsl:value-of select="percentage"/>%</b></a>
      <xsl:if test="coverage/percentagediff > 0"><sub>+<xsl:value-of select="coverage/percentagediff"/>%</sub></xsl:if>
      <xsl:if test="coverage/percentagediff &lt; 0"><sub><xsl:value-of select="coverage/percentagediff"/>%</sub></xsl:if>
      </td>
      <td align="center" ><b><xsl:value-of select="pass"/></b>
      <xsl:if test="coverage/passdiff > 0"><sub>+<xsl:value-of select="coverage/passdiff"/></sub></xsl:if>
      <xsl:if test="coverage/passdiff &lt; 0"><sub><xsl:value-of select="coverage/passdiff"/></sub></xsl:if>    
      </td>
      <td align="center" ><b><xsl:value-of select="fail"/></b>
      <xsl:if test="coverage/faildiff > 0"><sub>+<xsl:value-of select="coverage/faildiff"/></sub></xsl:if>
      <xsl:if test="coverage/faildiff &lt; 0"><sub><xsl:value-of select="coverage/faildiff"/></sub></xsl:if>    
      </td>
      <td align="left"  class="nob"><xsl:value-of select="date"/></td>
   </tr>
  </xsl:for-each>

</xsl:if>

</tbody>
</table>


<xsl:if test="count(cdash/buildgroup/coverage)>0">
<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="14" align="left" bgcolor="#888888"></td>
</tr>
</table>
</xsl:if>

<br/>

<!-- Dynamic analysis -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb"> 
<tbody>
    <xsl:if test="count(cdash/buildgroup/dynamicanalysis)=0">
   <tr class="table-heading3" >
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger">No Dynamic Analysis</a></h3>
      </td>
   <!-- quick links -->
  <td colspan="4" align="right" class="nob">
   <div id="DynamicAnalysis">
   </div>
   <div class="quicklink">
   <xsl:for-each select="/cdash/buildgroup">
      <a>
     <xsl:attribute name="href">#<xsl:value-of select="linkname"/></xsl:attribute>
     <xsl:value-of select="name"/></a> | 
    </xsl:for-each>
    <a href="#Coverage">Coverage</a>
    </div> 
    </td>
   </tr>
   </xsl:if>
   
    <xsl:if test="count(cdash/buildgroup/dynamicanalysis)>0">
        <tr class="table-heading3">
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger">Dynamic Analysis</a></h3>
      </td>
      <!-- quick links -->
  <td colspan="4" align="right" class="nob">
   <div id="DynamicAnalysis"></div>
   <div class="quicklink">
   <xsl:for-each select="/cdash/buildgroup">
      <a>
     <xsl:attribute name="href">#<xsl:value-of select="linkname"/></xsl:attribute>
     <xsl:value-of select="name"/></a> | 
    </xsl:for-each>
    <a href="#Coverage">Coverage</a>
    </div> 
    </td>
   </tr>

   <tr class="table-heading">
      <th align="center" width="20%">Site</th>
      <th align="center" width="30%">Build Name</th>
      <th align="center" width="20%">Checker</th>

      <th align="center" width="10%">Defect Count</th>
      <th align="center" class="nob" width="20%">Date</th>
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
      <td align="left" class="nob"><xsl:value-of select="date"/></td>
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

</xsl:if>
</tbody>
</table>

<xsl:if test="count(cdash/buildgroup/dynamicanalysis)>0">
  <table width="100%" cellspacing="0" cellpadding="0">
  <tr>
  <td height="1" colspan="14" align="left" bgcolor="#888888"></td>
  </tr>
  </table>
</xsl:if>


</xsl:if> <!-- end dashboard is not in the future -->

<xsl:if test="cdash/dashboard/future=1">
<br/>
CDash cannot predict the future (yet)...
<br/>
</xsl:if> <!-- end dashboard is in the future -->

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
