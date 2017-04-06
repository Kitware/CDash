<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

<xsl:template name="builds">
  <xsl:param name="type"/>


  <xsl:if test="count($type/build)=0">
    <tr class="table-heading1 table-nobuild">
      <td colspan="1" class="nob">
        <h3><a href="#" class="grouptrigger">No <xsl:value-of select="name"/> Builds</a></h3>
      </td>

      <!-- quick links -->
      <td align="right" class="nob">
      <xsl:choose>
      <xsl:when test="/cdash/dashboard/displaylabels=1">
        <xsl:attribute name="colspan">15</xsl:attribute>
      </xsl:when>
      <xsl:otherwise>
        <xsl:attribute name="colspan">14</xsl:attribute>
      </xsl:otherwise>
      </xsl:choose>
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
      <td align="right" class="nob">
      <xsl:choose>
      <xsl:when test="/cdash/dashboard/displaylabels=1">
        <xsl:attribute name="colspan">15</xsl:attribute>
      </xsl:when>
      <xsl:otherwise>
        <xsl:attribute name="colspan">14</xsl:attribute>
      </xsl:otherwise>
      </xsl:choose>
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
      <th align="center" rowspan="2" width="25%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_1</xsl:attribute>
      Build Name</th>
      <td align="center" colspan="1" width="5%" class="timeheader botl">Update</td>
      <td align="center" colspan="2" width="10%" class="timeheader botl">Configure</td>
      <td align="center" colspan="2" width="10%" class="timeheader botl">Build</td>
      <td align="center" colspan="3" width="15%" class="timeheader botl">Test</td>
      <td align="center" width="20%" class="timeheader botl"></td>

      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <td align="center" width="5%" class="timeheader botl"></td>
      </xsl:if>
   </tr>

   <tr class="table-heading">
      <th align="center" width="3%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_2</xsl:attribute>
      Files</th>
      <th align="center" class="advancedviewitem" width="5%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_3</xsl:attribute>
      Time</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_4</xsl:attribute>
      Error</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_5</xsl:attribute>
      Warn</th>
      <th align="center" class="advancedviewitem" width="5%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_6</xsl:attribute>
      Time</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_7</xsl:attribute>
      Error</th>
      <th align="center">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_8</xsl:attribute>
      Warn</th>
      <th align="center" class="advancedviewitem" width="5%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_9</xsl:attribute>
      Time</th>
      <th align="center" width="5%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_10</xsl:attribute>
      Not Run</th>
      <th align="center" width="5%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_11</xsl:attribute>
      Fail</th>
      <th align="center" width="5%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_12</xsl:attribute>
      Pass</th>

      <th align="center" width="5%" class="advancedviewitem">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_13</xsl:attribute>
      Time</th>
      <th align="center" width="20%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_14</xsl:attribute>
      <xsl:if test="/cdash/dashboard/displaylabels=0">
        <xsl:attribute name="class">nob</xsl:attribute>
      </xsl:if>
      Build Time</th>
      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <th align="center" rowspan="2" width="5%" class="nob">
        <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_15</xsl:attribute>
        Labels</th>
      </xsl:if>
   </tr>
   </thead>

   <tbody>
     <xsl:for-each select="$type/build">
   <tr valign="middle">
      <td align="left" class="paddt">
      <a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="siteid"/>&#38;project=<xsl:value-of select="/cdash/dashboard/projectid"/>&#38;currenttime=<xsl:value-of select="/cdash/dashboard/unixtimestamp"/></xsl:attribute><xsl:value-of select="site"/></a>

      <xsl:if test="siteoutoforder=1">
         <img border="0" src="img/flag.png" title="flag"></img>
      </xsl:if>

      </td>
      <td align="left">
      <xsl:if test="string-length(buildid)>0">
      <xsl:if test="string-length(buildplatform)>0">
        <div style="float:left;">
        <img class="icon" alt="platform">
            <xsl:attribute name="src">img/platform_<xsl:value-of select="buildplatform"/>.png</xsl:attribute>
        </img>
        </div>
      </xsl:if>
      <div style="float: left; margin: 0px 4px;">
      <a class="buildinfo">
        <xsl:if test="countchildren=0">
        <xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
        </xsl:if>
        <xsl:if test="countchildren!=0">
        <xsl:attribute name="href"><xsl:value-of select="multiplebuildshyperlink"/>
        </xsl:attribute>
        </xsl:if>

        <xsl:attribute name="alt">
           <xsl:value-of select="generator"/>
        </xsl:attribute>

        <xsl:value-of select="buildname"/>
      </a>
      </div>
     </xsl:if>
     <xsl:if test="string-length(buildid)=0">
       <div style="float: left; margin: 0px 4px;"><xsl:value-of select="buildname"/></div>
     </xsl:if>
     <xsl:text>&#x20;</xsl:text>

      <div style="float:left;">
      <xsl:if test="string-length(note)>0 and countchildren=0">
        <a title="View notes"><xsl:attribute name="href">viewNotes.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><img src="img/document.png" alt="Notes" class="icon"/></a>
      </xsl:if>

      <xsl:if test="upload-file-count>0 and countchildren=0">
      <a><xsl:attribute name="href">viewFiles.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute>
         <xsl:attribute name="title"><xsl:value-of select="upload-file-count" /> files uploaded with this build</xsl:attribute>
      <img src="img/package.png" alt="Files" class="icon"/></a>
      </xsl:if>

      <!-- If the build has errors or test failing -->
      <xsl:if test="(compilation/error > 0 or test/fail > 0) and countchildren=0">
      <a href="javascript:;">
      <xsl:attribute name="onclick">javascript:buildinfo_click(<xsl:value-of select="buildid"/>)</xsl:attribute>
      <img src="img/Info.png" alt="info" class="icon"></img>
      </a>
      </xsl:if>

      <!-- If the build is expected -->
      <xsl:if test="expected=1">
      <a>
      <xsl:attribute name="href">javascript:expectedinfo_click('<xsl:value-of select="siteid"/>','<xsl:value-of select="buildname"/>','<xsl:value-of select="expecteddivname"/>','<xsl:value-of select="/cdash/dashboard/projectid"/>','<xsl:value-of select="buildtype"/>','<xsl:value-of select="/cdash/dashboard/unixtimestamp"/>')</xsl:attribute>
      <img src="img/Info.png" alt="info" class="icon"></img>
      </a>
      </xsl:if>

      <!-- Display the note icon -->
      <xsl:if test="buildnote>0 and countchildren=0">
      <a name="Build Notes" class="jTip">
      <xsl:attribute name="id">buildnote_<xsl:value-of select="buildid"/></xsl:attribute>
      <xsl:attribute name="href">ajax/buildnote.php?buildid=<xsl:value-of select="buildid"/>&amp;width=350&amp;link=buildSummary.php%3Fbuildid%3D<xsl:value-of select="buildid"/></xsl:attribute>
      <img src="img/note.png" alt="note" class="icon"></img>
      </a>
      </xsl:if>

      <!-- If user is admin of the project propose to group this build -->
      <xsl:if test="/cdash/user/admin=1 and (countchildren=0 or expected=1)">
        <xsl:if test="string-length(buildid)>0">
        <a>
        <xsl:attribute name="href">javascript:buildgroup_click(<xsl:value-of select="buildid"/>)</xsl:attribute>
        <img src="img/folder.png" class="icon"></img>
        </a>
        </xsl:if>
        <xsl:if test="string-length(buildid)=0">
        <a>
        <xsl:attribute name="href">javascript:buildnosubmission_click('<xsl:value-of select="siteid"/>','<xsl:value-of select="buildname"/>','<xsl:value-of select="expecteddivname"/>','<xsl:value-of select="buildgroupid"/>','<xsl:value-of select="buildtype"/>')</xsl:attribute>
        <img src="img/folder.png" class="icon"></img>
        </a>
        </xsl:if>
      </xsl:if> <!-- end admin -->
      </div>


      <xsl:if test="string-length(buildid)>0 and countchildren=0">
      <div>
      <xsl:attribute name="id">buildgroup_<xsl:value-of select="buildid"/></xsl:attribute>
      </div>
      </xsl:if>

      <xsl:if test="string-length(expecteddivname)>0 and (countchildren=0 or expected=1)">
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
            <xsl:if test="update/defined=1">
            normal
            </xsl:if>
            </xsl:otherwise>
            </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
        <xsl:if test="countchildren=0">
        <xsl:if test="userupdates>0"><img src="img/yellowled.png" height="10px" alt="star" title="I checked in some code for this build!"/><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if><a>
        <xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="update/files"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="update/files"/>
        </xsl:if>
      </td>

      <td align="right" class="advancedviewitem">
        <span class="sorttime" style="display:none">sorttime=<xsl:value-of select="update/timefull"/>#</span>
        <xsl:value-of select="update/time"/>
        <xsl:if test="string-length(update/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      </td>

      <td align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="configure/error!=0">
            error
            </xsl:when>
           <xsl:when test="string-length(configure/error)>0">
           normal
           </xsl:when>
        </xsl:choose>
      </xsl:attribute>
        <xsl:if test="countchildren=0">
        <a>
        <xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="configure/error"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="configure/error"/>
        </xsl:if>
      <xsl:if test="string-length(configure/error)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
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
        <xsl:if test="countchildren=0">
        <a>
        <xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="configure/warning"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="configure/warning"/>
        </xsl:if>
      <xsl:if test="string-length(configure/warning)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="configure/nwarningdiff > 0 and countchildren=0"><sub>+<xsl:value-of select="configure/nwarningdiff"/></sub></xsl:if>
      <xsl:if test="configure/nwarningdiff &lt; 0 and countchildren=0"><sub><xsl:value-of select="configure/nwarningdiff"/></sub></xsl:if>
      </td>


      <td align="center" class="advancedviewitem">
      <span class="sorttime" style="display:none">sorttime=<xsl:value-of select="configure/timefull"/>#</span>
      <xsl:value-of select="configure/time"/>
      <xsl:if test="string-length(configure/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
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

       <div>
       <xsl:if test="compilation/nerrordiffp > 0 or compilation/nerrordiffn > 0">
          <xsl:attribute name="class">valuewithsub</xsl:attribute>
       </xsl:if>
        <xsl:if test="countchildren=0">
        <a>
        <xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="compilation/error"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="compilation/error"/>
        </xsl:if>
      <xsl:if test="string-length(compilation/error)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="compilation/nerrordiffp > 0 and countchildren=0">
      <a class="sup">
        <xsl:attribute name="href">viewBuildError.php?onlydeltap&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute>
        +<xsl:value-of select="compilation/nerrordiffp"/>
      </a>
      </xsl:if>
      <xsl:if test="compilation/nerrordiffn > 0 and countchildren=0">
      <a>
      <xsl:attribute name="href">viewBuildError.php?onlydeltan&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute>
      <span class="sub">-<xsl:value-of select="compilation/nerrordiffn"/></span>
      </a>
      </xsl:if>
      </div>
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
       <div>
       <xsl:if test="compilation/nwarningdiffp > 0 or compilation/nwarningdiffn > 0">
          <xsl:attribute name="class">valuewithsub</xsl:attribute>
       </xsl:if>

        <xsl:if test="countchildren=0">
        <a>
        <xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="compilation/warning"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="compilation/warning"/>
        </xsl:if>
      <xsl:if test="string-length(compilation/warning)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="compilation/nwarningdiffp > 0 and countchildren=0">
      <a class="sup">
        <xsl:attribute name="href">viewBuildError.php?type=1&#38;onlydeltap&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute>
        +<xsl:value-of select="compilation/nwarningdiffp"/>
      </a>
      </xsl:if>
      <xsl:if test="compilation/nwarningdiffn > 0 and countchildren=0">
      <a>
      <xsl:attribute name="href">viewBuildError.php?type=1&#38;onlydeltan&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute>
      <span class="sub">-<xsl:value-of select="compilation/nwarningdiffn"/></span>
      </a>
      </xsl:if>
      </div>

      </td>

      <td align="center" class="advancedviewitem">
      <span class="sorttime" style="display:none">sorttime=<xsl:value-of select="compilation/timefull"/>#</span>
      <xsl:value-of select="compilation/time"/>
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
      <div>
       <xsl:if test="test/nnotrundiffp > 0 or test/nnotrundiffn > 0">
          <xsl:attribute name="class">valuewithsub</xsl:attribute>
       </xsl:if>
        <xsl:if test="countchildren=0">
        <a>
        <xsl:attribute name="href">viewTest.php?onlynotrun&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="test/notrun"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="test/notrun"/>
        </xsl:if>
      <xsl:if test="string-length(test/notrun)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="test/nnotrundiffp > 0 and countchildren=0">
      <a class="sup">
        <xsl:attribute name="href">viewTest.php?onlydelta&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute>
        +<xsl:value-of select="test/nnotrundiffp"/>
      </a>
      </xsl:if>
      <xsl:if test="test/nnotrundiffn > 0 and countchildren=0">
      <span class="sub">-<xsl:value-of select="test/nnotrundiffn"/></span>
      </xsl:if>
      </div>
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
       <div>
       <xsl:if test="test/nfaildiffp > 0 or test/nfaildiffn > 0">
          <xsl:attribute name="class">valuewithsub</xsl:attribute>
       </xsl:if>
        <xsl:if test="countchildren=0">
        <a>
        <xsl:attribute name="href">viewTest.php?onlyfailed&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="test/fail"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="test/fail"/>
        </xsl:if>
      <xsl:if test="string-length(test/fail)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="test/nfaildiffp > 0 and countchildren=0"><a class="sup">
        <xsl:attribute name="href">viewTest.php?onlydelta&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute>
        +<xsl:value-of select="test/nfaildiffp"/></a>
      </xsl:if>
      <xsl:if test="test/nfaildiffn > 0 and countchildren=0"><span class="sub">-<xsl:value-of select="test/nfaildiffn"/></span>
      </xsl:if>
      </div>
      </td>

      <td align="center">
      <xsl:attribute name="class">
        <xsl:choose>
             <xsl:when test="string-length(test/pass)>0">
             normal
             </xsl:when>
        </xsl:choose>
      </xsl:attribute>
       <div>
       <xsl:if test="test/npassdiffp > 0 or test/npassdiffn > 0">
          <xsl:attribute name="class">valuewithsub</xsl:attribute>
       </xsl:if>
       <xsl:if test="countchildren=0">
        <a>
        <xsl:attribute name="href">viewTest.php?onlypassed&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>
          <xsl:value-of select="test/pass"/>
        </a>
        </xsl:if>
        <xsl:if test="countchildren!=0">
          <xsl:value-of select="test/pass"/>
        </xsl:if>
      <xsl:if test="string-length(test/fail)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      <xsl:if test="test/npassdiffp > 0 and countchildren=0">
      <a class="sup">
        <xsl:attribute name="href">viewTest.php?onlydelta&#38;buildid=<xsl:value-of select="buildid"/>
        </xsl:attribute>+<xsl:value-of select="test/npassdiffp"/>
      </a>
      </xsl:if>

      <xsl:if test="test/npassdiffn > 0 and countchildren=0">
      <span class="sub">-<xsl:value-of select="test/npassdiffn"/></span>
      </xsl:if>
      </div>
      </td>

      <td align="center">
      <xsl:attribute name="class">advancedviewitem
        <xsl:choose>
          <xsl:when test="test/timestatus > 0">
             error
            </xsl:when>
             <xsl:when test="string-length(test/timestatus)>0">
             normal
             </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:if test="string-length(test/timestatus)=0">
         <span class="sorttime" style="display:none">sorttime=<xsl:value-of select="test/timefull"/>#</span>
       </xsl:if>
      <div>
       <xsl:if test="test/ntimediffp > 0 or test/ntimediffn > 0">
          <xsl:attribute name="class">valuewithsub</xsl:attribute>
       </xsl:if>
      <xsl:choose>
          <xsl:when test="test/timestatus > 0">
             <a><xsl:attribute name="href">viewTest.php?onlytimestatus&#38;buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="test/timestatus"/></a>
             <xsl:if test="test/ntimediffp > 0">
             <a class="sup">
              <xsl:attribute name="href">viewTest.php?onlydelta&#38;buildid=<xsl:value-of select="buildid"/>
              </xsl:attribute>+<xsl:value-of select="test/ntimediffp"/>
             </a>
             </xsl:if>
             <xsl:if test="test/ntimediffn > 0">
              <span class="sub">-<xsl:value-of select="test/ntimediffn"/></span>
             </xsl:if>
          </xsl:when>
           <xsl:when test="string-length(test/timestatus)>0">
             <xsl:value-of select="test/time"/>
             <xsl:if test="string-length(test/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
             <xsl:if test="test/ntimediffp > 0 and countchildren=0">
             <a class="sup">
              <xsl:attribute name="href">viewTest.php?onlydelta&#38;buildid=<xsl:value-of select="buildid"/>
              </xsl:attribute>+<xsl:value-of select="test/ntimediffp"/>
             </a>
             </xsl:if>
             <xsl:if test="test/ntimediffn > 0 and countchildren=0">
              <span class="sub">-<xsl:value-of select="test/ntimediffn"/></span>
              </xsl:if>
           </xsl:when>
             <xsl:when test="string-length(test/timestatus)=0">
               <xsl:value-of select="test/time"/>
               <xsl:if test="string-length(test/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
             </xsl:when>
        </xsl:choose>
        </div>
      </td>

      <td align="center">
        <xsl:if test="/cdash/dashboard/displaylabels=0">
         <xsl:attribute name="class">nob</xsl:attribute>
        </xsl:if>


        <xsl:choose>
        <xsl:when test="string-length(builddate)=0">
          <span class="builddateelapsed" alt="Expected build">
           <xsl:attribute name="alt">Expected submit time: <xsl:value-of select="expectedstarttime" /></xsl:attribute>
           Expected build
          </span>
        </xsl:when>
        <xsl:otherwise>
        <span class="sorttime" style="display:none">sorttime=<xsl:value-of select="builddatefull"/>#</span>
        <span class="builddateelapsed">
           <xsl:attribute name="alt"><xsl:value-of select="builddate"/>
           <xsl:text disable-output-escaping="yes">&lt;br&gt;</xsl:text>Update time: <xsl:value-of select="update/time"/>
           <xsl:text disable-output-escaping="yes">&lt;br&gt;</xsl:text>Configure time: <xsl:value-of select="configure/time"/>
           <xsl:text disable-output-escaping="yes">&lt;br&gt;</xsl:text>Compilation time: <xsl:value-of select="compilation/time"/>
           <xsl:text disable-output-escaping="yes">&lt;br&gt;</xsl:text>Test time: <xsl:value-of select="test/time"/></xsl:attribute>
           <xsl:value-of select="builddateelapsed"/>
        </span>
        </xsl:otherwise>
        </xsl:choose>
      </td>

      <!-- display the labels -->
      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <td class="nob" align="left">
        <xsl:if test="count(labels/label)=0">(none)</xsl:if>
        <xsl:if test="count(labels/label)=1"><xsl:value-of select="labels/label"/></xsl:if>
        <xsl:if test="count(labels/label)>1">(<xsl:value-of select="count(labels/label)"/> labels)</xsl:if>
        </td>
      </xsl:if>
   </tr>
  </xsl:for-each>
  </tbody>

  </xsl:if>
  <!-- end "count($type/build)>0" -->


  <!-- Row displaying the totals -->
  <!--
  <xsl:if test="count($type/build/buildid)>0">
  <tbody>
    <tr class="total">
      <td width="30%" colspan="2" align="center"><b><xsl:value-of select = "count($type/build/buildid)" /> build<xsl:if test="count($type/build/buildid)>1">s</xsl:if></b></td>
      <td width="5%" align="center">
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
      <xsl:value-of select = "$type/totalUpdatedFiles"/>
      </td>
      <td width="5%" align="center">
       <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="$type/totalConfigureError!=0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "$type/totalConfigureError"/></b>
      </td>
      <td width="5%" align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="$type/totalConfigureWarning > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "$type/totalConfigureWarning"/></b>
      </td>
      <td width="5%" align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="$type/totalError > 0">
            error
            </xsl:when>
          <xsl:otherwise>
           normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "$type/totalError"/></b>
      </td>
      <td width="5%" align="center">
       <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="$type/totalWarning > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
             normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "$type/totalWarning"/></b>
      </td>
      <td width="6%" align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="$type/totalNotRun > 0">
            warning
            </xsl:when>
          <xsl:otherwise>
            normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "$type/totalNotRun"/></b>
      </td>
      <td width="3%" align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="$type/totalFail > 0">
            error
            </xsl:when>
          <xsl:otherwise>
            normal
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <b><xsl:value-of select = "$type/totalFail"/></b>
      </td>

      <td width="3%" align="center">
       <xsl:attribute name="class">normal</xsl:attribute>
      <b><xsl:value-of select = "$type/totalPass"/></b>
      </td>

      <td width="3%" align="center">
      <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="/cdash/enableTestTiming != 0">
          normal
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
        <xsl:value-of select = "$type/totalTestsDuration"/>
      </td>
      <td width="10%">
      <xsl:if test="/cdash/dashboard/displaylabels=0">
         <xsl:attribute name="class">nob</xsl:attribute>
      </xsl:if>
      <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>

      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <td width="10%" class="nob"></td>
      </xsl:if>
    </tr>
  </tbody>
  </xsl:if>
  -->
  <!-- end "Row displaying the totals" -->
