<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:include href="footer.xsl"/>
      
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
   <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
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
            
     <table width="100%" class="toptable" cellpadding="1" cellspacing="0">
  <tr>
    <td>
    <table width="100%" align="center" cellpadding="0" cellspacing="0" >
  <tr>
    <td height="22" class="topline"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">
  
      <table width="100%" border="0" cellpadding="0" cellspacing="0" >
     <tr>
      <td width="195" height="121" class="topbgleft">
        <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text> <img  border="0" alt="" src="images/cdash.gif"/>
        </td>
        <td width="425" valign="top" class="insd">
        <div class="insdd">
            <span class="inn1">CDash</span><br />
            <span class="inn2">Register</span>
            </div>
        </td>
        <td height="121" class="insd2"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>
      </tr>
    </table>
    </td>
        </tr>
  <tr>
    <td align="left" class="topbg2"><table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td width="631" align="left" class="bgtm"><ul id="Nav" class="nav">
<li id="Dartboard">
<a href="index.php">HOME</a>
</li>
<li><a href="login.php">LOGIN</a></li>
</ul>
</td>
    <td height="28" class="insd3"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>
  </tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>
 
<br/>

<div style="color: red;"><xsl:value-of select="cdash/error" /></div>

<br/>

<!-- Main -->
<form method="post" action="register.php" name="regform" onSubmit="doSubmit();">
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<tr class="treven">
  <td width="20%" height="2" class="nob"><div align="right"> First Name: </div></td>
  <td  width="80%" height="2" class="nob"><input class="textbox" name="fname" size="20"/></td>
</tr>
<tr class="trodd">
  <td width="20%" height="2" class="nob"><div align="right"> Last Name: </div></td>
  <td  width="80%" height="2" class="nob"><input class="textbox" name="lname" size="20"/></td>
</tr>
<tr class="treven">
  <td width="20%" height="2" class="nob"><div align="right"> Email: </div></td>
  <td  width="80%" height="2" class="nob"><input class="textbox" name="email" size="20"/></td>
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
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
