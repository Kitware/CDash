<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="local/footer.xsl"/>
  <xsl:include href="headscripts.xsl"/>
  <xsl:include href="local/headscripts.xsl"/>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>
  <xsl:template match="/">
      <html>
      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
        <link rel="shortcut icon" href="favicon.ico"/>
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>
        <xsl:call-template name="headscripts"/>
        <script src="js/cdashUser.js" type="text/javascript" charset="utf-8"></script>
      </head>
 <body>


 <div id="header">
 <div id="headertop">
  <div id="topmenu">
    <a href="index.php">All Dashboards</a>
    <a href="editUser.php">My Profile</a>
    <a href="user.php?logout=1">Log Out</a>
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
    <div id="headername2">
      CDash
      <span id="subheadername">
        <xsl:value-of select="cdash/user_name"/>
      </span>
    </div>
 </div>
</div>

<br/>
<!-- Message -->
<table>
  <tr>
    <td width="95"><div align="right"></div></td>
    <td><div style="color: green;"><xsl:value-of select="cdash/message" /></div></td>
  </tr>
</table>

<!-- My Projects -->
<xsl:if test="count(cdash/project)>0">
 <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1">
      <td colspan="7" id="nob"><h3>My Projects</h3></td>
    </tr>

   <tr class="table-heading">
      <td align="center" width="100px" class="botl">Project Name</td>
      <td align="center" width="240px" class="botl">Actions</td>
      <td align="center" width="130px" class="botl">Builds</td>
      <td align="center" width="130px" class="botl">Builds per day</td>
      <td align="center" width="130px" class="botl">Success Last 24h</td>
      <td align="center" width="130px" class="botl">Errors Last 24h</td>
      <td align="center" width="130px" class="botl">Warnings Last 24h</td>

   </tr>
    <xsl:for-each select="cdash/project">
      <tr class="table-heading">
        <td align="center" >
        <a>
        <xsl:attribute name="href">
        index.php?project=<xsl:value-of select="name_encoded"/>
        </xsl:attribute>
        <xsl:value-of select="name"/></a> </td>
       <td align="center"  bgcolor="#DDDDDD" ><a class="tooltip" title="Edit subscription" >
        <xsl:attribute name="href">subscribeProject.php?projectid=<xsl:value-of select="id"/>&amp;edit=1</xsl:attribute>
        <img src="img/edit.png" border="0" alt="subscribe" />
        </a>
        <xsl:if test="role>0">
          <a class="tooltip" title="Claim sites" >
          <xsl:attribute name="href">editSite.php?projectid=<xsl:value-of select="id"/></xsl:attribute>
          <img src="img/systemtray.png" border="0" alt="claimsite" /></a>
        </xsl:if>
        <xsl:if test="role>1">
          <xsl:if test="/cdash/manageclient=1">
          <a class="tooltip" title="Schedule Build" >
            <xsl:attribute name="href">manageClient.php?projectid=<xsl:value-of select="id"/></xsl:attribute>
            <img src="img/manageclient.png" border="0" alt="manageclient" /></a>
          </xsl:if>
          <a class="tooltip" title="Edit project" >
          <xsl:attribute name="href">createProject.php?edit=1&amp;projectid=<xsl:value-of select="id"/></xsl:attribute>
          <img  src="img/edit2.png" border="0" alt="editproject" /></a>
          <a class="tooltip" title="Manage subprojects" >
          <xsl:attribute name="href">manageSubProject.php?projectid=<xsl:value-of select="id"/></xsl:attribute>
          <img  src="img/subproject.png" border="0" alt="subproject" /></a>
          <a class="tooltip" title="Manage project groups" >
          <xsl:attribute name="href">manageBuildGroup.php?projectid=<xsl:value-of select="id"/></xsl:attribute>
            <img src="img/edit_group.png" border="0" alt="managegroups" /></a>
          <a class="tooltip" title="Manage project users" >
          <xsl:attribute name="href">manageProjectRoles.php?projectid=<xsl:value-of select="id"/></xsl:attribute>
           <img src="img/users.png" border="0" alt="manageusers" /></a>
          <a class="tooltip" title="Manage project coverage" >
          <xsl:attribute name="href">manageCoverage.php?projectid=<xsl:value-of select="id"/></xsl:attribute>
           <img src="img/filecoverage.png" border="0" alt="managecoverage" /></a>

        </xsl:if>
      </td>
      <td align="center"  bgcolor="#DDDDDD">
        <xsl:value-of select="nbuilds"/>
      </td>
      <td align="center"  bgcolor="#DDDDDD">
        <xsl:value-of select="average_builds"/>
      </td>
      <td align="center"  bgcolor="#DDDDDD">
        <xsl:if test="success>0">
           <xsl:attribute name="class">normal</xsl:attribute>
        </xsl:if>
        <xsl:value-of select="success"/>
      </td>
      <td align="center"  bgcolor="#DDDDDD">
        <xsl:if test="error>0">
           <xsl:attribute name="class">error</xsl:attribute>
        </xsl:if>
        <xsl:value-of select="error"/>
      </td>
      <td align="center"  bgcolor="#DDDDDD">
        <xsl:if test="warning>0">
           <xsl:attribute name="class">warning</xsl:attribute>
        </xsl:if>
        <xsl:value-of select="warning"/>
      </td>

    </tr>
  </xsl:for-each>
  </tbody>
