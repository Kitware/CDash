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
      <th align="center" rowspan="2" width="15%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_0</xsl:attribute>
      Site</th>
      <th align="center" rowspan="2" width="15%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_1</xsl:attribute>
      Build Name</th>
      <td align="center" colspan="2" width="5%" class="botl">Update</td>
      <td align="center" colspan="3" width="15%" class="botl">Configure</td>
      <td align="center" colspan="3" width="15%" class="botl">Build</td>
      <td align="center" colspan="4" width="15%" class="botl">Test</td>
      <th align="center" rowspan="2" width="10%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_14</xsl:attribute>
      Build Time</th>
      <th align="center" rowspan="2" width="10%" class="nob">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_15</xsl:attribute>
      Labels</th>
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
      <td align="left" class="paddt">
      <a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="siteid"/>&#38;project=<xsl:value-of select="/cdash/dashboard/projectid"/>&#38;currenttime=<xsl:value-of select="/cdash/dashboard/unixtimestamp"/></xsl:attribute><xsl:value-of select="site"/></a>
      </td>
      <td align="left">
      <xsl:if test="string-length(buildid)>0">
      <a>
        <xsl:if test="countbuildids=1">
        <xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
        <xsl:attribute name="href"><xsl:value-of select="multiplebuildshyperlink"/>
        </xsl:attribute>
        </xsl:if>
        <xsl:value-of select="buildname"/>
      </a>
     </xsl:if>
     <xsl:if test="string-length(buildid)=0">
       <xsl:value-of select="buildname"/>
     </xsl:if>
     <xsl:text>&#x20;</xsl:text>

      <xsl:if test="string-length(note)>0 and countbuildids=1">
      <a><xsl:attribute name="href">viewNotes.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><img src="images/Document.gif" alt="Notes" border="0"/></a>
      </xsl:if> 

      <xsl:if test="string-length(generator)>0 and countbuildids=1">
      <a><xsl:attribute name="href">javascript:alert("<xsl:value-of select="generator"/>");</xsl:attribute>
      <img src="images/Generator.png" border="0">
      <xsl:attribute name="alt"><xsl:value-of select="generator"/></xsl:attribute>
      </img>
      </a>
      </xsl:if> 

      <!-- If the build has errors or test failing -->
      <xsl:if test="(compilation/error > 0 or test/fail > 0) and countbuildids=1">
      <a href="javascript:;">
      <xsl:attribute name="onclick">javascript:buildinfo_click(<xsl:value-of select="buildid"/>)</xsl:attribute>
      <img src="images/Info.png" alt="info" border="0"></img>
      </a>
      </xsl:if>

      <!-- If the build is expected -->
      <xsl:if test="expected=1 and countbuildids=1">
      <a>
      <xsl:attribute name="href">javascript:expectedinfo_click('<xsl:value-of select="siteid"/>','<xsl:value-of select="buildname"/>','<xsl:value-of select="expecteddivname"/>','<xsl:value-of select="/cdash/dashboard/projectid"/>','<xsl:value-of select="buildtype"/>','<xsl:value-of select="/cdash/dashboard/unixtimestamp"/>')</xsl:attribute>
      <img src="images/Info.png" border="0" alt="info"></img>
      </a>
      </xsl:if>

      <!-- Display the note icon -->
      <xsl:if test="buildnote>0 and countbuildids=1">
      <a name="Build Notes" class="jTip">
      <xsl:attribute name="id">buildnote_<xsl:value-of select="buildid"/></xsl:attribute>
      <xsl:attribute name="href">ajax/buildnote.php?buildid=<xsl:value-of select="buildid"/>&amp;width=350&amp;link=buildSummary.php%3Fbuildid%3D<xsl:value-of select="buildid"/></xsl:attribute>
      <img src="images/note.png" border="0"></img>
      </a>
      </xsl:if>
      
      <!-- If user is admin of the project propose to group this build -->
      <xsl:if test="/cdash/user/admin=1 and countbuildids=1">
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

      <xsl:if test="string-length(buildid)>0 and countbuildids=1">
      <div>
      <xsl:attribute name="id">buildgroup_<xsl:value-of select="buildid"/></xsl:attribute>
      </div>
      </xsl:if>

      <xsl:if test="string-length(expecteddivname)>0 and countbuildids=1">
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
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="update/files"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="update/files"/>
        </xsl:if>
      <xsl:if test="string-length(update/files)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>  
      </td>

      <td align="right">
      <xsl:value-of select="update/time"/>
      <xsl:if test="string-length(update/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if> 
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
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="configure/error"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="configure/error"/>
        </xsl:if>
      <xsl:if test="string-length(configure/error)=0"><xsl:text disable-output-escaping="yes">0</xsl:text></xsl:if>
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
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="configure/warning"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="configure/warning"/>
        </xsl:if>
      <xsl:if test="string-length(configure/warning)=0"><xsl:text disable-output-escaping="yes">0</xsl:text></xsl:if>   
      <xsl:if test="configure/nwarningdiff > 0"><sub>+<xsl:value-of select="configure/nwarningdiff"/></sub></xsl:if>
      <xsl:if test="configure/nwarningdiff &lt; 0"><sub><xsl:value-of select="configure/nwarningdiff"/></sub></xsl:if>
      </td>

      <td align="right">
      <xsl:value-of select="configure/time"/>
      <xsl:if test="string-length(configure/time)=0"><xsl:text disable-output-escaping="yes">0</xsl:text></xsl:if> 
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
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="compilation/error"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="compilation/error"/>
        </xsl:if>
      <xsl:if test="string-length(compilation/error)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>   
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
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="compilation/warning"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="compilation/warning"/>
        </xsl:if>
      <xsl:if test="string-length(compilation/warning)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="compilation/nwarningdiff > 0"><sub>+<xsl:value-of select="compilation/nwarningdiff"/></sub></xsl:if>
      <xsl:if test="compilation/nwarningdiff &lt; 0"><sub><xsl:value-of select="compilation/nwarningdiff"/></sub></xsl:if>
      </td>

      <td align="right"><xsl:value-of select="compilation/time"/>
      <xsl:if test="string-length(compilation/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>   
      </td>

      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/notrun > 0">
            warning
            </xsl:when>
            <xsl:when test="string-length(test/notrun)>0">
            normal
            </xsl:when>
        </xsl:choose>
      </xsl:attribute>
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewTest.php?onlynotrun&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="test/notrun"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="test/notrun"/>
        </xsl:if>
      <xsl:if test="string-length(test/notrun)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="test/nnotrundiff > 0"><sub>+<xsl:value-of select="test/nnotrundiff"/></sub></xsl:if>
      <xsl:if test="test/nnotrundiff &lt; 0"><sub><xsl:value-of select="test/nnotrundiff"/></sub></xsl:if>
      </td>

      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
            error
            </xsl:when>
          <xsl:when test="string-length(test/fail)>0">
            normal  
          </xsl:when>  
        </xsl:choose>
      </xsl:attribute>
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewTest.php?onlyfailed&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="test/fail"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="test/fail"/>
        </xsl:if>
      <xsl:if test="string-length(test/fail)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>  
      <xsl:if test="test/nfaildiff > 0"><sub>+<xsl:value-of select="test/nfaildiff"/></sub></xsl:if>
      <xsl:if test="test/nfaildiff &lt; 0"><sub><xsl:value-of select="test/nfaildiff"/></sub></xsl:if>
      </td>

      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/fail > 0">
             error
            </xsl:when>
             <xsl:when test="string-length(test/fail)>0">
             normal
             </xsl:when>       
        </xsl:choose>
      </xsl:attribute>
        <xsl:if test="countbuildids=1">
        <a>
        <xsl:attribute name="href">viewTest.php?onlypassed&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="test/pass"/>
        </a>
        </xsl:if>
        <xsl:if test="countbuildids!=1">
          <xsl:value-of select="test/pass"/>
        </xsl:if>
      <xsl:if test="string-length(test/fail)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>  
      <xsl:if test="test/npassdiff > 0"><sub>+<xsl:value-of select="test/npassdiff"/></sub></xsl:if>
      <xsl:if test="test/npassdiff &lt; 0"><sub><xsl:value-of select="test/npassdiff"/></sub></xsl:if>
      </td>

      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="test/timestatus > 0">
             error
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
             <xsl:if test="string-length(test/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>  
             <xsl:if test="test/ntimediff > 0"><sub>+<xsl:value-of select="test/ntimediff"/></sub></xsl:if>
             <xsl:if test="test/ntimediff &lt; 0"><sub><xsl:value-of select="test/ntimediff"/></sub></xsl:if>
           </xsl:when>  
             <xsl:when test="string-length(test/timestatus)=0">
               <xsl:value-of select="test/time"/>
               <xsl:if test="string-length(test/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>  
             </xsl:when>
        </xsl:choose>
      </td>

      <td>
        <xsl:value-of select="builddate"/>
      </td>

      <td class="nob" align="left">
      <xsl:if test="count(labels/label)=0">(none)</xsl:if>
      <xsl:if test="count(labels/label)=1"><xsl:value-of select="labels/label"/></xsl:if>
      <xsl:if test="count(labels/label)>1">(<xsl:value-of select="count(labels/label)"/> labels)</xsl:if>
      </td>
   </tr>
  </xsl:for-each>
  </tbody>

  </xsl:if>
  <!-- end "count($type/build)>0" -->


  <!-- Row displaying the totals -->
  <xsl:if test="count(/cdash/buildgroup/build/buildid)>0 and $type/last=1">
  <tbody>
    <tr class="total">
      <td width="15%" align="left">Totals</td>
      <td width="15%" align="center"><b><xsl:value-of select = "count(/cdash/buildgroup/build/buildid)" /> Builds</b></td>
      <td width="2%" align="center">
       <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="/cdash/totalUpdateError!=0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
        </xsl:attribute>
      <xsl:value-of select = "/cdash/totalUpdatedFiles"/>
      </td>
      <td width="3%" align="right"><xsl:value-of select = "/cdash/totalUpdateDuration"/></td>
      <td width="5%" align="center">
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
      <td width="5%" align="center">
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
      <td width="5%" align="right">
        <xsl:value-of select = "/cdash/totalConfigureDuration"/>
      </td>
      <td width="5%" align="center">
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
      <td width="5%" align="center">
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
      <td width="5%" align="right">
        <xsl:value-of select = "/cdash/totalBuildDuration"/>
      </td>
      <td width="6%" align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalNotRun > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
            normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "/cdash/totalNotRun"/></b>
      </td>
      <td width="3%" align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalFail > 0">
            error
            </xsl:when>
          <xsl:otherwise>
            normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "/cdash/totalFail"/></b>  
      </td>
      <td width="3%" align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/totalFail > 0">
            error
            </xsl:when>
          <xsl:otherwise>
            normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>   
      <b><xsl:value-of select = "/cdash/totalPass"/></b>
      </td>
      <td width="3%" align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/enableTestTiming != 0">
          normal
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
        <xsl:value-of select = "/cdash/totalTestsDuration"/>
      </td>
      <td width="10%"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>
      <td width="10%" class="nob"></td>
      <!-- <td bgcolor="#ffffff"></td> -->
    </tr>
  </tbody>
  </xsl:if>
  <!-- end "Row displaying the totals" -->

  <!-- footer for the group when build>0 -->
  <xsl:choose>         
  <xsl:when test="/cdash/uselocaldirectory=1">
    <xsl:call-template name="groupfooter_local">
      <xsl:with-param name="type" select="."/>
    </xsl:call-template>
  </xsl:when>
  <xsl:otherwise>
    <xsl:call-template name="groupfooter">
      <xsl:with-param name="type" select="."/>
    </xsl:call-template>
  </xsl:otherwise>
  </xsl:choose>

  
