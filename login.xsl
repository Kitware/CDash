<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    
    <xsl:output method="html"/>
    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
									<!-- Include CDash Menu Stylesheet -->    
								<link rel="stylesheet" href="javascript/cdashmenu.css" type="text/css" media="screen" charset="utf-8" />
								
								<!-- Include the rounding css -->
								<script src="javascript/rounded.js"></script>
       </head>
       <body bgcolor="#ffffff">
   
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td valign="bottom" width="100%">
<div style="margin: 0pt auto; background-color: #6699cc;"  class="rounded">    
<font color="#ffffff"><h2>CDash - Login</h2>
<h3>Welcome to CDash!</h3></font><br/>
</div>
</td></tr><tr><td></td><td>
<!-- Menu -->
<ul id="Nav" class="nav">
  <li>
     <a href="index.php">Home</a>
   </li>
   <li>
     <a href="register.php">Register</a> 
  </li>
</ul>
</td>
</tr>
</table>
	
<script type="text/javascript">
  Rounded('rounded', 15, 15,0,0);
</script>

<br/>
<div style="color: green;"><xsl:value-of select="cdash/message" /></div>
<br/>

<!-- Main -->
<table width="100%" border="0" height="119">
<form method="post" action="" name="loginform">
<tr>
  <td width="10%" height="2"><div align="right"> Email: </div></td>
  <td  width="90%" height="2"><input class="textbox" name="login" size="40"/></td>
</tr>
<tr>
    <td width="10%" height="2"><div align="right">Password: </div></td>
    <td width="90%" height="2"><input class="textbox" type="password"  name="passwd" size="20"/></td>
</tr>
<tr>
  <td width="10%"></td>
  <td width="90%"><input type="submit" value="Login &gt;&gt;" name="sent" class="textbox"/>
  </td>
</tr> 
</form>
</table>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
