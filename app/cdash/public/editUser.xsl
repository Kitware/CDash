<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/>

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
       </head>
        <body bgcolor="#ffffff">

<xsl:call-template name="headerback"/>

<br/>

<div style="color: red;"><xsl:value-of select="cdash/error" /></div>

<br/>

<!-- Main -->
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<tr class="table-heading1">
     <td colspan="5" id="nob"><h3>My Profile</h3></td>
 </tr>
<form method="post" action="" name="updatprofile_form">
<tr class="treven">
  <td width="20%" height="2"><div align="right">First Name</div></td>
  <td  width="80%" height="2" id="nob"><input class="textbox" name="fname" size="20">
 <xsl:attribute name="value"><xsl:value-of select="cdash/user/firstname"/></xsl:attribute>
 </input>
 </td>
</tr>
<tr class="trodd">
  <td width="20%" height="2"><div align="right">Last Name</div></td>
  <td  width="80%" height="2" id="nob"><input class="textbox" name="lname" size="20">
 <xsl:attribute name="value"><xsl:value-of select="cdash/user/lastname"/></xsl:attribute>
 </input>
 </td>
</tr>
<tr class="treven">
  <td width="20%" height="2"><div align="right">Email</div></td>
  <td  width="80%" height="2" id="nob"><input class="textbox" name="email" size="20">
 <xsl:attribute name="value"><xsl:value-of select="cdash/user/email"/></xsl:attribute>
 </input>
 </td>
</tr>
<tr class="trodd">
  <td width="20%" height="2"><div align="right"> Institution</div></td>
  <td  width="80%" height="2" id="nob"><input class="textbox" name="institution" size="20">
 <xsl:attribute name="value"><xsl:value-of select="cdash/user/institution"/></xsl:attribute>
 </input>
 </td>
</tr>
<tr class="treven">
  <td width="20%" id="nob"></td>
  <td width="80%" id="nob"><input type="submit" value="Update Profile" name="updateprofile" class="textbox"/>
  </td>
</tr>
</form>
<form method="post" action="" name="updatemail_form">
<tr class="trodd">
    <td width="20%" height="2" ><div align="right">Current Password</div></td>
    <td width="80%" height="2" id="nob"><input class="textbox" type="password" name="oldpasswd" size="20"/></td>
</tr>
<tr class="treven">
    <td width="20%" height="2" ><div align="right">New Password</div></td>
    <td width="80%" height="2" id="nob"><input class="textbox" type="password"  name="passwd" size="20"/></td>
</tr>
<tr class="trodd">
    <td width="20%" height="2" ><div align="right">Confirm Password</div></td>
    <td width="80%" height="2" id="nob"><input class="textbox" type="password"  name="passwd2" size="20"/></td>
</tr>
<tr class="treven">
  <td width="20%" id="nob"></td>
  <td width="80%" id="nob"><input type="submit" value="Update Password" name="updatepassword" class="textbox"/>
 </td>
</tr>
<tr class="trodd">
    <td width="20%" height="2" ><div align="right">Repository Credential #1</div></td>
    <td width="80%" height="2" id="nob"><xsl:value-of select="cdash/user/credential_0"/></td>
</tr>
<tr class="treven">
    <td width="20%" height="2" ><div align="right">Repository Credential #2</div></td>
    <td width="80%" height="2" id="nob"><input class="textbox" type="text" name="credentials[1]">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/credential_1"/></xsl:attribute>
    </input>
    </td>
</tr>
<tr class="trodd">
    <td width="20%" height="2" ><div align="right">Repository Credential #3</div></td>
    <td width="80%" height="2" id="nob"><input class="textbox" type="text" name="credentials[2]">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/credential_2"/></xsl:attribute>
    </input>
    </td>
</tr>
<tr class="treven">
    <td width="20%" height="2" ><div align="right">Repository Credential #4</div></td>
    <td width="80%" height="2" id="nob"><input class="textbox" type="text" name="credentials[3]">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/credential_3"/></xsl:attribute>
    </input>
    </td>
</tr>
<tr class="trodd">
  <td width="20%" id="nob"></td>
  <td width="80%" id="nob"><input type="submit" value="Update Credentials" name="updatecredentials" class="textbox"/>
 </td>
</tr>
<tr class="treven">
  <td width="20%" height="2"><div align="right">Internal Id</div></td>
  <td  width="80%" height="2" id="nob"><xsl:value-of select="cdash/user/id"/></td>
</tr>
</form>
</tbody>
</table>

<!-- FOOTER -->
<br/>
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
