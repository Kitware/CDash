<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 
   
    <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headerback.xsl"/>  
     
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
      
       <!-- Functions to confirm the remove -->
  <xsl:text disable-output-escaping="yes">
        &lt;script language="javascript" type="text/javascript" &gt;
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
       
<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headerback_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headerback"/>
</xsl:otherwise>
</xsl:choose>    


<xsl:choose>
 <xsl:when test="cdash/group_created=1">
 The group <b><xsl:value-of select="cdash/group_name"/></b> has been created successfully.<br/>          
 Click here to access the  <a>
 <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/project_name"/></xsl:attribute>
project page</a>
 </xsl:when>
<xsl:otherwise>

<xsl:if test="string-length(cdash/warning)>0">
<br/><b><xsl:value-of select="cdash/warning"/></b><br/>
</xsl:if>

<table width="100%"  border="0">
  <tr>
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" >
    <form name="form1" method="post">
    <xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select onchange="location = 'manageCoverage.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option value="0">Choose project</option>
        <xsl:for-each select="cdash/availableproject">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
      </form>
    </td>
  </tr>
  <tr>
    <td width="10%"><div align="right"><strong>Build:</strong></div></td>
    <td width="90%" >
    <form name="form_build" method="post">
    <xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select name="buildSelection">
    <xsl:attribute name="onchange">location='manageCoverage.php?buildid='+this.options[this.selectedIndex].value+'&#38;projectid=<xsl:value-of select="cdash/project/id"/>';</xsl:attribute>
        <option value="0">Choose build</option>
        <xsl:for-each select="cdash/project/build">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
     </form>
    </td>
  </tr>
</table> 
  
<!-- If a project has been selected -->
<xsl:if test="count(cdash/project)>0">
 

<form name="formnewgroup" method="post">
<xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/>&#38;buildid=<xsl:value-of select="cdash/project/buildid"/></xsl:attribute>
<table width="100%"  border="0">
  <tr>
    <td><div align="right"></div></td>
    <td bgcolor="#DDDDDD"><strong>Coverage files</strong></td>
  </tr>
  <tr>
    <td width="10%"></td>
    <td width="90%">
    <table width="100%"  border="0">
    <tr bgcolor="#DDDDDD">
      <td width="40%">Filename</td>
      <td width="20%">Priority</td>
      <td width="20%">Authors</td>
      <td width="20%">Add author</td>
    </tr>
    <xsl:for-each select="cdash/project/file">
    <tr>
       <xsl:choose>
          <xsl:when test="row=0">
            <xsl:attribute name="class">trodd</xsl:attribute>
           </xsl:when>
          <xsl:otherwise>
           <xsl:attribute name="class">treven</xsl:attribute>
           </xsl:otherwise>
        </xsl:choose>
        
      <td><input type="checkbox">
      <xsl:attribute name="name">selectionFiles[<xsl:value-of select="fileid"/>]</xsl:attribute>
      <xsl:attribute name="value"><xsl:value-of select="fullpath"/></xsl:attribute>
      </input>
      <xsl:value-of select="fullpath"/></td> 
      <td>
      <form name="form_priority" method="post">
      <xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="/cdash/project/id"/>&#38;buildid=<xsl:value-of select="/cdash/project/buildid"/></xsl:attribute>
      <select onchange="this.form.submit();" name="prioritySelection">
      <option value="0">Choose Priority</option>
      <option value="1"><xsl:if test="priority=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Low</option>
      <option value="2"><xsl:if test="priority=2"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Medium</option>
      <option value="3"><xsl:if test="priority=3"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>High</option>
      <option value="4"><xsl:if test="priority=4"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Urgent</option>
      </select>
      <input type="hidden" name="fullpath"><xsl:attribute name="value"><xsl:value-of select="fullpath"/></xsl:attribute></input>
      </form>
      </td>
      <td>
      <xsl:for-each select="author">
      <xsl:value-of select="name"/>
      [<a>
      <xsl:attribute name="href">manageCoverage.php?projectid=<xsl:value-of select="/cdash/project/id"/>&amp;buildid=<xsl:value-of select="/cdash/project/buildid"/>&amp;removeuserid=<xsl:value-of select="id"/>&amp;removefileid=<xsl:value-of select="../id"/>
      </xsl:attribute>x</a>]
      </xsl:for-each>
      </td>
      <td>
      <form name="form_add_author" method="post">
      <xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="/cdash/project/id"/>&#38;buildid=<xsl:value-of select="/cdash/project/buildid"/></xsl:attribute>
      <select name="userSelection">
      <option value="0">Choose author</option>
        <xsl:for-each select="/cdash/project/user">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
        <input type="submit" name="addAuthor" value="Add author"/>
        <input type="hidden" name="fullpath"><xsl:attribute name="value"><xsl:value-of select="fullpath"/></xsl:attribute></input>
      </form>
      </td>
    </tr>
    </xsl:for-each>
    </table>
    </td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="removeAuthorsSelected" value="Remove authors from selected files >>"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><select name="userSelectedSelection">
      <option value="0">Choose author</option>
        <xsl:for-each select="/cdash/project/user">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
        <input type="submit" name="addAuthorsSelected" value="Add author to selected files >>"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td>
      <select name="prioritySelectedSelection">
      <option value="0">Choose Priority</option>
      <option value="1"><xsl:if test="priority=1"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Low</option>
      <option value="2"><xsl:if test="priority=2"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Medium</option>
      <option value="3"><xsl:if test="priority=3"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>High</option>
      <option value="4"><xsl:if test="priority=4"><xsl:attribute name="selected">true</xsl:attribute></xsl:if>Urgent</option>
      </select>
      <input type="submit" name="changePrioritySelected" value="Change priority of selected files >>"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="sendEmail" value="Send email to authors"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="assignLastAuthor" value="Assign last author"/> (Assign the last person who touched the file as the author)</td>
  </tr>
   <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="assignAllAuthors" value="Assign all authors"/> (Assign all the persons who touched the file as authors)</td>
  </tr>
</table>
</form>

<form name="formuploadfile" method="post" enctype="multipart/form-data">
<xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/>&#38;buildid=<xsl:value-of select="/cdash/project/buildid"/></xsl:attribute>
<table width="100%" border="0">
<tr>
    <td valign="top" width="10%"><div align="right"><b>Upload file:</b></div></td>
    <td><div align="left">(format: filename:authors1,authors2)</div>
    <input type="file" name="authorsFile"/><input type="submit" name="uploadAuthorsFile" value="Upload authors file"/> </td>
  </tr>
</table>
</form>


</xsl:if>


<br/>
</xsl:otherwise>
</xsl:choose>

<br/>

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
