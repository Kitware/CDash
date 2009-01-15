<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    <xsl:include href="headerback.xsl"/> 
   
    <xsl:output method="html" encoding="iso-8859-1"/>
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
<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
