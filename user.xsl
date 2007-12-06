<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

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
         
              <!-- Include CDash Menu Stylesheet -->    
    <link rel="stylesheet" href="javascript/cdashmenu.css" type="text/css" media="screen" charset="utf-8" />
    
    <!-- Include the rounding css -->
    <script src="javascript/rounded.js"></script>
    
       </head>
       <body bgcolor="#ffffff">
   
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td valign="bottom" width="100%">
<div style="margin: 0pt auto; background-color: #6699cc;"  class="rounded">    
<font color="#ffffff"><h2>My CDash</h2>
<h3>Welcome <xsl:value-of select="cdash/user_name"/></h3></font><br/>
</div>
</td>
</tr>
<tr><td></td><td>

<!-- Menu -->
<ul id="Nav" class="nav">
  <li>
     <a href="index.php">Home</a>
   </li>
   <li>
     <a href="user.php?logout=1">Logout</a> 
  </li>
</ul>
</td>
</tr>
</table>
 
<br/>

<script type="text/javascript">
  Rounded('rounded', 15, 15,0,0);
</script>

<!-- Message -->
<table>
 <tr>
		  <td width="95"><div align="right"></div></td>
		  <td><div style="color: green;"><xsl:value-of select="cdash/message" /></div></td>
		</tr>
</table>


<!-- My Projects -->
<xsl:if test="count(cdash/project)>0">
<table>
 <tr>
		  <td><div align="right"></div></td>
		  <td bgcolor="#CCCCCC" colspan="5"><b>My Projects</b></td>
		</tr>

<xsl:for-each select="cdash/project">
  <tr>
		  <td width="95"><div align="right"></div></td>
		  <td bgcolor="#DDDDDD" align="right"><xsl:value-of select="name"/> </td>
    <td><div align="left"></div></td><td bgcolor="#DDDDDD"><a>
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
</table>
<br/>
</xsl:if>

<!-- My Sites -->
<xsl:if test="count(cdash/claimedsite)>0">
<table>
 <tr>
		  <td><div align="right"></div></td>
		  <td bgcolor="#CCCCCC" colspan="10"><b>My Sites</b></td>
		</tr>

<!-- header of the matrix -->
<tr>
		  <td width="95"><div align="right"></div></td>
		  <td bgcolor="#DDDDDD" align="right"><b><i>Site\Project</i></b></td>
				
				<xsl:for-each select="cdash/claimedsiteproject">
    <td bgcolor="#DDDDDD" align="center"><a><xsl:attribute name="href">index.php?project=<xsl:value-of select="name"/></xsl:attribute><xsl:value-of select="name"/></a></td>
   </xsl:for-each>
</tr>
		
