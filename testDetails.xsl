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
    <xsl:attribute name="href">
      <xsl:value-of select="cdash/cssfile"/>
    </xsl:attribute>
  </link>

  <xsl:call-template name="headscripts"/>
  <!-- Include JavaScript -->
  <script src="javascript/cdashTestGraph.js" type="text/javascript" charset="utf-8"></script>

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

<div id="executiontime">
<img src="images/clock.png" alt="Execution Time" title="Execution Time" />
<span class="builddateelapsed">
<xsl:attribute name="alt">
  Mean time:<xsl:value-of select="cdash/test/timemean"/>s
  <xsl:text disable-output-escaping="yes">&lt;br&gt;</xsl:text>
  STD time:<xsl:value-of select="cdash/test/timestd"/>s
</xsl:attribute>
<xsl:value-of select="cdash/test/time"/>s</span>
</div>

<!--
  <tr>
      <th class="measurement">Completion Status</th>
      <td>
        <xsl:value-of select="cdash/test/details"/>
      </td>
   </tr>
-->

<br/>
<b>Test: </b>
<a>
        <xsl:attribute name="href">
   <xsl:value-of select="cdash/test/summaryLink"/>
        </xsl:attribute>
 <xsl:value-of select="cdash/test/test"/>
      </a>
      <font>
        <xsl:attribute name="color">
          <xsl:value-of select="cdash/test/statusColor"/>
        </xsl:attribute>
        (<xsl:value-of select="cdash/test/status"/>)
      </font>
<br/>
<b>Build: </b>
<a>
<xsl:attribute name="href">
    buildSummary.php?buildid=<xsl:value-of select="cdash/test/buildid"/>
  </xsl:attribute>
  <xsl:value-of select="cdash/test/build"/></a>

  (<a>
  <xsl:attribute name="href">
    viewSite.php?siteid=<xsl:value-of select="cdash/test/siteid"/>
  </xsl:attribute>
  <xsl:value-of select="cdash/test/site"/></a>)
on <xsl:value-of select="cdash/test/buildstarttime"/>
<br/>
<xsl:if test="string-length(cdash/test/update/revision)>0">
<b>Repository revision: </b><a><xsl:attribute name="href"><xsl:value-of select="cdash/test/update/revisionurl"/></xsl:attribute>
  <xsl:value-of select="cdash/test/update/revision"/>
  </a>
<br/>
</xsl:if>

<xsl:if test="cdash/project/showtesttime=1">
<br/>
  <b>Test Timing: </b><font>
        <xsl:attribute name="color">
         <xsl:value-of select="cdash/test/timeStatusColor"/>
        </xsl:attribute><xsl:value-of select="cdash/test/timestatus"/>
      </font>
</xsl:if>
<br/>
<!-- Display the measurements -->
<table>
<xsl:for-each select="cdash/test/images/image">
  <tr>
    <th class="measurement"><xsl:value-of select="role"/></th>
    <td>
      <img>
 <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="imgid"/>
 </xsl:attribute>
      </img>
    </td>
  </tr>
</xsl:for-each>
  <xsl:for-each select="/cdash/test/measurements/measurement">
     <tr>
      <th class="measurement"><xsl:value-of select="name"/></th>
      <td>
         <xsl:if test="type='numeric/double'">
             <a><xsl:attribute name="href">javascript:shownamedmeasurementgraph_click(<xsl:value-of select="/cdash/test/buildid"/>,<xsl:value-of select="/cdash/test/id"/>,"<xsl:value-of select="name" />");</xsl:attribute>[Graph]</a> </xsl:if>
        <xsl:value-of select="value" disable-output-escaping="yes"/>
      </td>
   </tr>
   </xsl:for-each>
</table>
<br/>

<!-- Named Measurement Graphs -->
<xsl:for-each select="/cdash/test/measurements/measurement">
  <xsl:if test="type='numeric/double'">
  <div>
    <xsl:attribute name="id"><xsl:value-of select="name" />graphoptions</xsl:attribute>
  </div>
  <div>
    <xsl:attribute name="id"><xsl:value-of select="name" />graph</xsl:attribute>
  </div>
  <center>
    <div>
      <xsl:attribute name="id"><xsl:value-of select="name" />grapholder</xsl:attribute>
    </div>
  </center>
  </xsl:if>
</xsl:for-each>

<!-- Show command line -->
<img src="images/console.png"/>
<a id="commandlinelink" href="javascript:showcommandline_click()">Show Command Line</a>
<div id="commandline" style="display:none">
  <xsl:value-of select="cdash/test/command"/>
</div>
<br/>
<!-- Timing Graph -->
<img src="images/graph.png"/>
<a>
<xsl:attribute name="href">javascript:showtesttimegraph_click(<xsl:value-of select="/cdash/test/buildid"/>,<xsl:value-of select="/cdash/test/id"/>)</xsl:attribute>Show Test Time Graph
</a>
<div id="timegraphoptions"></div>
<div id="timegraph"></div>
<center>
<div id="timegrapholder"></div>
</center>
<!-- Pass/Fail Graph -->
<img src="images/graph.png"/>
<a>
<xsl:attribute name="href">javascript:showtestpassinggraph_click(<xsl:value-of select="/cdash/test/buildid"/>,<xsl:value-of select="/cdash/test/id"/>)</xsl:attribute>Show Failing/Passing Graph
</a>
<div id="passinggraphoptions"></div>
<div id="passinggraph"></div>
<center>
<div id="passinggrapholder"></div>
</center>
<br/>
<b>Test output</b>
<pre>
  <xsl:value-of select="cdash/test/output"/>
</pre>

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
