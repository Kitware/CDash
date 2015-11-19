<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>




<xsl:template name="buildgroupheader">
  <xsl:param name="type"/>

  <xsl:if test="count($type/build)=0">
    <tr class="table-heading1">
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
      <th align="center" rowspan="2" width="15%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_0</xsl:attribute>
      Site</th>
      <th align="center" rowspan="2" width="15%">
      <xsl:attribute name="id">sort<xsl:value-of select="id"/>sort_1</xsl:attribute>
      Build Name</th>
      <td align="center" colspan="2" width="5%" class="botl">Update</td>
      <td align="center" colspan="3" width="10%" class="botl">Configure</td>
      <td align="center" colspan="3" width="10%" class="botl">Build</td>
      <td align="center" colspan="4" width="10%" class="botl">Test</td>
      <th align="center" rowspan="2" width="20%">
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
  </xsl:if>
</xsl:template>




<xsl:template name="buildrow">
  <tr valign="middle">

    <!-- column 1 -->
    <td width="15%" align="left" class="paddt">
      <a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="siteid"/>&#38;project=<xsl:value-of select="/cdash/dashboard/projectid"/>&#38;currenttime=<xsl:value-of select="/cdash/dashboard/unixtimestamp"/></xsl:attribute><xsl:value-of select="site"/></a>
    </td>

    <!-- column 2 -->
    <td width="15%" align="left">
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
      <a><xsl:attribute name="href">viewNotes.php?buildid=<xsl:value-of select="buildid"/> </xsl:attribute><img src="images/document.png" alt="Notes" border="0"/></a>
      </xsl:if>

      <!-- If the build has errors or test failing -->
      <xsl:if test="(compilation/error > 0 or test/fail > 0) and countbuildids=1">
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
      <xsl:if test="buildnote>0 and countbuildids=1">
      <a name="Build Notes" class="jTip">
      <xsl:attribute name="id">buildnote_<xsl:value-of select="buildid"/></xsl:attribute>
      <xsl:attribute name="href">ajax/buildnote.php?buildid=<xsl:value-of select="buildid"/>&amp;width=350&amp;link=buildSummary.php%3Fbuildid%3D<xsl:value-of select="buildid"/></xsl:attribute>
      <img src="images/note.png" border="0"></img>
      </a>
      </xsl:if>

      <!-- If user is admin of the project propose to group this build -->
      <xsl:if test="/cdash/user/admin=1 and (countbuildids=1 or expected=1)">
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

      <xsl:if test="string-length(expecteddivname)>0 and (countbuildids=1 or expected=1)">
      <div>
      <xsl:attribute name="id">infoexpected_<xsl:value-of select="expecteddivname"/></xsl:attribute>
      </div>
     </xsl:if>

     </td>

    <!-- column 3 -->
    <td width="2%" align="center">
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

    <!-- column 4 -->
    <td width="3%" align="right">
      <xsl:value-of select="update/time"/>
      <xsl:if test="string-length(update/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
      </td>

    <!-- column 5 -->
    <td width="5%" align="center">
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

    <!-- column 6 -->
    <td width="5%" align="center">
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

    <!-- column 7 -->
    <td width="5%" align="right">
      <xsl:value-of select="configure/time"/>
      <xsl:if test="string-length(configure/time)=0"><xsl:text disable-output-escaping="yes">0</xsl:text></xsl:if>
    </td>

    <!-- column 8 -->
    <td width="5%" align="center">
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

    <!-- column 9 -->
    <td width="5%" align="center">
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

    <!-- column 10 -->
    <td width="5%" align="right"><xsl:value-of select="compilation/time"/>
      <xsl:if test="string-length(compilation/time)=0"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></xsl:if>
    </td>

    <!-- column 11 -->
    <td width="6%" align="center">
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

    <!-- column 12 -->
    <td width="3%" align="center">
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

    <!-- column 13 -->
    <td width="3%" align="center">
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

    <!-- column 14 -->
    <td width="3%" align="center">
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

    <!-- column 15 -->
    <td width="10%">
        <xsl:if test="/cdash/dashboard/displaylabels=0">
         <xsl:attribute name="class">nob</xsl:attribute>
        </xsl:if>

        <xsl:if test="string-length(builddate)=0">
          Expected build
        </xsl:if>

        <xsl:value-of select="builddate"/>
    </td>

    <!-- column 16  (conditional) -->
    <!-- display the labels -->
    <xsl:if test="/cdash/dashboard/displaylabels=1">
      <td width="10%" class="nob" align="left">
        <xsl:if test="count(labels/label)=0">(none)</xsl:if>
        <xsl:if test="count(labels/label)=1"><xsl:value-of select="labels/label"/></xsl:if>
        <xsl:if test="count(labels/label)>1">(<xsl:value-of select="count(labels/label)"/> labels)</xsl:if>
      </td>
    </xsl:if>
  </tr>
