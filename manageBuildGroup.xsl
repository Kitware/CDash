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
<font color="#ffffff"><h2>CDash - Build Groups</h2>
<h3>Manage groups of builds</h3></font>
<br/></div>
</td>
</tr>
<tr>
<td></td><td>
<!-- Menu -->
<ul id="Nav" class="nav">
  <li>
     <a href="user.php">Back</a>
  </li>
</ul>
</td>
</tr>
</table>


<br/><br/>

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

<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%"  border="0">
  <tr>
    <td width="14%"><div align="right"><strong>Project:</strong></div></td>
    <td width="86%"><select onchange="location = 'manageBuildGroup.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
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
  </tr>
  
  <!-- If a project has been selected -->
  <xsl:if test="count(cdash/project)>0">
  <tr>
    <td></td>
    <td></td>
  </tr>
  <tr>
    <td ><div align="right"></div></td>
    <td bgcolor="#DDDDDD"><strong>Current groups</strong></td>
  </tr>
  
  <!-- List the current groups -->
   <tr>
     <td><div align="right"></div></td>
     <td>
     <table>
     <xsl:for-each select="cdash/project/group">
     <form method="post" action="">
     <xsl:attribute name="form"><xsl:value-of select="id"/></xsl:attribute>
     
     <tr>
     <td><xsl:value-of select="name"/></td>
     <td>
     <a><xsl:attribute name="href">manageBuildGroup.php?projectid=<xsl:value-of select="/cdash/project/id"/>&amp;groupid=<xsl:value-of select="id"/>&amp;up=1</xsl:attribute> [up]</a>
     <a><xsl:attribute name="href">manageBuildGroup.php?projectid=<xsl:value-of select="/cdash/project/id"/>&amp;groupid=<xsl:value-of select="id"/>&amp;down=1</xsl:attribute> [down]</a>
     </td>
     <td>
     <xsl:if test="name!='Nightly' and name!='Experimental' and name !='Continuous'">  <!-- cannot delete Nightly/Continuous/Experimental -->
     <input type="hidden" name="groupid">
     <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
     </input>
     <input name="newname" type="text" id="newname" size="40"/><input type="submit" name="rename" value="Rename"/>
     </xsl:if>
     </td><td>
     <xsl:if test="name!='Nightly' and name!='Experimental' and name !='Continuous'"> <!-- cannot delete Nightly/Continuous/Experimental -->
     <input type="submit" name="deleteGroup" value="Delete Group"/>
     </xsl:if>
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
    <td  bgcolor="#DDDDDD"><strong>Create new group</strong></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><i>Note: created groups will be kept in the history.</i></td>
  </tr>
  <tr>
    <td><div align="right">Name:</div></td>
    <td><input name="name" type="text" id="name" size="40"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="createGroup" value="Create Group"/></td>
  </tr>
  </xsl:if>
  
</table>
</form>
</xsl:otherwise>
</xsl:choose>
        
<script type="text/javascript">
  Rounded('rounded', 15, 15,0,0);
</script>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