</xsl:template>
<!-- end template -->


   <xsl:include href="filterdataTemplate.xsl"/>
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>

   <!-- Include local common files -->
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/>

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
                  
         <!-- Include JavaScript -->
         <script src="javascript/cdashBuildGroup.js" type="text/javascript" charset="utf-8"></script>
         <script src="javascript/cdashFilters.js" type="text/javascript" charset="utf-8"></script>
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
<div id="index_content">
<xsl:if test="cdash/dashboard/future=0">

<xsl:if test="cdash/updates">
<table width="100%" cellpadding="11" cellspacing="0">
  <xsl:for-each select="cdash/banner">
  <tr bgcolor="#DDDDDD">
  <td align="center" width="100%" colspan="2">
  <b><xsl:value-of select="text"/></b>
  </td>
  </tr>
  </xsl:for-each>  
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

<!-- Display the table dependencies -->
<xsl:if test="count(cdash/subproject/dependency)>0">
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
  <tr class="table-heading1">
      <td colspan="2" class="nob">
          <h3><a href="#" class="grouptrigger">SubProject Dependencies</a></h3>
      </td>
   <!-- quick links -->
  <td colspan="9" align="right" class="nob">
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
     <td align="center" rowspan="2" width="20%"><b>Project</b></td>
     <td align="center" colspan="3" width="20%"><b>Configure</b></td>
     <td align="center" colspan="3" width="20%"><b>Build</b></td>
     <td align="center" colspan="3" width="20%"><b>Test</b></td>
     <td align="center" rowspan="2" width="20%" class="nob"><b>Last submission</b></td>
  </tr>
   <tr class="table-heading">
     <td align="center"><b>Error</b></td>
     <td align="center"><b>Warning</b></td>
     <td align="center"><b>Pass</b></td>
     <td align="center"><b>Error</b></td>
     <td align="center"><b>Warning</b></td>
     <td align="center"><b>Pass</b></td>
     <td align="center"><b>Not Run</b></td>
     <td align="center"><b>Fail</b></td>
     <td align="center"><b>Pass</b></td>
  </tr>
   
  <xsl:for-each select="cdash/subproject/dependency">
   <tr>
      <xsl:attribute name="class"><xsl:value-of select="rowparity"/></xsl:attribute>
      <td align="center" class="paddt"><a>
       <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname"/>&amp;subproject=<xsl:value-of select="name"/>&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:attribute>
      <xsl:value-of select="name"/>
      </a></td>

    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nconfigureerror>0">error</xsl:when>
          <xsl:otherwise> 
          <xsl:choose>
          <xsl:when test="nconfigureerror=0 and nconfigurewarning=0 and nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>    
    <xsl:if test="nconfigureerror!=0 or nconfigurewarning!=0 or nconfigurepass!=0">  
    <xsl:value-of select="nconfigureerror"/>
    </xsl:if>
    </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nconfigurewarning>0">warning</xsl:when>
          <xsl:otherwise>
          <xsl:choose>
          <xsl:when test="nconfigureerror=0 and nconfigurewarning=0 and nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>    
    <xsl:if test="nconfigureerror!=0 or nconfigurewarning!=0 or nconfigurepass!=0">  
    <xsl:value-of select="nconfigurewarning"/>
    </xsl:if>
    </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nconfigureerror=0 and nconfigurewarning=0 and nconfigurepass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
        </xsl:choose>
      </xsl:attribute> 
    <xsl:if test="nconfigureerror!=0 or nconfigurewarning!=0 or nconfigurepass!=0">        
    <xsl:value-of select="nconfigurepass"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nbuilderror>0">error</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="nbuilderror=0 and nbuildwarning=0 and nbuildpass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>    
    <xsl:if test="nbuilderror!=0 or nbuildwarning!=0 or nbuildpass!=0">        
    <xsl:value-of select="nbuilderror"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nbuildwarning>0">warning</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="nbuilderror=0 and nbuildwarning=0 and nbuildpass=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>    
    <xsl:if test="nbuilderror!=0 or nbuildwarning!=0 or nbuildpass!=0">     
    <xsl:value-of select="nbuildwarning"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nbuildpass>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>    
    <xsl:if test="nbuilderror!=0 or nbuildwarning!=0 or nbuildpass!=0">     
    <xsl:value-of select="nbuildpass"/>
    </xsl:if>
    </td>
   <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="ntestnotrun>0">error</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="ntestfail=0 and ntestpass=0 and ntestnotrun=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
    <xsl:if test="ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0">
    <xsl:value-of select="ntestnotrun"/>
    </xsl:if>
    </td>
  <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="ntestfail>0">error</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="ntestfail=0 and ntestpass=0 and ntestnotrun=0"></xsl:when>
          <xsl:otherwise>normal</xsl:otherwise>
          </xsl:choose></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>  
    <xsl:if test="ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0">    
    <xsl:value-of select="ntestfail"/>
    </xsl:if>
    </td>
  <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="ntestpass>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute> 
    <xsl:if test="ntestfail!=0 or ntestpass!=0 or ntestnotrun!=0">   
    <xsl:value-of select="ntestpass"/>
    </xsl:if>
    </td>
      
      
      
      <td align="center"  class="nob"><xsl:value-of select="lastsubmission"/></td>
   </tr>
  </xsl:for-each>