</xsl:template>




<xsl:template name="buildgroupfooter">
  <xsl:param name="type"/>

  <!-- Row displaying the totals -->
  <xsl:if test="count($type/build/buildid)>0">
  <tbody>
    <tr class="total">
      <td width="15%" align="left">Totals</td>
      <td width="15%" align="center"><b><xsl:value-of select = "count($type/build/buildid)" /> Builds</b></td>
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
      <xsl:value-of select = "$type/totalUpdatedFiles"/>
      </td>
      <td width="3%" align="right"><xsl:value-of select = "$type/totalUpdateDuration"/></td>
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
      <td width="5%" align="right">
        <xsl:value-of select = "$type/totalConfigureDuration"/>
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
      <td width="5%" align="right">
        <xsl:value-of select = "$type/totalBuildDuration"/>
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

      <!-- display the labels -->
      <xsl:if test="/cdash/dashboard/displaylabels=1">
        <td width="10%" class="nob"></td>
      </xsl:if>
    </tr>
  </tbody>
  </xsl:if>
  <!-- end "Row displaying the totals" -->
</xsl:template>




<xsl:template name="buildgroupopentable">
</xsl:template>




<xsl:template name="buildgroupclosetable">
</xsl:template>




<xsl:template name="buildgroup">
  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <xsl:call-template name="buildgroupheader">
    <xsl:with-param name="type" select="."/>
  </xsl:call-template>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:call-template name="buildrow" />
    </xsl:for-each>
  </tbody>
  </xsl:if>

  <xsl:call-template name="buildgroupfooter">
    <xsl:with-param name="type" select="."/>
  </xsl:call-template>

  </table>
</xsl:template>