<!-- Fill in the information -->
<xsl:for-each select="cdash/claimedsite">
  <td width="95"><div align="right"></div></td>
		  <td bgcolor="#EEEEEE" align="middle">
				<a><xsl:attribute name="href">editSite.php?siteid=<xsl:value-of select="id"/></xsl:attribute><xsl:value-of select="name"/></a>
				</td>
				
				<xsl:for-each select="project">
    <td bgcolor="#EEEEEE" align="middle">
				<table>
				<xsl:if test="nightly/NA=0">
				<tr bgcolor="#FFFFFF">
				<td><b>N</b></td>
				<td><xsl:attribute name="class"><xsl:value-of select="nightly/updateclass"/></xsl:attribute><xsl:value-of select="nightly/update"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="nightly/configureclass"/></xsl:attribute><xsl:value-of select="nightly/configure"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="nightly/errorclass"/></xsl:attribute><xsl:value-of select="nightly/error"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="nightly/testfailclass"/></xsl:attribute><xsl:value-of select="nightly/testfail"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="nightly/dateclass"/></xsl:attribute>
				<a><xsl:attribute name="href"><xsl:value-of select="nightly/datelink"/></xsl:attribute><xsl:value-of select="nightly/date"/></a>		
				</td>
				</tr>
				</xsl:if>
				<xsl:if test="continuous/NA=0">
				<tr bgcolor="#FFFFFF">
				<td><b>C</b></td>
				<td><xsl:attribute name="class"><xsl:value-of select="continuous/updateclass"/></xsl:attribute><xsl:value-of select="continuous/update"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="continuous/configureclass"/></xsl:attribute><xsl:value-of select="continuous/configure"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="continuous/errorclass"/></xsl:attribute><xsl:value-of select="continuous/error"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="continuous/testfailclass"/></xsl:attribute><xsl:value-of select="continuous/testfail"/></td>	
				<td><xsl:attribute name="class"><xsl:value-of select="continuous/dateclass"/></xsl:attribute>
				<a><xsl:attribute name="href"><xsl:value-of select="continuous/datelink"/></xsl:attribute><xsl:value-of select="continuous/date"/></a>		
				</td></tr>
				</xsl:if>
				<xsl:if test="experimental/NA=0">
				<tr bgcolor="#FFFFFF">
				<td><b>E</b></td>
				<td><xsl:attribute name="class"><xsl:value-of select="experimental/updateclass"/></xsl:attribute><xsl:value-of select="experimental/update"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="experimental/configureclass"/></xsl:attribute><xsl:value-of select="experimental/configure"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="experimental/errorclass"/></xsl:attribute><xsl:value-of select="experimental/error"/></td>
				<td><xsl:attribute name="class"><xsl:value-of select="experimental/testfailclass"/></xsl:attribute><xsl:value-of select="experimental/testfail"/></td>		
				<td><xsl:attribute name="class"><xsl:value-of select="experimental/dateclass"/></xsl:attribute>
				<a><xsl:attribute name="href"><xsl:value-of select="experimental/datelink"/></xsl:attribute><xsl:value-of select="experimental/date"/></a>		
				</td></tr>
				</xsl:if>	
				</table>
				</td>
   </xsl:for-each>
</xsl:for-each>
</table>
<br/>
</xsl:if>

<!-- Public Project -->
<xsl:if test="count(cdash/publicproject)>0">
<table>
 <tr>
		  <td><div align="right"></div></td>
		  <td bgcolor="#CCCCCC" colspan="3"><b>Public projects</b></td>
		</tr>

<xsl:for-each select="cdash/publicproject">
  <tr>
		  <td width="95"><div align="right"></div></td>
		  <td bgcolor="#DDDDDD" align="right"><xsl:value-of select="name"/> </td>
    <td><div align="left"></div></td><td bgcolor="#DDDDDD"><a>
				<xsl:attribute name="href">subscribeProject.php?projectid=<xsl:value-of select="id"/></xsl:attribute>[Subscribe to this project]</a></td>
		</tr>
</xsl:for-each>
</table>
<br/>
</xsl:if>

<!-- Global Administration -->
<xsl:if test="cdash/user_is_admin=1">
<table>
  <tr><td width="95"><div align="right"></div></td><td bgcolor="#CCCCCC"><b>Administration</b></td></tr>
		<tr><td width="95"><div align="right"></div></td><td bgcolor="#EEEEEE"><a href="createProject.php">[Create new project]</a></td></tr>
		<tr><td width="95"><div align="right"></div></td><td bgcolor="#EEEEEE"><a href="createProject.php?edit=1">[Edit project]</a></td></tr>
		<tr><td width="95"><div align="right"></div></td><td bgcolor="#EEEEEE"><a href="manageProjectRoles.php">[Manage project roles]</a></td></tr>	
	 <tr><td width="95"><div align="right"></div></td><td bgcolor="#EEEEEE"><a href="manageBuildGroup.php">[Manage project groups]</a></td></tr>	
  <tr><td width="95"><div align="right"></div></td><td bgcolor="#EEEEEE"><a href="backwardCompatibilityTools.php">[Backward compatibility tools]</a></td></tr>
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
