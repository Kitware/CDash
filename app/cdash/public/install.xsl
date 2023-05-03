<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">

<font color="red"><xsl:value-of select="cdash/alert"/></font><br/>

<br/>
Please follow the installation step to make sure your system meets the requirements.<br/><br/>
<xsl:if test="cdash/connectiondb=0">
Cannot connect to <b><xsl:value-of select="cdash/connectiondb_name"/></b> on <b><xsl:value-of select="cdash/connectiondb_host"/></b> using login <b><xsl:value-of select="cdash/connectiondb_login"/></b>.<br/>
Make sure you have modified the settings in the <b>config.php</b> file.
<xsl:if test="cdash/connectiondb_type != 'mysql'">
<br/>With databases other than mysql, make sure that the database has been created manually before running this installation.<br/>
</xsl:if>
</xsl:if>


<xsl:if test="cdash/database=1">
The database already exists. Quitting installation script.<br/>
Click here to access the <a href="index.php">main CDash page</a><br/><br/>
</xsl:if>

<xsl:if test="cdash/extcurl=0">
<font color="#FF0000">Your PHP installation does not support cURL. Please install the cURL extension.</font><br/>
</xsl:if>
<xsl:if test="cdash/extjson=0">
<font color="#FF0000">Your PHP installation does not support JSON. Please install the JSON extension.</font><br/>
</xsl:if>
<xsl:if test="cdash/extmbstring=0">
<font color="#FF0000">Your PHP installation does not support multibyte strings. Please install the multibyte string extension.</font><br/>
</xsl:if>
<xsl:if test="cdash/extpdo=0">
<font color="#FF0000">Your PHP installation does not support PDO. Please install the PDO extension.</font><br/>
</xsl:if>

<xsl:if test="cdash/backupwritable=0">
<font color="#FF0000">Your backup directory is not writable, make sure that the web process can write into the directory.</font><br/>
</xsl:if>
<xsl:if test="cdash/logwritable=0">
<font color="#FF0000">Your log directory is not writable, make sure that the web process can write into the directory.</font><br/>
</xsl:if>
<xsl:if test="cdash/uploadwritable=0">
<font color="#FF0000">Your upload directory is not writable, make sure that the web process can write into the directory.</font><br/>
</xsl:if>
<br/>
<xsl:choose>
<xsl:when test="cdash/db_created=1">
<b>The CDash database has been successfully created!</b><br/>
Click here to  <a href="project/new">create a new project.</a>
</xsl:when>
<xsl:otherwise>
<xsl:if test="cdash/database=0 and cdash/connectiondb=1">
Please review the settings of your config.php file below and click install to install the SQL tables.<br/><br/>

<xsl:if test="cdash/connectiondb=1">
<form name="form1" method="post" action="">
<table>
<tr><td>Database Type:</td><td><b><xsl:value-of select="cdash/connectiondb_type"/></b></td></tr>
<tr><td>Database Hostname:</td><td><b><xsl:value-of select="cdash/connectiondb_host"/></b></td></tr>
<tr><td>Database Login:</td><td><b><xsl:value-of select="cdash/connectiondb_login"/></b></td></tr>
<tr><td>Database Name:</td><td><b><xsl:value-of select="cdash/connectiondb_name"/></b></td></tr>
<tr><td>Admin Email:</td><td><input name="admin_email" type="text"/></td></tr>
<tr><td>Admin Password:</td><td><input name="admin_password" type="password"/></td></tr>
</table>
<br/>
<input type="submit" name="Submit" value="Install"/>
</form>
</xsl:if>
</xsl:if>
</xsl:otherwise>
</xsl:choose>

    </xsl:template>
</xsl:stylesheet>
