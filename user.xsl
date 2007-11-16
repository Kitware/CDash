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
       </head>
       <body bgcolor="#ffffff">
   
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td bgcolor="#6699cc" valign="top" width="100%">
<font color="#ffffff"><h2>CDash - My Profile</h2>
<h3>Welcome <xsl:value-of select="cdash/user_name"/></h3></font>
</td></tr><tr><td></td><td>
<div id="navigator">
<table border="0" cellpadding="0" cellspacing="0">
<tr>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">index.php</xsl:attribute>Home</a>
</p>
</td>

<td align="center" width="5">
<p></p>
</td>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">login.php?logout=1</xsl:attribute>Logout</a>
</p>
</td>

</tr>
</table>
</div>
</td>
</tr>
</table>
 
<br/>

<!-- Main -->
<xsl:if test="cdash/user_admin=1">
  <a href="createProject.php">[Create project]</a>
</xsl:if>
<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
