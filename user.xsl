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
<font color="#ffffff"><h2>CDash - My Profile</h2>
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


<!-- Main -->
<xsl:if test="cdash/user_admin=1">
<table>
  <tr><td width="95"><div align="right"></div></td><td bgcolor="#DDDDDD"><a href="createProject.php">[Create project]</a></td></tr>
		<tr><td width="95"><div align="right"></div></td><td bgcolor="#EEEEEE"><a href="manageBuildGroup.php">[Manage build groups]</a></td></tr>
		<tr><td width="95"><div align="right"></div></td><td bgcolor="#DDDDDD"><a href="backwardCompatibilityTools.php">[Backward compatibility tools]</a></td></tr>
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
