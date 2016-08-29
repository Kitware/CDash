<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>

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
         <xsl:call-template name="headscripts"/>

         <!-- Include JavaScript -->
         <script src="js/cdashBuildGraph.js" type="text/javascript" charset="utf-8"></script>
         <script src="js/cdashAddNote.js" type="text/javascript" charset="utf-8"></script>
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
    <!-- Build log for a single submission -->
    <br/><b>Site Name: </b><a>
<xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="cdash/build/siteid"/></xsl:attribute>
<xsl:value-of select="cdash/build/site"/></a>
    <br/><b>Build Name: </b><xsl:value-of select="cdash/build/name"/>

    <xsl:if test="string-length(cdash/build/note)>0">
      (<a><xsl:attribute name="href">viewNotes.php?buildid=<xsl:value-of select="cdash/build/id"/> </xsl:attribute>view notes</a>)
    </xsl:if>


    <br/><b>Stamp: </b><xsl:value-of select="cdash/build/stamp"/>
    (<a><xsl:attribute name="href"><xsl:value-of select="cdash/build/relatedBuildsLink"/></xsl:attribute>related builds</a>)
    <br/><b>Time: </b><xsl:value-of select="cdash/build/time"/>
    <br/><b>Type: </b><xsl:value-of select="cdash/build/type"/>
    <br/>
    <!-- Display Operating System information  -->
    <xsl:if test="cdash/build/osname">
      <br/><b>OS Name: </b><xsl:value-of select="cdash/build/osname"/>
    </xsl:if>
    <xsl:if test="cdash/build/osplatform">
      <br/><b>OS Platform: </b><xsl:value-of select="cdash/build/osplatform"/>
    </xsl:if>
    <xsl:if test="cdash/build/osrelease">
      <br/><b>OS Release: </b><xsl:value-of select="cdash/build/osrelease"/>
    </xsl:if>
    <xsl:if test="cdash/build/osversion">
      <br/><b>OS Version: </b><xsl:value-of select="cdash/build/osversion"/>
    </xsl:if>

    <!-- Display Compiler information  -->
    <xsl:if test="cdash/build/compilername">
      <br/><b>Compiler Name: </b><xsl:value-of select="cdash/build/compilername"/>
    </xsl:if>
    <xsl:if test="cdash/build/compilerversion">
      <br/><b>Compiler Version: </b><xsl:value-of select="cdash/build/compilerversion"/>
    </xsl:if>

    <xsl:if test="cdash/build/generator">
      <br/><b>CTest version: </b><xsl:value-of select="cdash/build/generator"/>
    </xsl:if>

    <xsl:if test="cdash/build/lastsubmitbuild>0">
    <p/><b>Last submission: </b><a>
     <xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="cdash/build/lastsubmitbuild"/></xsl:attribute><xsl:value-of select="cdash/build/lastsubmitdate"/></a>
     </xsl:if>
      <br/><br/>
      <table>
      <tr><td>
      <table class="dart">
      <tr class="table-heading">
        <th colspan="3">Current Build</th>
      </tr>
      <tr class="table-heading">
        <th>Stage</th><th>Errors</th><th>Warnings</th>
      </tr>
       <tr class="tr-odd">
        <td>
        <xsl:choose>
          <xsl:when test="cdash/update">
            <a href="#Stage0"><b>Update</b></a>
          </xsl:when>
          <xsl:otherwise><b>Update</b></xsl:otherwise>
        </xsl:choose>
       </td>
        <td align="right">
        <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/update/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  <xsl:choose>
                  <xsl:when test="cdash/update">normal</xsl:when>
                  <xsl:otherwise>na</xsl:otherwise>
                   </xsl:choose>
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>

        <b><a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
        <xsl:value-of select="cdash/update/nerrors"/></a></b></td>
        <td align="right">
              <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/update/nwarnings > 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  <xsl:choose>
                  <xsl:when test="cdash/update">normal</xsl:when>
                  <xsl:otherwise>na</xsl:otherwise>
                   </xsl:choose>
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>

        <b><a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
        <xsl:value-of select="cdash/update/nwarnings"/></a></b></td>
        </tr>
        <tr class="tr-even">
        <td><a href="#Stage1"><b>Configure</b></a></td>

        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/configure/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
       <xsl:value-of select="cdash/configure/nerrors"/></a></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/configure/nwarnings > 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
       <xsl:value-of select="cdash/configure/nwarnings"/></a></b></td>
       </tr>
        <tr class="tr-odd">
        <td><a href="#Stage2"><b>Build</b></a></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/build/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="cdash/build/id"/>
       </xsl:attribute><xsl:value-of select="cdash/build/nerrors"/></a></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/build/nwarnings > 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
       <xsl:value-of select="cdash/build/nwarnings"/></a></b></td>
       </tr>
       <tr class="tr-even">
        <td><a href="#Stage3"><b>Test</b></a></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/test/nfailed > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewTest.php?onlyfailed&#38;buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
       <xsl:value-of select="cdash/test/nfailed"/></a></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/test/nnotrun> 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewTest.php?onlynotrun&#38;buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
       <xsl:value-of select="cdash/test/nnotrun"/></a></b></td>
       </tr>
      </table>
      </td>
      <td>

      <!-- Previous build -->
      <xsl:if test="cdash/previousbuild">
      <table class="dart">
      <tr class="table-heading">
        <th colspan="3"><a>
        <xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
        Previous Build
        </a>
        </th>
      </tr>
      <tr class="table-heading">
        <th>Stage</th><th>Errors</th><th>Warnings</th>
      </tr>
       <tr class="tr-odd">
        <td><b>Update</b></td>
        <td align="right">
        <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nupdateerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                 <xsl:choose>
                  <xsl:when test="cdash/update">normal</xsl:when>
                  <xsl:otherwise>na</xsl:otherwise>
                   </xsl:choose>
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>

        <b><a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
       <xsl:value-of select="cdash/previousbuild/nupdateerrors"/></a></b></td>
        <td align="right">
              <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nupdatewarnings > 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  <xsl:choose>
                  <xsl:when test="cdash/update">normal</xsl:when>
                  <xsl:otherwise>na</xsl:otherwise>
                   </xsl:choose>
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>

        <b><a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
        <xsl:value-of select="cdash/previousbuild/nupdatewarnings"/></a></b></td>
        </tr>
        <tr class="tr-even">
        <td><b>Configure</b></td>

        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nconfigurenerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
       <xsl:value-of select="cdash/previousbuild/nconfigureerrors"/></a></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nconfigurewarnings > 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
       <xsl:value-of select="cdash/previousbuild/nconfigurewarnings"/></a></b></td>
       </tr>
        <tr class="tr-odd">
        <td><b>Build</b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nerrors > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
       <xsl:value-of select="cdash/previousbuild/nerrors"/></a></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/nwarnings > 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
       <xsl:value-of select="cdash/previousbuild/nwarnings"/></a></b></td>
       </tr>
       <tr class="tr-even">
        <td><b>Test</b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/ntestfailed > 0">error
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewTest.php?onlyfailed&#38;buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
       <xsl:value-of select="cdash/previousbuild/ntestfailed"/></a></b></td>
        <td align="right">  <xsl:attribute name="class">
          <xsl:choose>
          <xsl:when test="cdash/previousbuild/ntestnotrun> 0">warning
               </xsl:when>
                 <xsl:otherwise>
                  normal
                  </xsl:otherwise>
               </xsl:choose>
       </xsl:attribute>
       <b><a><xsl:attribute name="href">viewTest.php?onlynotrun&#38;buildid=<xsl:value-of select="cdash/previousbuild/buildid"/></xsl:attribute>
       <xsl:value-of select="cdash/previousbuild/ntestnotrun"/></a></b></td>
       </tr>
      </table>
      </xsl:if>
      </td>
      </tr>
      </table>
      <br/>

