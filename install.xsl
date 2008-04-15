<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
      
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>

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
      <span class="inn1">CDash</span><br />
      <span class="inn2">Installation</span>
      </div>
    </td>
    <td height="121" class="insd2"><xsl:text>&#160;</xsl:text></td>
   </tr>
  </table>
  </td>
    </tr>
 
</table></td>
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
Click here to access the <a href="index.php">main CDash page</a><br/><br/>
</xsl:if>

<xsl:if test="cdash/xslt=0">
<font color="#FF0000">Your PHP installation doesn't support XSLT please install the PHP_XSLT package.</font><br/>
</xsl:if>
<xsl:if test="cdash/phpcurl=0">
<font color="#FF0000">Your PHP installation doesn't support Curl please install the PHP_CURL package if you want to support geolocation.</font><br/>
</xsl:if>
<xsl:if test="cdash/backupwritable=0">
<font color="#FF0000">Your backup directory is not writable, make sure that the web process can write into the directory.</font><br/>
</xsl:if>
<xsl:if test="cdash/rsswritable=0">
<font color="#FF0000">Your rss directory is not writable, make sure that the web process can write into the directory.</font><br/>
</xsl:if>
<br/>
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
Admin username: <b>admin@cdash.org</b><br/>
Admin password: <b>administrator</b><br/>
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