</xsl:template>
<!-- end template -->

   <xsl:include href="filterdataTemplate.xsl"/>
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
   <xsl:include href="subprojectGroupCoverage.xsl"/>

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
        <link rel="stylesheet" href="css/bootstrap.min.css"/>

         <!-- Include JavaScript -->
         <script src="js/cdashBuildGroup.js" type="text/javascript" charset="utf-8"></script>
         <script src="js/cdashFilters.js" type="text/javascript" charset="utf-8"></script>
         <xsl:call-template name="headscripts"/>
         <xsl:if test="cdash/feed!=0">
           <script src="js/cdashFeed.js" type="text/javascript" charset="utf-8"></script>
         </xsl:if>
         <script src="js/cdashTableCollapse.js" type="text/javascript" charset="utf-8"></script>

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
<div id="index_top">
  <xsl:for-each select="cdash/banner">
  <div id="banner"><xsl:value-of select="text"/></div>
  </xsl:for-each>

  <div id="updatechanges">
    <xsl:if test="cdash/updates/nchanges=-1">No update data</xsl:if>
    <xsl:if test="cdash/updates/nchanges=0">No file changed</xsl:if>
    <xsl:if test="cdash/updates/nchanges>0">
      <a><xsl:attribute name="href"><xsl:value-of select="cdash/updates/url"/></xsl:attribute>
         <xsl:value-of select="cdash/updates/nchanges"/> file<xsl:if test="cdash/updates/nchanges>1">s</xsl:if>
          changed  </a>
         by <xsl:value-of select="cdash/updates/nauthors"/> author<xsl:if test="cdash/updates/nauthors>1">s</xsl:if>
    </xsl:if>
         as of
         <b><xsl:value-of select="cdash/updates/timestamp"/></b>
  </div>

