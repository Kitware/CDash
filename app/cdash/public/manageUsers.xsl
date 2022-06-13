<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headscripts.xsl"/>
   <xsl:include href="headeradminproject.xsl"/>

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

        <!-- Include project roles -->
        <script src="js/cdashManageUsers.js" type="text/javascript"></script>
        <!-- Functions to confirm the email -->
        <xsl:text disable-output-escaping="yes">
              &lt;script language="JavaScript" type="text/javascript"&gt;

              $(document).ready(function() {
                $(window).keydown(function(event){
                  if(event.keyCode == 13) {
                  event.preventDefault();
                  return false;
                  }
                });
              });

              function confirmRemove() {
                 if (window.confirm("Are you sure you want to remove this user from the database?")){
                    return true;
                 }
                 return false;
              }

              function generatePassword()
                {
                var chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
                var passwd = "";
                for(x=0;x&lt;12;x++)
                  {
                  i = Math.floor(Math.random() * 62);
                  passwd += chars.charAt(i);
                  }
                $("input#passwd").val(passwd);
                $("input#passwd2").val(passwd);
                $("#clearpasswd").html("("+passwd+")");
                }

              &lt;/script&gt;
        </xsl:text>
       </head>
       <body bgcolor="#ffffff">
<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headeradminproject_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headeradminproject"/>
</xsl:otherwise>
</xsl:choose>

<br/>

<xsl:if test="string-length(cdash/warning)>0">
<div style="color: green;"><xsl:value-of select="cdash/warning"/></div><br/>
</xsl:if>

<div style="color: red;"><xsl:value-of select="cdash/error" /></div>

<form method="post" action="manageUsers.php" name="regform">
<table width="100%"  border="0">
  <tr>
    <td><div align="right"></div></td>
    <td  bgcolor="#DDDDDD"><strong>Search for already registered users</strong></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td>
    <xsl:choose>
    <xsl:when test="/cdash/fullemail">
      <i>type the full email address of the user to add</i>
    </xsl:when>
    <xsl:otherwise>
      <i>start typing a name or email address (% to display all users)</i>
    </xsl:otherwise>
    </xsl:choose>
    </td>
  </tr>
  <tr>
    <td><div align="right">Search:</div></td>
    <td><input name="search" type="text" id="search" size="40">
    <xsl:attribute name="value"><xsl:value-of select="cdash/search"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><div name="newuser" id="newuser"></div></td>
  </tr>
  <tr>
    <td></td>
    <td  bgcolor="#DDDDDD"><strong>Add new user</strong></td>
  </tr>
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
  <td  width="80%" height="2" class="nob"><input class="textbox"  name="email" size="20"/></td>
</tr>
<tr class="trodd">
    <td width="20%" height="2" class="nob"><div align="right">Password: </div></td>
    <td width="80%" height="2" class="nob"><input class="textbox" type="password"  id="passwd" name="passwd" size="20"/>
    <input type="button" value="Generate Password" onclick="javascript:generatePassword();" name="generatepassword" class="textbox"/>
    <span id="clearpasswd"></span>
    </td>
</tr>
<tr class="treven">
    <td width="20%" height="2" class="nob"><div align="right">Confirm Password: </div></td>
    <td width="80%" height="2" class="nob"><input class="textbox" type="password" id="passwd2"  name="passwd2" size="20"/></td>
</tr>
<tr class="trodd">
  <td width="20%" height="2" class="nob"><div align="right"> Institution: </div></td>
  <td  width="80%" height="2" class="nob"><input class="textbox" name="institution" size="20"/></td>
</tr>
<tr>
  <td width="20%" class="nob"></td>
  <td width="80%" class="nob"><input type="submit" value="Add user >>" name="adduser" class="textbox"/>
  (password will be display in clear upon addition)
  </td>
</tr>
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