</tbody>
</table>
</xsl:if>
 
<!-- Filters? -->
<xsl:if test="count(cdash/filterdata) = 1">
  <xsl:call-template name="filterdata" select="."/>
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
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb" id="coveragetable">
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
<thead>
<tr class="table-heading2">
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger">Coverage</a></h3>
      </td>
   <!-- quick links -->
  <td colspan="6" align="right" class="nob">
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
      <th align="center" width="20%" id="sortcoveragesort_0">Site</th>
      <th align="center" width="25%" id="sortcoveragesort_1">Build Name</th>
      <th align="center" width="10%" id="sortcoveragesort_2">Percentage</th>
      <th align="center"  width="10%" id="sortcoveragesort_3" >Passed</th>
      <th align="center"  width="10%" id="sortcoveragesort_4">Failed</th>
      <th align="center" width="15%" id="sortcoveragesort_5">Date</th>
      <th align="center" class="nob" id="sortcoveragesort_6" width="10%">Labels</th>
   </tr>
</thead>
<tbody>   
  <xsl:for-each select="cdash/buildgroup/coverage">
   
   <tr>
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
      <xsl:if test="percentagediff > 0"><sub>+<xsl:value-of select="percentagediff"/>%</sub></xsl:if>
      <xsl:if test="percentagediff &lt; 0"><sub><xsl:value-of select="percentagediff"/>%</sub></xsl:if>
      </td>
      <td align="center" ><b><xsl:value-of select="pass"/></b>
      <xsl:if test="passdiff > 0"><sub>+<xsl:value-of select="passdiff"/></sub></xsl:if>
      <xsl:if test="passdiff &lt; 0"><sub><xsl:value-of select="passdiff"/></sub></xsl:if>    
      </td>
      <td align="center" ><b><xsl:value-of select="fail"/></b>
      <xsl:if test="faildiff > 0"><sub>+<xsl:value-of select="faildiff"/></sub></xsl:if>
      <xsl:if test="faildiff &lt; 0"><sub><xsl:value-of select="faildiff"/></sub></xsl:if>    
      </td>
      <td align="left"><xsl:value-of select="date"/></td>

      <td class="nob" align="left">
      <xsl:if test="count(labels/label)=0">(none)</xsl:if>
      <xsl:if test="count(labels/label)!=0"><xsl:value-of select="labels/label"/></xsl:if>
      <!-- <xsl:if test="count(labels/label)>1">(multiple)</xsl:if> -->
      </td>
   </tr>
  </xsl:for-each>
