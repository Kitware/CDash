<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
   <xsl:template match="/">


<!-- Main -->
<h3>SubProject Dependencies</h3>
<table xmlns:lxslt="http://xml.apache.org/xslt" cellspacing="0" border="0" width="100%">
   <tr align="center" bgcolor="#CCCCCC">
     <td></td>
     <xsl:for-each select="cdash/subproject">
     <td><a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname"/>&amp;subproject=<xsl:value-of select="name"/>&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:attribute>
     <xsl:value-of select="name"/>
    </a></td>
     </xsl:for-each>
   </tr>
   <xsl:for-each select="cdash/subproject">
   <tr align="center">
   <xsl:attribute name="bgcolor"><xsl:value-of select="bgcolor"/></xsl:attribute>
   <td>
   <a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname"/>&amp;subproject=<xsl:value-of select="name_encoded"/>&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:attribute>
     <xsl:value-of select="name"/>
    </a>
   </td>
   <xsl:for-each select="dependency">
     <td>
     <xsl:choose>
     <xsl:when test="string-length(id)>0">X</xsl:when>
     <xsl:otherwise></xsl:otherwise>
     </xsl:choose>
     </td>

    </xsl:for-each>
   </tr>
   </xsl:for-each>
</table>

    </xsl:template>
</xsl:stylesheet>
