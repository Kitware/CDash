<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:include href="footer.xsl"/>
    <xsl:include href="headerback.xsl"/> 
    
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
      
       <!-- Functions to confirm the remove -->
  <xsl:text disable-output-escaping="yes">
        &lt;script language="JavaScript"&gt;
        function confirmDelete() {
           if (window.confirm("Are you sure you want to delete this group? If the group is not empty, builds will be put in their original group.")){
              return true;
           }
           return false;
        }
        &lt;/script&gt;
  </xsl:text>
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
<b>Warning: <xsl:value-of select="cdash/warning"/></b><br/><br/>
</xsl:if>

<form name="form1" enctype="multipart/form-data" method="post">
<xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
<table width="100%"  border="0">
  <tr>
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" ><select onchange="location = 'manageBuildGroup.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
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
     <form method="post">
     <xsl:attribute name="name">form_<xsl:value-of select="id"/></xsl:attribute>
     <xsl:attribute name="action">manageBuildGroup.php?projectid=<xsl:value-of select="/cdash/project/id"/></xsl:attribute>
     
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
     <input name="newname" type="text" id="newname" size="20"/><input type="submit" name="rename" value="Rename"/>
     </xsl:if>
     </td><td>
     <xsl:if test="name!='Nightly' and name!='Experimental' and name !='Continuous'"> <!-- cannot delete Nightly/Continuous/Experimental -->
     <input type="submit" name="deleteGroup" value="Delete Group" onclick="return confirmDelete()"/>
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
    <td><div align="right">Name:</div></td>
    <td><input name="name" type="text" id="name" size="40"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="createGroup" value="Create Group"/><br/><br/></td>
  </tr>
    <tr>
    <td><div align="right"></div></td>
    <td  bgcolor="#DDDDDD"><strong>Global Move</strong></td>
  </tr>
  <tr>
    <td width="10%"><div align="right">Show:</div></td>
    <td width="90%" ><select onchange="location = 'manageBuildGroup.php?projectid='+projectSelection.value+'&amp;show='+this.options[this.selectedIndex].value;"  name="globalMoveSelectionType">
        <option><xsl:attribute name="value">0</xsl:attribute>All</option>
       <xsl:for-each select="cdash/project/group">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
    </td>
  </tr>
    <tr>
    <td><div align="right"></div></td>
    <td>
     <select name="movebuilds[]" size="15" multiple="true" id="movebuilds">
        <xsl:for-each select="cdash/currentbuild">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
     </select>
    <br/>
    Move to group: (select a group even if you want only expected)
    <select name="groupSelection">
        <option>
        <xsl:attribute name="value">0</xsl:attribute>
        Choose...
        </option>
        
        <xsl:for-each select="cdash/project/group">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
    <br/>
    <input name="expectedMove" type="checkbox" value="1"/> expected
    <br/>
    <input type="submit" name="globalMove" value="Move selected build to group"/>
    </td>
  </tr>
  </xsl:if>
  
</table>


</form>

<br/>
</xsl:otherwise>
</xsl:choose>

<br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
