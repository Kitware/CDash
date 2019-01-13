<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>
    <xsl:template name="headeradminproject">

<div id="header">
 <div id="headertop">
  <div id="topmenu">
    <a><xsl:attribute name="href"><xsl:value-of select="/cdash/backurl"/></xsl:attribute>My CDash</a>
    <a href="index.php">All Dashboards</a>
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
      <span id="subheadername">
        <xsl:value-of select="/cdash/menutitle"/> <xsl:value-of select="/cdash/menusubtitle"/>
      </span>
    </div>
    <div id="headermenu">
        <ul id="navigation">
        <li id="admin">
        <a href="#">Settings</a><ul>
        <li><a><xsl:attribute name="href">createProject.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>Project</a></li>
        <li><a><xsl:attribute name="href">manageProjectRoles.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>Users</a></li>
        <li><a><xsl:attribute name="href">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>Groups</a></li>
        <li><a><xsl:attribute name="href">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>Coverage</a></li>
        <li><a><xsl:attribute name="href">manageBanner.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>Banner</a></li>
        <li><a><xsl:attribute name="href">manageMeasurements.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>Measurements</a></li>
        <li><a><xsl:attribute name="href">manageSubProject.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>SubProjects</a></li>
        <li class="endsubmenu"><a><xsl:attribute name="href">manageOverview.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>Overview</a></li>
        </ul>
        </li>
         <li id="Dashboard">
            <a><xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/project/name_encoded"/></xsl:attribute>Dashboard</a>
         </li>
       </ul>
    </div>
 </div>

</div>


    </xsl:template>
</xsl:stylesheet>