<div id="settings">
<img src="img/settings.png"/>
<ul>
  <li><a href="" class="advancedview">Advanced View</a></li>
  <li><a href="" class="autorefresh">Auto-refresh</a></li>
  <li><a id="label_colorblind" href="javascript:colorblind_toggle();">
<xsl:if test="cdash/filterdata/colorblind = 0">Colorblind palette</xsl:if>
<xsl:if test="cdash/filterdata/colorblind != 0">Classic palette</xsl:if>
</a></li>
<li>
<a id="label_showfilters" href="javascript:filters_toggle();">
<xsl:if test="cdash/filterdata/showfilters = 0">Show Filters</xsl:if>
<xsl:if test="cdash/filterdata/showfilters != 0">Hide Filters</xsl:if>
</a>
</li>
  <xsl:if test="cdash/feed!=0">
    <li><a href="" class="showfeed">Hide Feed</a></li>
  </xsl:if>
  <li><a href="" class="helptrigger">Help</a></li>
</ul>
</div>

 <div id="helpmenu">
         <div class="jqmWindow" id="help">Loading help...</div>
         <div class="jqmWindow" id="groupsdescription">Loading group description...</div>
</div>

<!-- Filters? -->
<xsl:if test="count(cdash/filterdata) = 1">
  <xsl:call-template name="filterdata" select="."/>