<!-- Display the history table -->
<div class="title-divider">History</div>
<a>
<xsl:attribute name="href">javascript:showbuildhistory_click(<xsl:value-of select="cdash/build/id"/>)</xsl:attribute>
Show Build History
</a><br/>
<a><xsl:attribute name="href">
index.php?project=<xsl:value-of select="/cdash/dashboard/projectname_encoded"/>&#38;filtercount=3&#38;showfilters=1&#38;filtercombine=and&#38;field1=site/string&#38;compare1=63&#38;value1=<xsl:value-of select="/cdash/build/sitename_encoded"/>&#38;field2=buildstarttime/date&#38;compare2=83&#38;value2=<xsl:value-of select="/cdash/build/filterstarttime"/>&#38;field3=buildstarttime/date&#38;compare3=84&#38;value3=<xsl:value-of select="/cdash/build/filterendtime"/>
</xsl:attribute>
Build History Filter
</a>
<div id="buildhistory"></div>
<br/>

<!-- Display notes for that build -->
<div class="title-divider">Notes</div>

<xsl:if test="count(cdash/note)>0">
<div class="title-divider">Users notes (<xsl:value-of select="count(cdash/note)"/>)</div>
  <xsl:for-each select="cdash/note">
    <b><xsl:value-of select="status"/></b> by <b><xsl:value-of select="user"/></b> at <xsl:value-of select="date"/>
    <pre><xsl:value-of select="text"/></pre>
    <hr/>
  </xsl:for-each>
