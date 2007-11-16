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

<!-- Main -->

<br/>
<p><b>Site:</b><xsl:value-of select="cdash/build/site"/> 
</p>
<p><b>Build Name:</b><xsl:value-of select="cdash/build/buildname"/> 
</p>
<a>
<xsl:attribute name="href"><xsl:value-of select="cdash/dynamicanalysis/href"/></xsl:attribute>
<xsl:value-of select="cdash/dynamicanalysis/filename"/></a>

<font>
<xsl:attribute name="color">
  <xsl:choose>
     <xsl:when test="cdash/dynamicanalysis/status='Passed'">
      #00aa00
     </xsl:when>
    <xsl:otherwise>
      #ffcc66
     </xsl:otherwise>
  </xsl:choose>
</xsl:attribute>
<xsl:value-of select="cdash/dynamicanalysis/status"/>
</font>
<pre><xsl:value-of select="cdash/dynamicanalysis/log"/></pre>
 <br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
