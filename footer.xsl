<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
        
    <xsl:output method="html"/>
    <xsl:template name="footer" match="/">
     <a href="http://public.kitware.com/CDash/"><img alt="Cdash" width="60" src="images/cdash-60.gif" border="0"/></a>   
     <font color="#666666" face="Verdana, Arial, Helvetica, sans-serif" size="2">
    CDash 1.0 <xsl:text disable-output-escaping="yes">&amp;copy;</xsl:text>  2007 by <a href="http://kitware.com" target="_blank"> Kitware Inc. </a>
   </font>
   </xsl:template>
</xsl:stylesheet>
