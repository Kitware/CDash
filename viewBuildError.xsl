<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
    
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
    
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
   
       <xsl:call-template name="header"/>
<br/>

<p><b>Site:</b><xsl:value-of select="cdash/build/site"/></p>
<p><b>Build Name:</b><xsl:value-of select="cdash/build/buildname"/></p>  
 <p><b>Build Time:</b><xsl:value-of select="cdash/build/starttime"/></p>    
Found <xsl:value-of select="count(cdash/errors/error)"/><xsl:text>&#x20;</xsl:text><xsl:value-of select="cdash/errortypename"/>s<br/>
<p><a>
<xsl:attribute name="href">viewBuildError.php?type=<xsl:value-of select="cdash/nonerrortype"/>&#38;buildid=<xsl:value-of select="cdash/build/buildid"/></xsl:attribute>
<xsl:value-of select="cdash/nonerrortypename"/>s</a> are here.</p>
<xsl:for-each select="cdash/errors/error">
<hr/>
<h3><A Name="650">Build Log line <xsl:value-of select="logline"/></A></h3>
<xsl:if test="sourceline>0">
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
  <a href="http://www.itk.org/cgi-bin/viewcvs.cgi/Code/BasicFilters/itkGradientRecursiveGaussianImageFilter.txx?cvsroot=Insight&amp;annotate=HEAD#84">CVS</a>
</xsl:if>
<pre><xsl:value-of select="precontext"/></pre>
<pre><xsl:value-of select="text"/></pre>
<pre><xsl:value-of select="postcontext"/></pre>
</xsl:for-each>
<hr/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