</tbody>
</xsl:if>
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
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb" id="dynamicanalysistable"> 
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
  <thead>
      <tr class="table-heading3">
      <td colspan="1" class="nob">
          <h3><a href="#" class="grouptrigger">Dynamic Analysis</a></h3>
      </td>
      <!-- quick links -->
  <td colspan="5" align="right" class="nob">
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
      <th align="center" width="20%" id="sortdynanalysissort_0">Site</th>
      <th align="center" width="25%" id="sortdynanalysissort_1">Build Name</th>
      <th align="center" width="20%" id="sortdynanalysissort_2">Checker</th>
      <th align="center" width="10%" id="sortdynanalysissort_3">Defect Count</th>
      <th align="center" width="15%" id="sortdynanalysissort_4">Date</th>
      <th align="center" class="nob" id="sortdynanalysissort_5" width="10%">Labels</th>
   </tr>
</thead>
<tbody> 
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
      <td align="left"><xsl:value-of select="date"/></td>

      <td class="nob" align="left">
      <xsl:if test="count(labels/label)=0">(none)</xsl:if>
      <xsl:if test="count(labels/label)!=0"><xsl:value-of select="labels/label"/></xsl:if>
      <!-- <xsl:if test="count(labels/label)>1">(multiple)</xsl:if> -->
      </td>
   </tr>
  </xsl:for-each>
</tbody>
</xsl:if>
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

</div>
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
