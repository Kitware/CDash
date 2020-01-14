<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
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

<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="header_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="header"/>
</xsl:otherwise>
</xsl:choose>

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

<!-- FOOTER -->
<br/>
<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="footer_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="footer"/>
</xsl:otherwise>
</xsl:choose>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
