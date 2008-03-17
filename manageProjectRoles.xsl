<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 
   
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>

    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
   
        <!-- Include project roles -->
        <script src="javascript/jquery.js"></script>
        <script src="javascript/cdashProjectRole.js"></script>

       </head>
       <body bgcolor="#ffffff">
         <xsl:call-template name="headerback"/>
<br/>

<xsl:choose>
 <xsl:when test="cdash/group_created=1">
 The group <b><xsl:value-of select="cdash/group_name"/></b> has been created successfully.<br/>          
 Click here to access the  <a>
 <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/project_name"/></xsl:attribute>
project page</a>
 </xsl:when>
<xsl:otherwise>

<xsl:if test="string-length(cdash/warning)>0">
<xsl:value-of select="cdash/warning"/>
</xsl:if>


<table width="100%"  border="0">
  <tr>
   <form name="form1" method="post" action="">
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" ><select onchange="location = 'manageProjectRoles.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
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
  
  <!-- If a project has been selected -->
  <xsl:if test="count(cdash/project)>0">
  <tr>
    <td></td>
    <td></td>
  </tr>
  <tr>
    <td ><div align="right"></div></td>
    <td bgcolor="#DDDDDD"><strong>Current users</strong></td>
  </tr>
  
  <!-- List the current users -->
   <tr>
     <td><div align="right"></div></td>
     <td>
     <table>
       <tr bgcolor="#CCCCCC">
        <td><center><b>Firstname</b></center></td>
        <td><center><b>Lastname</b></center></td>
        <td><center><b>Email</b></center></td>
        <td><center><b>Role</b></center></td>
        <td><center><b>CVS Login</b></center></td>
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
        <td><xsl:value-of select="firstname"/></td>
        <td><xsl:value-of select="lastname"/></td>
        <td><xsl:value-of select="email"/></td>
        <td>
        <select name="role">
         <option value="0"><xsl:if test="role=0"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Normal User</option>
         <option value="1"><xsl:if test="role=1"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Site maintainer</option>
         <option value="2"><xsl:if test="role=2"><xsl:attribute name="selected"></xsl:attribute></xsl:if>Project Administrator</option>
       </select>
       </td>
       <td>
        <input type="text" name="cvslogin" size="20">
        <xsl:attribute name="value"><xsl:value-of select="cvslogin"/></xsl:attribute>
        </input>
        </td>
        <td>
        <input type="submit" name="updateuser" value="update"/>
        <input type="submit" name="removeuser" value="remove"/>
        </td>
        
        </tr>
        </form>
        </xsl:for-each>
     </table>
     </td>
     </tr>
  <tr>
    <td></td>
    <td></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td  bgcolor="#DDDDDD"><strong>Add new user</strong></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><i>start typing a name or email address (% to display all users)</i></td>
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
    
  </xsl:if>
</table>
<br/>

</xsl:otherwise>
</xsl:choose>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