</xsl:if>
</div>

<xsl:if test="cdash/feed!=0">
  <div id="feed"></div>
</xsl:if>

</xsl:if>

<xsl:if test="cdash/testingdataurl">
<table width="100%" cellpadding="11" cellspacing="0">
  <tr>
    <td height="25" align="left">
      Testing data for this project can be found at:
      <a><xsl:attribute name="href"><xsl:value-of select="cdash/testingdataurl"/></xsl:attribute><xsl:value-of select="cdash/testingdataurl"/></a>
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
       <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>&amp;subproject=<xsl:value-of select="name_encoded"/>&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:attribute>
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
          <xsl:when test="ntestnotrun>0">warning</xsl:when>
          <xsl:otherwise><xsl:choose>
          <xsl:when test="string-length(ntestnotrun)>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
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
          <xsl:when test="string-length(ntestfail)>0">normal</xsl:when>
          <xsl:otherwise></xsl:otherwise>
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

<!-- COVERAGE -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb" id="coveragetable">
    <xsl:if test="count(cdash/buildgroup/coverage)=0">
       <tr class="table-heading2 table-nobuild">
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
      <th align="center"  width="10%" id="sortcoveragesort_3" >LOC Tested</th>
      <th align="center"  width="10%" id="sortcoveragesort_4">LOC Untested</th>
      <th align="center" width="15%" id="sortcoveragesort_5">
      <xsl:if test="/cdash/dashboard/displaylabels=0">
       <xsl:attribute name="class">nob</xsl:attribute>
      </xsl:if>
      Date</th>

      <!-- display the labels -->
      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <th align="center" class="nob" id="sortcoveragesort_6" width="10%">Labels</th>
      </xsl:if>
   </tr>
