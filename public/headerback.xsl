<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>
    <xsl:include href="headscripts.xsl"/>
    <xsl:include href="local/headscripts.xsl"/>

    <xsl:template name="headerback">

<div id="header">
 <div id="headertop">
  <div id="topmenu">
      <a href="index.php">All Dashboards</a>
     <xsl:if test="cdash/user/id>0">
       <a href="user.php?logout=1">Log Out</a>
     </xsl:if>
    <a><xsl:attribute name="href">user.php</xsl:attribute>
        <xsl:choose>
          <xsl:when test="cdash/user/id>0">My CDash</xsl:when>
          <xsl:otherwise></xsl:otherwise>
        </xsl:choose></a>
  </div>

  <div id="datetime">
   <xsl:value-of select="cdash/dashboard/datetime"/>
  </div>
 <div id="feedicon" alt="RSS Feed" title="RSS Feed">
   <xsl:if test="cdash/dashboard/projectpublic=1">
      <a>
      <xsl:attribute name="href">rss/SubmissionRSS<xsl:value-of select="cdash/dashboard/projectname"/>.xml</xsl:attribute><img src="img/feed-icon16x16.png" alt="RSS" width="14" height="14" border="0" />
      </a>
   </xsl:if>
   </div>
 </div>

 <div id="headerbottom">
    <div id="headerlogo">
      <a>
        <xsl:attribute name="href">
        <xsl:value-of select="cdash/dashboard/home"/></xsl:attribute>
        <img id="projectlogo" border="0" height="50px">
        <xsl:attribute name="alt"></xsl:attribute>
        <xsl:choose>
        <xsl:when test="cdash/dashboard/logoid>0">
          <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
         </xsl:when>
        <xsl:otherwise>
         <xsl:attribute name="src">img/cdash.png</xsl:attribute>
        </xsl:otherwise>
        </xsl:choose>
        </img>
      </a>
    </div>
    <div id="headername">
      <xsl:value-of select="/cdash/menutitle"/>
      <span id="subheadername">
        <xsl:value-of select="/cdash/menusubtitle"/>
      </span>
    </div>
    <div id="headermenu">
        <ul id="navigation">
        <li id="Back">
        <a>
        <xsl:attribute name="href"><xsl:value-of select="/cdash/backurl"/></xsl:attribute>
        Back</a>
        </li>
       </ul>
    </div>
 </div>

</div>


    </xsl:template>
</xsl:stylesheet>
