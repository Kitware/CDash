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
         <script>
          
          function doSubmit()
          {
            document.getElementById('url').value = 'catchbot';
          }
          
         </script>
       </head>
       <body bgcolor="#ffffff">
   
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td bgcolor="#6699cc" valign="top" width="100%">
<font color="#ffffff"><h2>CDash - Register</h2>
<h3>Welcome to CDash!</h3></font>
</td></tr><tr><td></td><td>
<div id="navigator">
<table border="0" cellpadding="0" cellspacing="0">
<tr>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">index.php</xsl:attribute>Home</a>
</p>
</td>

<td align="center" width="5">
<p></p>
</td>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">login.php</xsl:attribute>Login</a>
</p>
</td>

</tr>
</table>
</div>
</td>
</tr>
</table>
 
<br/>

<div style="color: red;"><xsl:value-of select="cdash/error" /></div>

<br/>

<!-- Main -->
<table width="100%" border="0" height="119">
<form method="post" action="register.php" name="regform" onSubmit="doSubmit();">
<tr>
  <td width="10%" height="2"><div align="right"> First Name: </div></td>
  <td  width="90%" height="2"><input class="textbox" name="fname" size="20"/></td>
</tr>
<tr>
  <td width="10%" height="2"><div align="right"> Last Name: </div></td>
  <td  width="90%" height="2"><input class="textbox" name="lname" size="20"/></td>
</tr>
<tr>
  <td width="10%" height="2"><div align="right"> Email: </div></td>
  <td  width="90%" height="2"><input class="textbox" name="email" size="20"/></td>
</tr>
<tr>
    <td width="10%" height="2"><div align="right">Password: </div></td>
    <td width="90%" height="2"><input class="textbox" type="password"  name="passwd" size="20"/></td>
</tr>
<tr>
    <td width="10%" height="2"><div align="right">Confirm Password: </div></td>
    <td width="90%" height="2"><input class="textbox" type="password"  name="passwd2" size="20"/></td>
</tr>
<tr>
  <td width="10%" height="2"><div align="right"> Institution: </div></td>
  <td  width="90%" height="2"><input class="textbox" name="institution" size="20"/></td>
</tr>
<tr style="display: none;">
  <td width="10%" height="2"><div align="right"> url: </div></td>
  <td  width="90%" height="2"><input id="url" class="textbox" name="url" size="20"/></td>
</tr>
<tr>
  <td width="10%"></td>
  <td width="90%"><input type="submit" value="Register" name="sent" class="textbox"/>
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
