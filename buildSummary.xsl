<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
    
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
									
									<!-- Include JavaScript -->
         <script src="javascript/cdashBuildGraph.js" type="text/javascript" charset="utf-8"></script> 
       </head>
       <body bgcolor="#ffffff">
   
       <xsl:call-template name="header"/>
<br/>


    <!-- Build log for a single submission -->
    <br/><b>Site Name: </b><xsl:value-of select="cdash/build/site"/>
    <br/><b>Build Name: </b><xsl:value-of select="cdash/build/name"/>
    <br/><b>Time: </b><xsl:value-of select="cdash/build/time"/>
    <br/><b>Type: </b><xsl:value-of select="cdash/build/type"/>

    <xsl:if test="cdash/build/lastsubmitbuild>0">
    <p/><b>Last submission: </b><a>
				 <xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="cdash/build/lastsubmitbuild"/></xsl:attribute><xsl:value-of select="cdash/build/lastsubmitdate"/></a>  
				 </xsl:if>	
						<br/><br/>
      <table class="dart">
      <tr class="table-heading">
        <th>Stage</th><th>Errors</th><th>Warnings</th>
      </tr>
						 <tr class="tr-odd">
        <td><a href="#Stage0"><b>Update</b></a></td>
        <td align="right"><b><xsl:value-of select="cdash/update/nerrors"/></b></td>
        <td align="right"><b><xsl:value-of select="cdash/update/nwarnings"/></b></td>
								</tr>
        <tr class="tr-even">
        <td><a href="#Stage1"><b>Configure</b></a></td>

        <td align="right"><b><xsl:value-of select="cdash/configure/nerrors"/></b></td>
        <td align="right"><b><xsl:value-of select="cdash/configure/nwarnings"/></b></td>
       </tr>
        <tr class="tr-odd">
        <td><a href="#Stage2"><b>Build</b></a></td>
        <td align="right"><b><xsl:value-of select="cdash/build/nerrors"/></b></td>
        <td align="right"><b><xsl:value-of select="cdash/build/nwarnings"/></b></td>
       </tr>
							<tr class="tr-even">
        <td><a href="#Stage3"><b>Test</b></a></td>
        <td align="right"><b><xsl:value-of select="cdash/test/nerrors"/></b></td>
        <td align="right"><b><xsl:value-of select="cdash/test/nwarnings"/></b></td>
       </tr>
      </table>
      <br/>
<!-- Graph -->

<a>
<xsl:attribute name="href">javascript:showgraph_click(<xsl:value-of select="cdash/build/id"/>)</xsl:attribute>
[Show Build Time Graph]
</a>
<div name="graph" id="graph"></div>
<center>
<div id="grapholder"></div>
</center>
<br/>
<!-- Update -->
<div class="title-divider" id="Stage0">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div>
Stage: Update (<xsl:value-of select="cdash/update/nerrors"/> errors, <xsl:value-of select="cdash/update/nwarnings"/> warnings)
</div>
<br/><b>Start Time: </b><xsl:value-of select="cdash/update/starttime"/> 
<br/><b>End Time: </b><xsl:value-of select="cdash/update/endtime"/>
<br/><b>Update Command: </b> <xsl:value-of select="cdash/update/command"/>    
<br/><b>Update Type: </b> <xsl:value-of select="cdash/update/type"/>   
<br/><b>Number of Updates: </b>
<a><xsl:attribute name="href">viewUpdate.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>
<xsl:value-of select="cdash/update/nupdates"/></a>
<br/><br/>


<!-- Configure -->
<div class="title-divider" id="Stage1">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div>
Stage: Configure (<xsl:value-of select="cdash/configure/nerrors"/> errors, <xsl:value-of select="cdash/configure/nwarnings"/> warnings)
</div>

<br/><b>Start Time: </b><xsl:value-of select="cdash/configure/starttime"/> 
<br/><b>End Time: </b><xsl:value-of select="cdash/configure/endtime"/>
<br/><b>Configure Command: </b> <xsl:value-of select="cdash/configure/command"/>    
<br/><b>Configure Return Value: </b> <xsl:value-of select="cdash/configure/status"/> 
<br/><b>Configure Output: </b>
<br/><pre><xsl:value-of select="cdash/configure/output"/></pre>      

<br/><br/>

<!-- Build -->
<div class="title-divider" id="Stage2">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div>Stage: Build (<xsl:value-of select="cdash/build/nerrors"/> errors, <xsl:value-of select="cdash/build/nwarnings"/> warnings)</div>
        <br/><b>Build command: </b><tt><xsl:value-of select="cdash/build/command"/></tt>
        <br/><b>Start Time: </b><xsl:value-of select="cdash/build/starttime"/>
        <br/><b>End Time: </b><xsl:value-of select="cdash/build/endtime"/>
        <br/>
        <br/>

<!-- Show the errors -->
<xsl:for-each select="cdash/build/error">
<xsl:if test="sourceline>0">
<hr/>
<h3><A Name="650">Build Log line <xsl:value-of select="logline"/></A></h3>
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
  <a href="">CVS</a>
</xsl:if>
<pre><xsl:value-of select="precontext"/></pre>
<pre><xsl:value-of select="text"/></pre>
<pre><xsl:value-of select="postcontext"/></pre>
</xsl:for-each>


        <div class="title-divider" id="Stage2Warnings"><div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]</div>
Build Warnings (<xsl:value-of select="cdash/build/nwarnings"/>)</div>
           
<xsl:for-each select="cdash/build/warning">
<xsl:if test="sourceline>0">
<hr/>
<h3><A Name="650">Build Log line <xsl:value-of select="logline"/></A></h3>
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
  <a href="">CVS</a>
</xsl:if>
<pre><xsl:value-of select="precontext"/></pre>
<pre><xsl:value-of select="text"/></pre>
<pre><xsl:value-of select="postcontext"/></pre>
</xsl:for-each>						
																			
<br/>


<!-- Test -->
<div class="title-divider" id="Stage3">
<div class="tracknav">
[<a href="#top">Top</a>]
[<a href="#Stage0">Update</a>]
[<a href="#Stage1">Configure</a>]
[<a href="#Stage2">Build</a>|<a href="#Stage2Warnings">W</a>]
[<a href="#Stage3">Test</a>]
</div>
        Stage: Test (<xsl:value-of select="cdash/test/npassed"/>  passed, <xsl:value-of select="cdash/test/nfailed"/> failed, <xsl:value-of select="cdash/test/nnotrun"/> not run)
        </div>
<a><xsl:attribute name="href">viewTest.php?buildid=<xsl:value-of select="cdash/build/id"/></xsl:attribute>[View Tests Summary]</a>

<br/>
<br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