</table>
<br/>
</xsl:if>

<!-- Job Submission -->
<xsl:if test="/cdash/manageclient=1">
<xsl:if test="count(cdash/jobschedule)>0">
 <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1">
      <td colspan="7" id="nob"><h3>My Build Schedules</h3></td>
    </tr>

   <tr class="table-heading">
      <td align="center" class="botl">Project</td>
      <td align="center" class="botl">Status</td>
      <td align="center" class="botl">Last run</td>
      <td align="center" class="botl">Description</td>
      <td align="center" class="botl">Actions</td>

   </tr>
    <xsl:for-each select="cdash/jobschedule">
      <tr class="table-heading">
        <td align="center" >
        <a>
        <xsl:attribute name="href">
        index.php?project=<xsl:value-of select="projectname"/>
        </xsl:attribute>
        <xsl:value-of select="projectname"/></a>
        </td>
        <td align="center" ><xsl:value-of select="status"/></td>
        <td align="center" ><xsl:value-of select="lastrun"/></td>
        <td align="center" >
           <xsl:if test="string-length(description)=0">
            NA
           </xsl:if>
        <xsl:value-of select="description"/></td>

       <td align="center" >
        <a><xsl:attribute name="href">manageClient.php?scheduleid=<xsl:value-of select="id"/>
        </xsl:attribute><img src="img/advanced.png" border="0" alt="edit schedule" /></a>
        <a onclick="return VerifyDeleteSchedule()"><xsl:attribute name="href">manageClient.php?removeschedule=<xsl:value-of select="id"/>
        </xsl:attribute><img src="img/delete.png" border="0" alt="remove schedule" /></a>
        </td>
    </tr>
  </xsl:for-each>
  </tbody>
</table>
<br/>
</xsl:if>
</xsl:if>

