<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="logout.xsl"/>
   <xsl:include href="footer.xsl"/>
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="headscripts.xsl"/>
   <xsl:include href="local/headscripts.xsl"/>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
         <xsl:call-template name="headscripts"/>
       </head>
       <body>


   <div id="header">
 <div id="headertop"></div>

 <div id="headerbottom">
    <div id="headerlogo">
      <a>
        <xsl:attribute name="href">
        <xsl:value-of select="cdash/dashboard/home"/></xsl:attribute>
        <img id="projectlogo" border="0" height="50px">
        <xsl:attribute name="alt"></xsl:attribute>
        <xsl:choose>
        <xsl:when test="cdash/dashboard/logoid>0">
          <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
         </xsl:when>
        <xsl:otherwise>
         <xsl:attribute name="src">img/cdash.png?rev=2019-05-08</xsl:attribute>
        </xsl:otherwise>
        </xsl:choose>
        </img>
      </a>
    </div>
    <div id="headername2">
      <span id="subheadername">
        CDash - Recover password
      </span>
    </div>
 </div>
</div>

<br/>
<div style="color: red;"><xsl:value-of select="cdash/warning" /></div>
<div style="color: green;"><xsl:value-of select="cdash/message" /></div>
<br/>

<!-- Main -->
<form method="post" action="" name="loginform">
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<tr class="table-heading">
  <td width="10%" class="nob"><div align="right"></div></td>
  <td width="90%" class="nob"><div align="left"><b>Enter your email address you registered with CDash.</b></div></td>
</tr>
<tr class="table-heading">
  <td width="10%" class="nob"><div align="right"> Email: </div></td>
  <td  width="90%" class="nob"><input class="textbox" name="email" size="40"/></td>
</tr>
<tr class="table-heading">
  <td width="10%" class="nob"></td>
  <td width="90%" class="nob"><input type="submit" value="Recover password &gt;&gt;" name="recover" class="textbox"/>
  </td>
</tr>
</tbody>
</table>
</form>

<!-- FOOTER -->
<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="footer_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="footer"/>
</xsl:otherwise>
</xsl:choose>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
