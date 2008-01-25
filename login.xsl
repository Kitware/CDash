<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    
  <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
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
       <body>
 

   	<table width="100%" class="toptable" cellpadding="1" cellspacing="0">
  <tr>
    <td>
		<table width="100%" align="center" cellpadding="0" cellspacing="0" >
  <tr>
    <td height="22" class="topline"><xsl:text>&#160;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">
	
		  <table width="100%" height="121" border="0" cellpadding="0" cellspacing="0" >
	   <tr>
		  <td width="195" height="121" class="topbgleft">
				<xsl:text>&#160;</xsl:text> <img  border="0" alt="" src="images/cdash.gif"/>
				</td>
				<td width="425" valign="top" class="insd">
				<div class="insdd">
						<span class="inn1">CDash</span><br />
						<span class="inn2">Login</span>
						</div>
				</td>
				<td height="121" class="insd2"><xsl:text>&#160;</xsl:text></td>
			</tr>
		</table>
		</td>
				</tr>
  <tr>
    <td align="left" class="topbg2"><table width="100%" height="28" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td width="631" align="left" class="bgtm"><ul id="Nav" class="nav">
<li id="Dartboard">
<a href="index.php">HOME</a>
</li>
<li><a href="register.php">REGISTER</a></li>
</ul>
</td>
		<td height="28" class="insd3"><xsl:text>&#160;</xsl:text></td>
	</tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>

<br/>
<div style="color: green;"><xsl:value-of select="cdash/message" /></div>
<br/>

<!-- Main -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<form method="post" action="" name="loginform">
<tr class="table-heading">
  <td width="10%" id="nob"><div align="right"> Email: </div></td>
  <td  width="90%" id="nob"><input class="textbox" name="login" size="40"/></td>
</tr>
<tr class="table-heading">
    <td width="10%" id="nob" ><div align="right">Password: </div></td>
    <td width="90%" id="nob"><input class="textbox" type="password"  name="passwd" size="20"/></td>
</tr>
<tr class="table-heading">
  <td width="10%" id="nob"></td>
  <td width="90%" id="nob"><input type="submit" value="Login &gt;&gt;" name="sent" class="textbox"/>
  </td>
</tr> 
</form>
</tbody>
</table>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