</xsl:if>


<!-- Add Notes -->
<a>
<xsl:attribute name="href">javascript:addnote_click(<xsl:value-of select="cdash/build/id"/>,'<xsl:value-of select="cdash/user/id"/>')</xsl:attribute>
Add a Note to this Build</a>
<div id="addnote"></div>
<br/>

<!-- Graphs -->
<div class="title-divider">Graphs</div>
<img src="img/graph.png" title="graph"/><a><xsl:attribute name="href">javascript:showbuildgraph_click(<xsl:value-of select="cdash/build/id"/>, "time")</xsl:attribute>
Show Build Time Graph</a>
<div id="buildtimegraphoptions"></div>
<div id="buildtimegraph"></div>
<center>
<div id="buildtimegrapholder"></div>
</center>
<img src="img/graph.png" title="graph"/><a><xsl:attribute name="href">javascript:showbuildgraph_click(<xsl:value-of select="cdash/build/id"/>, "errors")</xsl:attribute>
Show Build Errors Graph</a>
<div id="builderrorsgraphoptions"></div>
<div id="builderrorsgraph"></div>
<center>
<div id="builderrorsgrapholder"></div>
</center>
<img src="img/graph.png" title="graph"/><a><xsl:attribute name="href">javascript:showbuildgraph_click(<xsl:value-of select="cdash/build/id"/>, "warnings")</xsl:attribute>
Show Build Warnings Graph</a>
<div id="buildwarningsgraphoptions"></div>
<div id="buildwarningsgraph"></div>
<center>
<div id="buildwarningsgrapholder"></div>
</center>
<img src="img/graph.png" title="graph"/><a><xsl:attribute name="href">javascript:showbuildgraph_click(<xsl:value-of select="cdash/build/id"/>, "testsfailed")</xsl:attribute>
Show Build Tests Failed Graph</a>
<div id="buildtestsfailedgraphoptions"></div>
<div id="buildtestsfailedgraph"></div>
<center>
<div id="buildtestsfailedgrapholder"></div>
</center>

<br/>

<!-- Update -->
<xsl:if test="cdash/update">
<div class="title-divider" id="Stage0">
Stage: Update (<xsl:value-of select="cdash/update/nerrors"/> errors, <xsl:value-of select="cdash/update/nwarnings"/> warnings)
</div>
<br/><b>Start Time: </b><xsl:value-of select="cdash/update/starttime"/>
<br/><b>End Time: </b><xsl:value-of select="cdash/update/endtime"/>
<br/><b>Update Command: </b> <xsl:value-of select="cdash/update/command"/>
<br/><b>Update Type: </b> <xsl:value-of select="cdash/update/type"/>
<br/><b>Number of Updates: </b>
<a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
<xsl:value-of select="cdash/update/nupdates"/></a>
<xsl:if test="cdash/update/status">
  <br/><b>Update Status: </b><xsl:value-of select="cdash/update/status"/>
</xsl:if>
<br/><br/>
</xsl:if>