<!-- My Sites -->
<xsl:if test="count(cdash/claimedsite)>0">
 <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1">
      <td colspan="10" id="nob"><h3>My Sites</h3></td>
    </tr>

    <!-- header of the matrix -->
    <tr class="table-heading">
      <td align="center"><b>Site</b></td>

      <xsl:for-each select="cdash/claimedsiteproject">
        <td align="center" id="nob"><a><xsl:attribute name="href">index.php?project=<xsl:value-of select="name_encoded"/></xsl:attribute><xsl:value-of select="name"/></a>
        </td>
      </xsl:for-each>
    </tr>

    <!-- Fill in the information -->
    <xsl:for-each select="cdash/claimedsite">
  <tr class="treven">
      <td align="center" >
        <a><xsl:attribute name="href">editSite.php?siteid=<xsl:value-of select="id"/></xsl:attribute><xsl:value-of select="name"/></a>
         <xsl:if test="outoforder=1">
           <img border="0" src="img/flag.png" title="flag"></img>
        </xsl:if>
      </td>

      <xsl:for-each select="project">
        <td align="center" id="nob">
     <table width="100%" border="0">
            <xsl:if test="nightly/NA=0">
              <tr class="table-heading">
                <td align="center"><b>N</b></td>
                <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/updateclass"/></xsl:attribute><xsl:value-of select="nightly/update"/></td>
                <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/configureclass"/></xsl:attribute><xsl:value-of select="nightly/configure"/></td>
                <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/errorclass"/></xsl:attribute><xsl:value-of select="nightly/error"/></td>
                <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/testfailclass"/></xsl:attribute><xsl:value-of select="nightly/testfail"/></td>
                <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/dateclass"/></xsl:attribute>
                <a><xsl:attribute name="href"><xsl:value-of select="nightly/datelink"/></xsl:attribute><xsl:value-of select="nightly/date"/></a>
              </td>
            </tr>
          </xsl:if>
          <xsl:if test="continuous/NA=0">
            <tr class="table-heading">
              <td align="center"><b>C</b></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/updateclass"/></xsl:attribute><xsl:value-of select="continuous/update"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/configureclass"/></xsl:attribute><xsl:value-of select="continuous/configure"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/errorclass"/></xsl:attribute><xsl:value-of select="continuous/error"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/testfailclass"/></xsl:attribute><xsl:value-of select="continuous/testfail"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/dateclass"/></xsl:attribute>
              <a><xsl:attribute name="href"><xsl:value-of select="continuous/datelink"/></xsl:attribute><xsl:value-of select="continuous/date"/></a>
            </td></tr>
          </xsl:if>
          <xsl:if test="experimental/NA=0">
            <tr class="table-heading">
              <td align="center"><b>E</b></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/updateclass"/></xsl:attribute><xsl:value-of select="experimental/update"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/configureclass"/></xsl:attribute><xsl:value-of select="experimental/configure"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/errorclass"/></xsl:attribute><xsl:value-of select="experimental/error"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/testfailclass"/></xsl:attribute><xsl:value-of select="experimental/testfail"/></td>
              <td align="center" id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/dateclass"/></xsl:attribute>
              <a><xsl:attribute name="href"><xsl:value-of select="experimental/datelink"/></xsl:attribute><xsl:value-of select="experimental/date"/></a>
            </td></tr>
          </xsl:if>
        </table>
      </td>
    </xsl:for-each>
  </tr>
  </xsl:for-each>
  </tbody>
</table>
<br/>
</xsl:if>

<!-- Public Project -->
<xsl:if test="count(cdash/publicproject)>0">
 <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1">
      <td colspan="3" id="nob"><h3>Public projects</h3></td>
    </tr>

    <xsl:for-each select="cdash/publicproject">
      <tr class="table-heading">
        <td align="center"  id="nob">
         <xsl:attribute name="class"><xsl:value-of select="trparity"/></xsl:attribute>
        <b><xsl:value-of select="name"/></b></td>
        <td  id="nob">
        <xsl:attribute name="class"><xsl:value-of select="trparity"/></xsl:attribute>
        <a>
        <xsl:attribute name="href">subscribeProject.php?projectid=<xsl:value-of select="id"/></xsl:attribute>Subscribe to this project</a></td>
      </tr>
    </xsl:for-each>
    </tbody>
  </table>
  <br/>
</xsl:if>

<!-- If we allow user to create new projects -->
<xsl:if test="cdash/user_can_create_projects=1 and cdash/user_is_admin=0">
 <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
  <tbody>
    <tr class="table-heading1"><td id="nob"><h3>Administration</h3></td></tr>
    <tr class="trodd"><td id="nob"><a href="createProject.php">Start a new project</a></td></tr>
  </tbody>
  </table>
<br/>
</xsl:if>

<!-- Global Administration -->
<xsl:if test="cdash/user_is_admin=1">
 <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1"><td id="nob"><h3>Administration</h3></td></tr>
    <tr class="trodd"><td id="nob"><a href="createProject.php">Create new project</a></td></tr>
    <tr class="treven"><td id="nob"><a href="createProject.php?edit=1">Edit project</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="manageProjectRoles.php">Manage project roles</a></td></tr>
    <tr class="treven"><td id="nob"><a href="manageSubProject.php">Manage subproject</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="manageBuildGroup.php">Manage project groups</a></td></tr>
    <tr class="treven"><td id="nob"><a href="manageCoverage.php">Manage project coverage</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="manageBanner.php">Manage banner message</a></td></tr>
    <tr class="treven"><td id="nob"><a href="manageUsers.php">Manage users</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="upgrade.php">Maintenance</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="monitor.php">Monitor / Processing Statistics</a></td></tr>
    <tr class="treven"><td id="nob"><a href="siteStatistics.php">Site Statistics</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="userStatistics.php">User Statistics</a></td></tr>
    <tr class="treven"><td id="nob"><a href="manageBackup.php">Manage Backup</a></td></tr>
  </tbody>
  </table>
</xsl:if>
<br/>
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
