<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
        
<xsl:output method="html" encoding="iso-8859-1"/>
<xsl:template name="footer" match="/">
<table width="100%" cellpadding="0" cellspacing="0">
  <tr>
   <td height="66" align="left" valign="middle" class="footer">
   <span style="float:right">
   <table>
    <tr>
     <td><img src="images/logo2.gif" height="66"/></td>
   <td>CDash 1.0 <xsl:text disable-output-escaping="yes">&amp;copy;</xsl:text> 2008 
   <a href="http://www.kitware.com">Kitware Inc.</a>
   <xsl:text>&#160;</xsl:text><br/>
  <a href="http://www.cdash.org/Bug">[report problems]</a>
 </td>     
</tr>
   </table>
   </span>
   <xsl:text>&#160;</xsl:text>
   <img src="images/blogo.gif" height="66" />
   </td>
  </tr>
</table>

<!-- Google Analytics -->
<xsl:if test="string-length(/cdash/dashboard/googletracker)>0">
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
var pageTracker = _gat._getTracker("<xsl:value-of select="/cdash/dashboard/googletracker"/>");
pageTracker._initData();
pageTracker._trackPageview();
</script>
</xsl:if>
  </xsl:template>
</xsl:stylesheet>
