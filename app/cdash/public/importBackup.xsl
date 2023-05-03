<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
     doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">

<xsl:if test="string-length(cdash/alert)>0">
<b><xsl:value-of select="cdash/alert"/></b>
</xsl:if>
<br/>
<br/>

<form name="form1" method="post" action="">
This page allows you to import xml files in the backup directory for this installation of CDash.<br/>
<br/>
<p>
  <input type="submit" name="Submit" value="Import Backups"/>
  matching
  <input type="text" name="filemask" size="100" value="*.xml"/>
</p>
</form>
    </xsl:template>
</xsl:stylesheet>
