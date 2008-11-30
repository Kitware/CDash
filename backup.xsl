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

Export the database from the date range into the 'database' directory in your current backup directory.
<br/><br/>

<form name="form1" enctype="multipart/form-data" method="post" action="">
From:
<input>
<xsl:attribute name="name">monthFrom</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/monthFrom"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">dayFrom</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/dayFrom"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">yearFrom</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">4</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/yearFrom"/></xsl:attribute>
</input>
To:
<input>
<xsl:attribute name="name">monthTo</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/monthTo"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">dayTo</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/dayTo"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">yearTo</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">4</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/yearTo"/></xsl:attribute>
</input>
<br/>
<br/>
<input type="submit" name="Submit" value="Export Database >>"/>
</form>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
             </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
