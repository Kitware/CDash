<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
        
    <xsl:output method="html"/>
    <xsl:template name="footer" match="/">
				<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td height="66" align="left" valign="middle" class="footer">
						<span style="float:right">
					 <img src="images/logo2.gif" height="66"/> CDash 1.0 <xsl:text disable-output-escaping="yes">&amp;copy;</xsl:text> 2008 
					 	<a href="http://www.kitware.com">Kitware Inc.</a>
							<xsl:text>&#160;</xsl:text>
						</span>
						<xsl:text>&#160;</xsl:text>
						<img src="images/blogo.gif" height="66" />
						</td>
    </tr>
    </table>
   </xsl:template>
</xsl:stylesheet>
