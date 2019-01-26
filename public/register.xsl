<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

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

         <xsl:text disable-output-escaping="yes">
        &lt;script language="javascript" type="text/javascript" &gt;
          function doSubmit()
            {
            document.getElementById('url').value = 'catchbot';
            }
         &lt;/script&gt;
         </xsl:text>
       </head>
    <body>


<div id="header">
 <div id="headertop">
 <div id="topmenu">
    <a><xsl:attribute name="href">user.php</xsl:attribute>
        <xsl:choose>
          <xsl:when test="cdash/user/id>0">My CDash</xsl:when>
          <xsl:otherwise>Login</xsl:otherwise>
        </xsl:choose></a><a href="viewProjects.php">All Dashboards</a>
     <xsl:if test="cdash/user/id>0">
       <xsl:call-template name="logout"/>
     </xsl:if>
  </div>
 </div>

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
        CDash - Register
      </span>
    </div>
 </div>
</div>

<br/>
<div style="color: red;"><xsl:value-of select="cdash/error" /><br/></div>

<!-- Main -->
<form method="post" action="register.php" name="regform" onsubmit="doSubmit();">
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<tr class="treven">
  <td width="20%" height="2" class="nob"><div align="right"> First Name: </div></td>
  <td  width="80%" height="2" class="nob">
    <input class="textbox" name="fname" size="20">
    <xsl:attribute name="value">
      <xsl:value-of select="cdash/firstname"/>
    </xsl:attribute>
    </input>
  </td>
</tr>
<tr class="trodd">
  <td width="20%" height="2" class="nob"><div align="right"> Last Name: </div></td>
  <td  width="80%" height="2" class="nob">
    <input class="textbox" name="lname" size="20">
    <xsl:attribute name="value">
      <xsl:value-of select="cdash/lastname"/>
    </xsl:attribute>
    </input>
  </td>
</tr>
<tr class="treven">
  <td width="20%" height="2" class="nob"><div align="right"> Email: </div></td>
  <td  width="80%" height="2" class="nob">
    <input class="textbox" name="email" size="20">
    <xsl:attribute name="value">
      <xsl:value-of select="cdash/email"/>
    </xsl:attribute>
    </input>
  </td>
</tr>
<tr class="trodd">
    <td width="20%" height="2" class="nob"><div align="right">Password: </div></td>
    <td width="80%" height="2" class="nob"><input class="textbox" type="password"  name="passwd" size="20"/></td>
</tr>
<tr class="treven">
    <td width="20%" height="2" class="nob"><div align="right">Confirm Password: </div></td>
    <td width="80%" height="2" class="nob"><input class="textbox" type="password"  name="passwd2" size="20"/></td>
</tr>
<tr class="trodd">
  <td width="20%" height="2" class="nob"><div align="right"> Institution: </div></td>
  <td  width="80%" height="2" class="nob"><input class="textbox" name="institution" size="20"/></td>
</tr>
<tr>
  <td width="20%" class="nob"></td>
  <td width="80%" class="nob"><input type="submit" value="Register" name="sent" class="textbox"/>
  <input id="url" class="textbox" type="hidden" name="url" size="20"/>
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
