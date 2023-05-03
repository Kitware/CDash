<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">

<!-- Main -->
<table border="0">
<tr><td align="left"><b>Site: </b><xsl:value-of select="cdash/build/site"/></td></tr>
<tr><td align="left"><b>Build Name: </b><xsl:value-of select="cdash/build/buildname"/></td></tr>
<tr><td align="left"><b>Coverage File: </b><xsl:value-of select="cdash/coverage/fullpath"/></td></tr>
</table>
<hr/>

<pre><xsl:value-of select="cdash/coverage/file" disable-output-escaping="yes"/></pre>

    </xsl:template>
</xsl:stylesheet>
