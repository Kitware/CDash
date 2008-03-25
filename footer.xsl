<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>

<xsl:template name="footer" match="/">
<table width="100%" cellpadding="0" cellspacing="0">
  <tr>
   <td height="66" align="left" valign="middle" class="footer">
   <table style="float:right">
    <tr>
     <td><a href="http://www.cdash.org"><img src="images/logo2.gif" border="0" height="66" alt="CDash logo"/></a></td>
   <td>CDash <xsl:value-of select="/cdash/version"/><xsl:text disable-output-escaping="yes"> &amp;copy;</xsl:text> 2008 
   <a href="http://www.kitware.com">Kitware Inc.</a>
   <xsl:text>&#160;</xsl:text><br/>
  <a href="http://www.cmake.org/Bug">[report problems]</a>
 </td>     
</tr>
   </table>
   <xsl:text>&#160;</xsl:text>
   <img src="images/blogo.gif" height="66" alt="logo" />
   </td>
  </tr>
</table>

<!-- Google Analytics -->
<xsl:if test="string-length(/cdash/dashboard/googletracker)>0">
<xsl:text disable-output-escaping="yes">
&lt;script type="text/javascript"&gt;
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
&lt;/script&gt;
&lt;script type="text/javascript"&gt;
var pageTracker = _gat._getTracker("</xsl:text><xsl:value-of select="/cdash/dashboard/googletracker"/><xsl:text disable-output-escaping="yes">");
pageTracker._initData();
pageTracker._trackPageview();
&lt;/script&gt;
</xsl:text>
</xsl:if>
  </xsl:template>
</xsl:stylesheet>
