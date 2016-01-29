<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headscripts.xsl"/>
   <xsl:include href="headeradminproject.xsl"/>

   <!-- Include local common files -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headscripts.xsl"/>
   <xsl:include href="local/headeradminproject.xsl"/>

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
         <xsl:comment><![CDATA[[if IE]>
          <link rel="stylesheet" href="css/tabs_ie.css" type="text/css" media="projection, screen" />
          <![endif]]]></xsl:comment>
          <!-- Include project roles -->
          <script src="js/jquery-1.6.2.js" type="text/javascript"></script>
          <script src="js/cdashProjectRole.js" type="text/javascript"></script>
          <script src="js/ui.tabs.js" type="text/javascript"></script>


        <!-- Functions to confirm the email -->
        <xsl:text disable-output-escaping="yes">
              &lt;script type="text/javascript"&gt;
              function confirmEmail() {
                 if (window.confirm("Are you sure you want to send this email to all site maintainers?")){
                    return true;
                 }
                 return false;
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
<xsl:value-of select="cdash/warning"/>
</xsl:if>

<div style="color: red;"><xsl:value-of select="cdash/error" /></div>
<table  border="0">
  <tr>
   <form name="form1" method="post" action="">
    <td width="15%"><div align="right"><strong>Project:</strong></div></td>
    <td width="85%" ><select onchange="location = 'manageProjectRoles.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option>
        <xsl:attribute name="value">0</xsl:attribute>
        Choose...
        </option>

        <xsl:for-each select="cdash/availableproject">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select></td>
    </form>
  </tr>
</table>

<xsl:choose>
 <xsl:when test="count(cdash/cvsuser)>0">
    <div id="wizard">
          <table width="800" border="0">
             <!-- Show the cvsusers if imported to check that they are valid -->
            <form  method="post">
            <td valign="top" width="100"><div align="right">Import CVS Users:</div></td>
            <td>
            <table>
            <tr style="background-color:#CCCCCC">
            <td>Send</td>
            <td>Email</td>
            <td>CVS Login</td>
            <td>First Name</td>
            <td>Last Name</td>
            </tr>
            <xsl:for-each select="cdash/cvsuser">
            <tr>
            <td><input type="checkbox" value="1">
            <xsl:attribute name="checked">checked</xsl:attribute>
            <xsl:attribute name="name">cvsuser[<xsl:value-of select="id"/>]</xsl:attribute>
            </input>
            </td>
            <td><xsl:value-of select="email"/>
            <input type="hidden">
            <xsl:attribute name="value"><xsl:value-of select="email"/></xsl:attribute>
            <xsl:attribute name="name">email[<xsl:value-of select="id"/>]</xsl:attribute>
            </input>
            </td>
            <td><xsl:value-of select="cvslogin"/>
            <input type="hidden">
            <xsl:attribute name="value"><xsl:value-of select="cvslogin"/></xsl:attribute>
            <xsl:attribute name="name">cvslogin[<xsl:value-of select="id"/>]</xsl:attribute>
            </input>
            </td>
            <td><xsl:value-of select="firstname"/>
            <input type="hidden">
            <xsl:attribute name="value"><xsl:value-of select="firstname"/></xsl:attribute>
            <xsl:attribute name="name">firstname[<xsl:value-of select="id"/>]</xsl:attribute>
            </input>
            </td>
            <td><xsl:value-of select="lastname"/>
            <input type="hidden">
            <xsl:attribute name="value"><xsl:value-of select="lastname"/></xsl:attribute>
            <xsl:attribute name="name">lastname[<xsl:value-of select="id"/>]</xsl:attribute>
            </input>
            </td>
            </tr>
            </xsl:for-each>
            <tr>
            <td><input type="submit" name="registerUsers" value="Register Users and Send email"/></td>
            </tr>
            </table>
            </td>
            </form>
          </table>
      </div>
    </xsl:when>
    <xsl:otherwise>
  <!-- If a project has
  been selected -->
  <xsl:if test="count(cdash/project)>0">
   <div id="wizard">
      <ul>
          <li>
            <a href="#fragment-1"><span>Current users</span></a></li>
          <li>
            <a href="#fragment-2"><span>Search for already registered users</span></a></li>
          <li>
            <a href="#fragment-3"><span>Register a new user</span></a></li>
          <li>
            <a href="#fragment-4"><span>Import users from CVS file </span></a></li>
      </ul>
    <div id="fragment-1" class="tab_content" >
        <div class="tab_help"></div>

          <table width="800"  border="0">
            <tr>
            <td><div align="right"></div></td>
            <td>
            <span style="color: #ff0000;">
              <xsl:for-each select="cdash/baduser">
              <xsl:choose>
                <xsl:when test="emailtype=0">
                * <b><xsl:value-of select="author"/></b> (<xsl:value-of select="email"/>) doesn't want to receive emails but has been submitting in the past month.
                </xsl:when>
                <xsl:otherwise>
                * <b><xsl:value-of select="author"/></b> is not registered for this project but has been submitting in the past month.
                </xsl:otherwise>
              </xsl:choose>
              <br/>
              </xsl:for-each>
              </span>
            </td>
            </tr>
            <tr>
             <td><div align="right"></div></td>
             <td>
             <table width="850">
               <tr bgcolor="#CCCCCC">
                <td><center><b>User</b></center></td>
                <td><center><b>Email</b></center></td>
                <td><center><b>Role</b></center></td>
                <td><center><b>Repository Credentials<br/>(cred1;cred2;)</b></center></td>
                <td><center><b>Notifications</b></center></td>
                <td><center><b>Action</b></center></td>
               </tr>

               <xsl:for-each select="cdash/user">
               <form method="post" action="">
               <xsl:attribute name="form">formuser<xsl:value-of select="id"/></xsl:attribute>
               <tr>
                <xsl:attribute name="bgcolor"><xsl:value-of select="bgcolor"/></xsl:attribute>
                <input name="userid" type="hidden">
                <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
                </input>
                <td><xsl:value-of select="firstname"/><xsl:text disable-output-escaping="yes"> </xsl:text><xsl:value-of select="lastname"/></td>
                <td><xsl:value-of select="email"/></td>
                <td>
                <select name="role">
                 <option value="0"><xsl:if test="role=0"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Normal User</option>
                 <option value="1"><xsl:if test="role=1"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Site maintainer</option>
                 <option value="2"><xsl:if test="role=2"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Project Administrator</option>
                </select>
                </td>
               <td>
                 <input name="credentials" type="text">
                  <xsl:attribute name="value"><xsl:for-each select="repositorycredential"><xsl:value-of select="."/>;</xsl:for-each></xsl:attribute>
                 </input>
                </td>
                <td>
                <select name="emailtype">
                 <option value="0"><xsl:if test="emailtype=0"><xsl:attribute name="selected"></xsl:attribute></xsl:if>No email</option>
                 <option value="1"><xsl:if test="emailtype=1"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Email checkins</option>
                 <option value="2"><xsl:if test="emailtype=2"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Email nighlty</option>
                 <option value="3"><xsl:if test="emailtype=3"><xsl:attribute name="selected"></xsl:attribute></xsl:if>All emails</option>
               </select>
                </td>
                <td>
                <input type="submit" name="updateuser" value="Update"/>
                <input type="submit" name="removeuser" value="Remove"/>
                </td>
                </tr>
                </form>
                </xsl:for-each>
             </table>
             </td>
             </tr>
          </table>
          <!-- Send email to site maintainer -->
          <form name="emailsitemaintainers_form" method="post" action="">
          <table width="100%"  border="0">
            <tr>
              <td  bgcolor="#DDDDDD"><strong>Send email to site maintainers</strong></td>
            </tr>
            <tr>

            <td colspan="2"><textarea style="width:872px;" name="emailMaintainers"  rows="10"></textarea></td>
            </tr>
            <tr>
            <td></td>
            <td align="right"><input type="submit" onclick="return confirmEmail()" name="sendEmailToSiteMaintainers" value="Send email to all the site maintainers"/></td>
            </tr>
          </table>
          </form>
    </div>
    <div id="fragment-2" class="tab_content" >
        <div class="tab_help"></div>
          <table width="800"  border="0">
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
              <td><input name="search" type="text" id="search" size="40"/></td>
            </tr>
            <tr>
              <td><div align="right"></div></td>
              <td><div name="newuser" id="newuser"></div></td>
              <input id="projectid" type="hidden">
              <xsl:attribute name="value"><xsl:value-of select="cdash/project/id"/></xsl:attribute>
              </input>
            </tr>
          </table>
    </div>
    <div id="fragment-3" class="tab_content" >
        <div class="tab_help"></div>
        <form  method="post">
          <table width="800"  border="0">
            <tr>
                <td><div align="right">User Email:</div></td>
            <td>
            <input name="registeruseremail" type="text" id="registeruseremail" size="40"/>
            </td>
            </tr>
            <tr>
             <td><div align="right">First name:</div></td>
            <td>
            <input name="registeruserfirstname" type="text" id="registeruserfirstname" size="40"/>
            </td>
            </tr>
            <tr>
             <td><div align="right">Last name:</div></td>
            <td>
            <input name="registeruserlastname" type="text" id="registeruserlastname" size="40"/>
            </td>
            </tr>
            <tr>
             <td><div align="right">Repository credential:</div></td>
            <td>
            <input name="registeruserrepositorycredential" type="text" id="registeruserrepositorycredential" size="40"/>
            * email address is automatically added as a credential
            </td>
            </tr>
            <tr>
            <td></td>
            <td>
            <input type="submit" name="registerUser" value="Register User"/>
            </td>
            </tr>
          </table>
          </form>
    </div>
    <div id="fragment-4" class="tab_content" >
        <div class="tab_help"></div>
          <table width="800"  border="0">
            <tr>
                <td><div align="right">CVS Users File:</div></td>

            <td>
            <form method="post" action="" enctype="multipart/form-data">
            <input name="cvsUserFile" type="file"/><input type="submit" name="importUsers" value="Import"/>
            </form>
            </td>
            </tr>
          </table>
    </div>
  </div>
</xsl:if> <!-- end if a project has been selected -->
</xsl:otherwise>
</xsl:choose>

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