<xsl:template name="repeatthis">
<xsl:for-each select="cdash/buildgroup">
  <xsl:call-template name="buildgroup"/>
  <br/>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeMissing">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Missing Submissions (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="expected = 1">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeUpdateErrors">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Update Errors (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="update/errors != 0">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeConfigureErrors">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Configure Errors (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="configure/error != 0">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeBuildErrors">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Build Errors (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="compilation/error != 0">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeTestsFailed">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Test Failures (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="test/fail != 0">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeTestsNotRun">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Tests Not Run (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="test/notrun != 0">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeConfigureWarnings">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Configure Warnings (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="configure/warning != 0">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="summarizeBuildWarnings">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3>Build Warnings (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="compilation/warning != 0">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<xsl:template name="statistics">
<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0">

  <thead>
  <tr class="table-heading1" >
    <td><h3>Statistics for build group "<xsl:value-of select="name"/>"</h3></td>
    <td></td>
    <td></td>
  </tr>
  </thead>

  <tr>
    <td>total failures:</td>
    <td><xsl:value-of select="totalUpdateError + totalConfigureError + totalError + totalUpdateWarning + totalConfigureWarning + totalWarning + totalFail + totalNotRun + count(build/expected)"/></td>
    <td>sum of errors, warnings, failed tests, tests not run and missing submissions</td>
  </tr>

  <tr>
    <td>missingSubmissions:</td>
    <td><xsl:value-of select="count(build/expected)"/></td>
    <td></td>
  </tr>

  <tr>
    <td>percent tests passing:</td>
    <td><xsl:value-of select="format-number(100 * (totalPass) div (totalPass + totalFail + totalNotRun), '##.0')"/></td>
    <td></td>
  </tr>

  <tr>
    <td>average duration:</td>
    <td><xsl:value-of select="format-number((totalUpdateDuration + totalConfigureDuration + totalBuildDuration + totalTestsDuration) div (count(build) - count(build/expected)), '##.0')"/></td>
    <td>includes update, configure, build and test durations</td>
  </tr>

  <tr>
    <td>total submissions:</td>
    <td><xsl:value-of select="count(build) - count(build/expected)"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalUpdateError:</td>
    <td><xsl:value-of select="totalUpdateError"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalConfigureError:</td>
    <td><xsl:value-of select="totalConfigureError"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalError:</td>
    <td><xsl:value-of select="totalError"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalFail:</td>
    <td><xsl:value-of select="totalFail"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalNotRun:</td>
    <td><xsl:value-of select="totalNotRun"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalUpdateWarning:</td>
    <td><xsl:value-of select="totalUpdateWarning"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalConfigureWarning:</td>
    <td><xsl:value-of select="totalConfigureWarning"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalWarning:</td>
    <td><xsl:value-of select="totalWarning"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalPass:</td>
    <td><xsl:value-of select="totalPass"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalUpdatedFiles:</td>
    <td><xsl:value-of select="totalUpdatedFiles"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalUpdateDuration:</td>
    <td><xsl:value-of select="totalUpdateDuration"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalConfigureDuration:</td>
    <td><xsl:value-of select="totalConfigureDuration"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalBuildDuration:</td>
    <td><xsl:value-of select="totalBuildDuration"/></td>
    <td></td>
  </tr>

  <tr>
    <td>totalTestsDuration:</td>
    <td><xsl:value-of select="totalTestsDuration"/></td>
    <td></td>
  </tr>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>




<!--

<xsl:template name="buildrowWithUpdateErrors">
  <xsl:if test="update/errors != 0">
    <xsl:call-template name="buildrow" />
  </xsl:if>
</xsl:template>




<xsl:template name="summarizeThing">
  <xsl:param name="heading"/>
  <xsl:param name="thingTest"/>

<xsl:for-each select="cdash/buildgroup">
  <xsl:if test="contains(name, 'Nightly')">

  <table border="0" cellpadding="4" cellspacing="0" width="100%">
  <xsl:attribute name="class">tabb <xsl:value-of select="sortlist"/></xsl:attribute>
  <xsl:attribute name="id">project_<xsl:value-of select="/cdash/dashboard/projectid"/>_<xsl:value-of select="id"/></xsl:attribute>

  <thead>
  <tr class="table-heading1" >
    <td colspan="16" class="nob">
      <h3><xsl:value-of select="$heading"/> (<xsl:value-of select="name"/>)</h3>
    </td>
  </tr>
  </thead>

  <xsl:if test="count(build)>0">
  <tbody>
    <xsl:for-each select="build">
      <xsl:if test="$thingTest">
        <xsl:call-template name="buildrow" />
      </xsl:if>
    </xsl:for-each>
  </tbody>
  </xsl:if>

  </table>

  </xsl:if>
</xsl:for-each>
</xsl:template>

-->



  <xsl:include href="header.xsl"/>
  <xsl:include href="footer.xsl"/>

  <!-- Include local common files -->
  <xsl:include href="local/header.xsl"/>
  <xsl:include href="local/footer.xsl"/>


  <xsl:output method="xml" indent="yes"
    doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
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
         <script src="js/cdashBuildGroup.js" type="text/javascript" charset="utf-8"></script>
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


<br/>
<xsl:call-template name="summarizeMissing" />
<br/>
<xsl:call-template name="summarizeUpdateErrors" />
<br/>
<xsl:call-template name="summarizeConfigureErrors" />
<br/>
<xsl:call-template name="summarizeBuildErrors" />
<br/>
<xsl:call-template name="summarizeTestsFailed" />
<br/>
<xsl:call-template name="summarizeTestsNotRun" />
<br/>
<!--
<xsl:call-template name="summarizeUpdateWarnings" />
<br/>
-->
<xsl:call-template name="summarizeConfigureWarnings" />
<br/>
<xsl:call-template name="summarizeBuildWarnings" />
<br/>
<xsl:call-template name="statistics" />
<br/>
<!--
<xsl:call-template name="summarizeThing">
  <xsl:with-param name="heading" select="'BuildsWithUpdateErrors'"/>
  <xsl:with-param name="thingTest" select="update/errors != 0"/>
</xsl:call-template>
<br/>
<xsl:call-template name="repeatthis" />
<br/>
-->


<!-- footer  -->

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

      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