<!-- Configure -->
<div class="title-divider" id="Stage1">
Configure (<xsl:value-of select="cdash/configure/nerrors"/> errors, <xsl:value-of select="cdash/configure/nwarnings"/> warnings)
</div>

<br/><b>Start Time: </b><xsl:value-of select="cdash/configure/starttime"/>
<br/><b>End Time: </b><xsl:value-of select="cdash/configure/endtime"/>
<br/><b>Configure Command: </b> <xsl:value-of select="cdash/configure/command"/>
<br/><b>Configure Return Value: </b> <xsl:value-of select="cdash/configure/status"/>
<br/><b>Configure Output: </b>
<br/><pre><xsl:value-of select="cdash/configure/output"/></pre>
<br/>
<a><xsl:attribute name="href">viewConfigure.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>View Configure Summary</a>
<br/><br/>
<!-- Build -->
<div class="title-divider" id="Stage2">
Build (<xsl:value-of select="cdash/build/nerrors"/> errors, <xsl:value-of select="cdash/build/nwarnings"/> warnings)</div>
        <br/><b>Build command: </b><tt><xsl:value-of select="cdash/build/command"/></tt>
        <br/><b>Start Time: </b><xsl:value-of select="cdash/build/starttime"/>
        <br/><b>End Time: </b><xsl:value-of select="cdash/build/endtime"/>
        <br/>
<br/>
<!-- Show the errors -->
<xsl:for-each select="cdash/build/error">
<xsl:if test="sourceline>0">
<hr/>
<h3><a>Build Log line <xsl:value-of select="logline"/></a></h3>
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
</xsl:if>
<pre><xsl:value-of select="precontext"/></pre>
<pre><xsl:value-of select="text"/></pre>
<pre><xsl:value-of select="postcontext"/></pre>

<xsl:if test="string-length(stdoutput)>0 or string-length(stderror)>0">
  <br/>
  <b><xsl:value-of select="sourcefile"/></b>
  <xsl:if test="string-length(stdoutput)>0">
    <pre><xsl:value-of select="stdoutput"/></pre>
  </xsl:if>
  <xsl:if test="string-length(stderror)>0">
    <pre><xsl:value-of select="stderror"/></pre>
  </xsl:if>
</xsl:if>

</xsl:for-each>
<a><xsl:attribute name="href">viewBuildError.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>View Errors Summary</a>
<br/>
<br/>
<!--  Warnings -->
<div class="title-divider" id="Stage2Warnings">
Build Warnings (<xsl:value-of select="cdash/build/nwarnings"/>)</div>

<xsl:for-each select="cdash/build/warning">
<xsl:if test="sourceline>0">
<hr/>
<h3><a>Build Log line <xsl:value-of select="logline"/></a></h3>
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
</xsl:if>
<pre><xsl:value-of select="precontext"/></pre>
<pre><xsl:value-of select="text"/></pre>
<pre><xsl:value-of select="postcontext"/></pre>

<xsl:if test="string-length(stdoutput)>0 or string-length(stderror)>0">
  <br/>
  <b><xsl:value-of select="sourcefile"/></b>
  <xsl:if test="string-length(stdoutput)>0">
    <pre><xsl:value-of select="stdoutput"/></pre>
  </xsl:if>
  <xsl:if test="string-length(stderror)>0">
    <pre><xsl:value-of select="stderror"/></pre>
  </xsl:if>
</xsl:if>

</xsl:for-each>
<br/>
<a><xsl:attribute name="href">viewBuildError.php?type=1&#38;buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>View Warnings Summary</a>
<br/>
<br/>
<!-- Test -->
<div class="title-divider" id="Stage3">
Test (<xsl:value-of select="cdash/test/npassed"/>  passed, <xsl:value-of select="cdash/test/nfailed"/> failed, <xsl:value-of select="cdash/test/nnotrun"/> not run)
 </div>
<a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>View Tests Summary</a>

<br/>
<br/>

<xsl:if test="cdash/coverage">
<!-- Coverage -->
<div class="title-divider" id="Stage4">
  Coverage (<xsl:value-of select="cdash/coverage"/>%)
</div>
<a><xsl:attribute name="href">viewCoverage.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>View Coverage Summary</a>
<br/>
<br/>
</xsl:if>

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
