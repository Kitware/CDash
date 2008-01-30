<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  
  <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
		<xsl:output method="html"/>
  <xsl:template match="/">
      <html>
						<head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>
      </head>

 <body>
	
   	<table width="100%" class="toptable" cellpadding="1" cellspacing="0">
  <tr>
    <td>
		<table width="100%" align="center" cellpadding="0" cellspacing="0" >
  <tr>
    <td height="22" class="topline"><xsl:text>&#160;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">
	
		  <table width="100%" height="121" border="0" cellpadding="0" cellspacing="0" >
	   <tr>
		  <td width="195" height="121" class="topbgleft">
				<xsl:text>&#160;</xsl:text> <img  border="0" alt="" src="images/cdash.gif"/>
				</td>
				<td width="425" valign="top" class="insd">
				<div class="insdd">
						<span class="inn1">My CDash</span><br />
						<span class="inn2"><xsl:value-of select="cdash/user_name"/></span>
						</div>
				</td>
				<td height="121" class="insd2"><xsl:text>&#160;</xsl:text></td>
			</tr>
		</table>
		</td>
				</tr>
  <tr>
    <td align="left" class="topbg2"><table width="100%" height="28" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td width="631" align="left" class="bgtm"><ul id="Nav" class="nav">
<li id="Dartboard">
<a href="index.php">HOME</a>
</li>
<li><a href="user.php?logout=1">LOGOUT</a></li>
</ul>
</td>
		<td height="28" class="insd3"><xsl:text>&#160;</xsl:text></td>
	</tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>

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
      <td colspan="5" id="nob"><h3>My Projects</h3></td>
    </tr>

    <xsl:for-each select="cdash/project">
      <tr class="table-heading">
        <td align="center" ><xsl:value-of select="name"/> </td>
        <td bgcolor="#DDDDDD" id="nob"><a>
        <xsl:attribute name="href">subscribeProject.php?projectid=<xsl:value-of select="id"/>&amp;edit=1</xsl:attribute>[Edit subscription]</a>
        <xsl:if test="role>0">
          <a><xsl:attribute name="href">editSite.php?projectid=<xsl:value-of select="id"/></xsl:attribute>[Claim sites]</a>
        </xsl:if>
        <xsl:if test="role>1">
          <a><xsl:attribute name="href">createProject.php?edit=1&amp;projectid=<xsl:value-of select="id"/></xsl:attribute>[Edit project]</a>
          <a><xsl:attribute name="href">manageBuildGroup.php?projectid=<xsl:value-of select="id"/></xsl:attribute>[Manage project groups]</a>
          <xsl:if test="role>2">
            <a><xsl:attribute name="href">manageProjectRoles.php?projectid=<xsl:value-of select="id"/></xsl:attribute>[Manage project roles]</a>
          </xsl:if>
        </xsl:if>
      </td>
    </tr>
  </xsl:for-each>
		</tbody>
</table>
<br/>
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
      <td align="center"><b><i>Site\Project</i></b></td>
      
      <xsl:for-each select="cdash/claimedsiteproject">
        <td align="center" id="nob"><a><xsl:attribute name="href">index.php?project=<xsl:value-of select="name"/></xsl:attribute><xsl:value-of select="name"/></a></td>
      </xsl:for-each>
    </tr>
    
    <!-- Fill in the information -->
    <xsl:for-each select="cdash/claimedsite">
      <td  bgcolor="#EEEEEE" align="center" >
        <a><xsl:attribute name="href">editSite.php?siteid=<xsl:value-of select="id"/></xsl:attribute><xsl:value-of select="name"/></a>
      </td>
      
      <xsl:for-each select="project">
        <td bgcolor="#EEEEEE" align="center" id="nob"> 
          <table width="100%">
            <xsl:if test="nightly/NA=0">
              <tr class="table-heading">
                <td><b>N</b></td>
                <td id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/updateclass"/></xsl:attribute><xsl:value-of select="nightly/update"/></td>
                <td id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/configureclass"/></xsl:attribute><xsl:value-of select="nightly/configure"/></td>
                <td id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/errorclass"/></xsl:attribute><xsl:value-of select="nightly/error"/></td>
                <td id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/testfailclass"/></xsl:attribute><xsl:value-of select="nightly/testfail"/></td>
                <td id="nob"><xsl:attribute name="class"><xsl:value-of select="nightly/dateclass"/></xsl:attribute>
                <a><xsl:attribute name="href"><xsl:value-of select="nightly/datelink"/></xsl:attribute><xsl:value-of select="nightly/date"/></a>		
              </td>
            </tr>
          </xsl:if>
          <xsl:if test="continuous/NA=0">
            <tr class="table-heading">
              <td><b>C</b></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/updateclass"/></xsl:attribute><xsl:value-of select="continuous/update"/></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/configureclass"/></xsl:attribute><xsl:value-of select="continuous/configure"/></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/errorclass"/></xsl:attribute><xsl:value-of select="continuous/error"/></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/testfailclass"/></xsl:attribute><xsl:value-of select="continuous/testfail"/></td>	
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="continuous/dateclass"/></xsl:attribute>
              <a><xsl:attribute name="href"><xsl:value-of select="continuous/datelink"/></xsl:attribute><xsl:value-of select="continuous/date"/></a>		
            </td></tr>
          </xsl:if>
          <xsl:if test="experimental/NA=0">
            <tr class="table-heading">
              <td><b>E</b></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/updateclass"/></xsl:attribute><xsl:value-of select="experimental/update"/></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/configureclass"/></xsl:attribute><xsl:value-of select="experimental/configure"/></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/errorclass"/></xsl:attribute><xsl:value-of select="experimental/error"/></td>
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/testfailclass"/></xsl:attribute><xsl:value-of select="experimental/testfail"/></td>		
              <td id="nob"><xsl:attribute name="class"><xsl:value-of select="experimental/dateclass"/></xsl:attribute>
              <a><xsl:attribute name="href"><xsl:value-of select="experimental/datelink"/></xsl:attribute><xsl:value-of select="experimental/date"/></a>		
            </td></tr>
          </xsl:if>	
        </table>
      </td>
    </xsl:for-each>
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
        <xsl:attribute name="href">subscribeProject.php?projectid=<xsl:value-of select="id"/></xsl:attribute>[Subscribe to this project]</a></td>
      </tr>
    </xsl:for-each>
				</tbody>
  </table>
  <br/>
</xsl:if>

<!-- Global Administration -->
<xsl:if test="cdash/user_is_admin=1">
 <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1"><td id="nob"><h3>Administration</h3></td></tr>
    <tr class="trodd"><td id="nob"><a href="createProject.php">[Create new project]</a></td></tr>
    <tr class="treven"><td id="nob"><a href="createProject.php?edit=1">[Edit project]</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="manageProjectRoles.php">[Manage project roles]</a></td></tr>	
    <tr class="treven"><td id="nob"><a href="manageBuildGroup.php">[Manage project groups]</a></td></tr>	
    <tr class="trodd"><td id="nob"><a href="backwardCompatibilityTools.php">[Backward compatibility tools]</a></td></tr>
    <tr class="treven"><td id="nob"><a href="import.php">[Import Dart1 Files]</a></td></tr>
  </tbody>
		</table>
</xsl:if>
<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
