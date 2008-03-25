<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    <xsl:include href="headerback.xsl"/> 
   
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
       
   <xsl:call-template name="headerback"/>

<xsl:if test="string-length(cdash/alert)>0">
<b><xsl:value-of select="cdash/alert"/></b>
</xsl:if>
<br/><br/>

<form name="form1" enctype="multipart/form-data" method="post" action="">
<table border="0">
 <xsl:for-each select="cdash/log">
  <tr>
    <td><div align="right"><b>CDash log</b></div></td>
    <td><div align="left"><a>
  <xsl:attribute name="href">
  <xsl:value-of select="fullpath"/>
  </xsl:attribute>
  <xsl:value-of select="name"/></a></div></td>
  </tr> 
 </xsl:for-each> 
</table>
<table border="0">
 <xsl:for-each select="cdash/file">
  <tr>
    <td><div align="right"><b>Unparsed File:</b></div></td>
    <td><div align="left"><a>
  <xsl:attribute name="href">
  <xsl:value-of select="fullpath"/>
  </xsl:attribute>
  <xsl:value-of select="name"/></a></div></td>
  </tr> 
 </xsl:for-each> 
</table>
</form>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