</thead>
<tbody id="coveragebody">

  <xsl:choose>
    <xsl:when test="count(cdash/subprojectgroup)>0">
      <xsl:call-template name="subprojectGroupCoverage"/>
    </xsl:when>
    <xsl:otherwise>
      <xsl:for-each select="cdash/buildgroup/coverage">
        <xsl:call-template name="coverageRow"/>
      </xsl:for-each>
    </xsl:otherwise>
  </xsl:choose>

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


<!-- Dynamic analysis -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb" id="dynamicanalysistable">
    <xsl:if test="count(cdash/buildgroup/dynamicanalysis)=0">
   <tr class="table-heading3 table-nobuild" >
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
      <th align="center" width="15%" id="sortdynanalysissort_4">
      <xsl:if test="/cdash/dashboard/displaylabels=0">
        <xsl:attribute name="class">nob</xsl:attribute>
      </xsl:if>
      Date</th>
      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <th align="center" class="nob" id="sortdynanalysissort_5" width="10%">Labels</th>
      </xsl:if>
   </tr>
</thead>
<tbody>
  <xsl:for-each select="cdash/buildgroup/dynamicanalysis">

   <tr>
      <td align="left"><xsl:value-of select="site"/></td>
      <td align="left"><xsl:value-of select="buildname"/></td>
      <td align="center"><xsl:value-of select="checker"/></td>
      <td align="center">
        <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="status!='passed'">
            error
          </xsl:when>
          <xsl:otherwise>
            <xsl:choose>
            <xsl:when test="defectcount > 0">
             warning
            </xsl:when>
            <xsl:otherwise>
              normal
            </xsl:otherwise>
            </xsl:choose>
          </xsl:otherwise>
        </xsl:choose>
        </xsl:attribute>
        <a><xsl:attribute name="href">viewDynamicAnalysis.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute><xsl:value-of select="defectcount"/></a>
      </td>
      <td align="center">
      <xsl:if test="/cdash/dashboard/displaylabels=0">
        <xsl:attribute name="class">nob</xsl:attribute>
      </xsl:if>

      <span class="sorttime" style="display:none">sorttime=<xsl:value-of select="datefull"/>#</span>
      <span class="builddateelapsed">
         <xsl:attribute name="alt"><xsl:value-of select="date"/></xsl:attribute>
         <xsl:value-of select="dateelapsed"/>
      </span>
      </td>

      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <td class="nob" align="left">
        <xsl:if test="count(labels/label)=0">(none)</xsl:if>
        <xsl:if test="count(labels/label)!=0"><xsl:value-of select="labels/label"/></xsl:if>
        </td>
      </xsl:if>
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

<!-- footer  -->
<xsl:if test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="coverageheader_local"/>
</xsl:if>

</xsl:if> <!-- end dashboard is not in the future -->

<xsl:if test="cdash/dashboard/future=1">
<br/>
CDash cannot predict the future (yet)...
<br/>
</xsl:if> <!-- end dashboard is in the future -->
</div>
<!-- FOOTER -->
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
