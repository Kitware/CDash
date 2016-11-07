<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

<xsl:template name="footer">

<div id="footer">
  <div id="kitwarelogo">
      <a href="http://www.kitware.com"><img src="img/blogo.gif" border="0" height="30" alt="logo" /></a>
  </div>
  <div id="footerlogo">
    <a href="http://www.cdash.org"><img src="img/cdash.png" border="0" height="30" alt="CDash logo"/></a>
    <span id="footertext">
    CDash
   <xsl:choose>
     <xsl:when test="(count(/cdash/user/admin)=1 and /cdash/user/admin!=0) or (count(/cdash/user_is_admin)=1 and /cdash/user_is_admin!=0)">
       <a href="gitinfo.php"><xsl:value-of select="/cdash/version"/></a>
     </xsl:when>
     <xsl:otherwise>
       <xsl:value-of select="/cdash/version"/>
     </xsl:otherwise>
   </xsl:choose>
   <xsl:text disable-output-escaping="yes"> &amp;copy;</xsl:text>
   <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text> <a href="http://www.kitware.com">Kitware</a>
   | <a href="http://www.cdash.org/Bug" target="blank">Report problems</a>
   <xsl:choose>
   <xsl:when test="string-length(/cdash/generationtime)>0">
     | <xsl:value-of select="/cdash/generationtime"/>s
   </xsl:when>
   <xsl:otherwise>
     <xsl:if test="string-length(/cdash/database/size)>0">
       | <xsl:value-of select="/cdash/database/size"/>
     </xsl:if>
   </xsl:otherwise>
   </xsl:choose>
   </span>
  </div>
</div>
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
