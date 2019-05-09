<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:include href="headscripts.xsl"/>
<!-- Group footer -->
<xsl:template name="groupfooter">
</xsl:template>

<!-- Main Header -->
<xsl:template name="header">

<div id="header">
 <div id="headertop">
  <div id="topmenu">
    <a><xsl:attribute name="href">user.php</xsl:attribute>
        <xsl:choose>
          <xsl:when test="cdash/user/id>0">My CDash</xsl:when>
          <xsl:otherwise>Login</xsl:otherwise>
        </xsl:choose></a><a href="viewProjects.php">All Dashboards</a>
     <xsl:if test="cdash/user/id>0">
       <a href="user.php?logout=1">Log Out</a>
     </xsl:if>
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
        <xsl:value-of select="cdash/dashboard/projectname"/> <xsl:value-of select="cdash/subprojectname"/>
      </span>
    </div>
    <div id="headermenu">
        <ul id="navigation">
        <xsl:choose>
        <xsl:when test="string-length(cdash/menu/back)>0">
        <li>
        <a>
        <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>
        Dashboard</a>
        </li><li id="Back">
        <a>
        <xsl:attribute name="href"><xsl:value-of select="cdash/menu/back"/></xsl:attribute>
        Back</a>
        </li>
        </xsl:when>
        <xsl:otherwise>
        <li>
        <!-- Back to the main page if not a subpackage otherwise goes back to the list of subprojects -->

        <a>
        <xsl:attribute name="href">
        index.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/>
        </xsl:attribute>
        Dashboard</a>
        <ul>
        <li><a>
        <xsl:attribute name="href">overview.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>Overview</a></li>
        <li><a>
        <xsl:attribute name="href">buildOverview.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>
        Builds</a></li>
        <li><a>
        <xsl:attribute name="href">testOverview.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>
        Tests</a></li>
        <li><a>
        <xsl:attribute name="href">queryTests.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/>&#38;limit=200<xsl:value-of select="cdash/extraurl"/></xsl:attribute>
        Tests Query</a></li>
        <li><a>
        <xsl:attribute name="href">userStatistics.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>
        Statistics</a></li>
        <li class="endsubmenu"><a>
        <xsl:attribute name="href">viewMap.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>
        Sites</a></li>
        </ul>
        </li>
        </xsl:otherwise>
        </xsl:choose>
        <li><a id="cal" href="#">Calendar</a>
        <span id="date_now" style="display:none;"><xsl:value-of select="cdash/dashboard/date"/></span>
        </li>

        <xsl:if test="string-length(cdash/menu/noprevious)=0">
        <li>
        <a>
        <xsl:attribute name="href">
        <xsl:choose>
          <xsl:when test="string-length(cdash/menu/previous)>0">
            <xsl:value-of select="cdash/menu/previous"/>
          </xsl:when>
          <xsl:otherwise>
          index.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#x26;date=<xsl:value-of select="cdash/dashboard/previousdate"/><xsl:value-of select="cdash/extraurl"/>
          </xsl:otherwise>
        </xsl:choose>
        </xsl:attribute>
          Previous
          </a></li>
        </xsl:if>

        <li><a><xsl:attribute name="href">
        <xsl:choose>
          <xsl:when test="string-length(cdash/menu/current)>0">
            <xsl:value-of select="cdash/menu/current"/>
          </xsl:when>
          <xsl:otherwise>
          index.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/><xsl:value-of select="cdash/extraurl"/>
          </xsl:otherwise>
        </xsl:choose>
        </xsl:attribute>
            Current
            </a></li>

        <xsl:if test="string-length(cdash/menu/nonext)=0">
        <li><a>
        <xsl:attribute name="href">
        <xsl:choose>
          <xsl:when test="string-length(cdash/menu/next)>0">
            <xsl:value-of select="cdash/menu/next"/>
          </xsl:when>
          <xsl:otherwise>
          index.php?project=<xsl:value-of select="cdash/dashboard/projectname_encoded"/>&#x26;date=<xsl:value-of select="cdash/dashboard/nextdate"/><xsl:value-of select="cdash/extraurl"/>
          </xsl:otherwise>
        </xsl:choose>
        </xsl:attribute>
              Next
              </a></li>
        </xsl:if>

        <li>
        <a href="#">Project</a><ul>
        <li><a><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/home"/> </xsl:attribute>Home</a></li>
        <li><a><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/documentation"/> </xsl:attribute>Documentation</a></li>
        <li><a><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/svn"/> </xsl:attribute>Repository</a></li>

        <li>
          <xsl:if test="string-length(cdash/user/projectrole)>0">
            <xsl:attribute name="class">endsubmenu</xsl:attribute>
          </xsl:if>
          <a><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/bugtracker"/> </xsl:attribute>Bug Tracker</a>
        </li>

       <xsl:if test="string-length(cdash/user/projectrole)=0">
          <li class="endsubmenu"><a><xsl:attribute name="href">subscribeProject.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/> </xsl:attribute>Subscribe</a></li>
        </xsl:if>

        </ul>
        </li>
        <xsl:if test="cdash/user/admin=1">
        <li id="admin">
        <a href="#">Settings</a><ul>
        <li><a><xsl:attribute name="href">createProject.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>Project</a></li>
        <li><a><xsl:attribute name="href">manageProjectRoles.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>Users</a></li>
        <li><a><xsl:attribute name="href">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>Groups</a></li>
        <li><a><xsl:attribute name="href">manageCoverage.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>Coverage</a></li>
        <li><a><xsl:attribute name="href">manageBanner.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>Banner</a></li>
        <li><a><xsl:attribute name="href">manageMeasurements.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>Measurements</a></li>
        <li><a><xsl:attribute name="href">manageSubProject.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>SubProjects</a></li>
        <li class="endsubmenu"><a><xsl:attribute name="href">manageOverview.php?projectid=<xsl:value-of select="cdash/dashboard/projectid"/></xsl:attribute>Overview</a></li>
        </ul>
        </li>
        </xsl:if>

       </ul>
    </div>
 </div>

</div>


<input type="hidden" id="projectname">
 <xsl:attribute name="value"><xsl:value-of select="cdash/dashboard/projectname_encoded"/>
 </xsl:attribute>
 </input>

 <input type="hidden" id="projectid">
 <xsl:attribute name="value"><xsl:value-of select="cdash/dashboard/projectid"/>
 </xsl:attribute>
 </input>

<div id="calendar" class="ui-datepicker-calendar" ></div>


</xsl:template>

</xsl:stylesheet>
