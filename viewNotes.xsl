<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
			<xsl:include href="header.xsl"/>
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
			
							<xsl:call-template name="header"/>
<br/>

<h3 style="background: #b0c4de">Notes for <xsl:value-of select="cdash/build/site"/>--<xsl:value-of select="cdash/build/buildname"/>-<xsl:value-of select="cdash/build/stamp"/></h3>
<p>
<img SRC="images/Alert.gif" ALT="Notes" border="0" ALIGN="texttop"/>
<b> <xsl:value-of select="cdash/note/time"/></b>
<b> -- <xsl:value-of select="cdash/note/name"/></b><br/>
<pre>
<xsl:value-of select="cdash/note/text"/>
</pre></p>
<br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
					   </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
