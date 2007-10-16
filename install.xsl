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
<font color="#ffffff"><h2>CDash - Installation</h2>
<h3>Welcome to CDash!</h3></font>
</td></tr><tr><td></td><td>
<div id="navigator">
</div>
</td>
</tr>
</table>

<br/>
Please follow the installation step to make sure your system meets the requirements.<br/><br/>
<xsl:if test="cdash/connectiondb=0">
Cannot connect to mysql on <b><xsl:value-of select="cdash/connectiondb_host"/></b> using login <b><xsl:value-of select="cdash/connectiondb_login"/></b>.<br/>
Make sure you have modified the settings in the <b>config.php</b> file.
</xsl:if>

<xsl:if test="cdash/database=1">
The database already exists. Quitting installation script.<br/>
Click here to access the <a href="index.php">main CDash page</a>
</xsl:if>

<xsl:if test="cdash/xslt=0">
Your PHP installation doesn't support XSLT please install the PHP_XSLT package.
</xsl:if>

<xsl:choose>
<xsl:when test="cdash/db_created=1">
<b>The CDash database has been sucessfully created!</b><br/>
Click here to  <a href="createProject.php">create a new project.</a>
</xsl:when>
<xsl:otherwise>
<xsl:if test="cdash/database=0 and cdash/xslt=1 and cdash/connectiondb=1">
Please review the settings of your config.php file below and click install to install the SQL tables.<br/><br/>

Database Hostname: <b><xsl:value-of select="cdash/connectiondb_host"/></b><br/>
Database Login: <b><xsl:value-of select="cdash/connectiondb_login"/></b><br/>
Database Name: <b><xsl:value-of select="cdash/connectiondb_name"/></b><br/>
Dashboard timeframe: <b><xsl:value-of select="cdash/dashboard_timeframe"/></b><br/>
<br/>
<form name="form1" method="post" action="">
<input type="submit" name="Submit" value="Install"/>
</form>
</xsl:if>
</xsl:otherwise>
</xsl:choose>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
					   </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
