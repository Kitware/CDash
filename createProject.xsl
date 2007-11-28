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
<font color="#ffffff"><h2>
<xsl:if test="cdash/edit=1">
CDash - Edit Project
</xsl:if>
<xsl:if test="cdash/edit=0">
CDash - New Project
</xsl:if>
</h2>
<h3>
<xsl:if test="cdash/edit=1">
Editing a project
</xsl:if>
<xsl:if test="cdash/edit=0">
Creating new project
</xsl:if>
</h3></font>
<br/></div>
</td>
</tr>
<tr>
<td></td><td>
</td>
</tr>
</table>

<br/>

<xsl:choose>
 <xsl:when test="cdash/project_created=1">
 The project <b><xsl:value-of select="cdash/project_name"/></b> has been created successfully.<br/>          
 Click here to access the  <a>
 <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/project_name"/></xsl:attribute>
 CDash project page</a>
 </xsl:when>
<xsl:otherwise>
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table>
  <tr>
		  <td width="99"></td>
    <td><div align="right"><strong>Project:</strong></div></td>
    <td><select onchange="location = 'createProject.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
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
		<xsl:if test="cdash/edit=0">
				<tr>
				  <td></td>
						<td><div align="right"><strong>Name:</strong></div></td>
						<td><input name="name" type="text" id="name"/></td>
				</tr>
		</xsl:if>	
  <tr>
		  <td></td>
    <td><div align="right"><strong>Description:</strong></div></td>
    <td><textarea name="description" id="description" cols="40" rows="5">
				<xsl:value-of select="cdash/project/description"/>
				</textarea></td>
  </tr>
  <tr>
		  <td></td>
    <td><div align="right"><strong>Home URL :</strong></div></td>
    <td><input name="homeURL" type="text" id="homeURL" size="50">
				<xsl:attribute name="value">
				<xsl:value-of select="cdash/project/homeurl"/>
				</xsl:attribute>
				</input>
				</td>
  </tr>
  <tr>
		  <td></td>
    <td><div align="right"><strong>CVS URL :</strong></div></td>
    <td><input name="cvsURL" type="text" id="cvsURL" size="50">
					<xsl:attribute name="value">
				<xsl:value-of select="cdash/project/cvsurl"/>
				</xsl:attribute>
				</input>
				</td>
  </tr>
  <tr>
		  <td></td>
    <td><div align="right"><strong>Bug Tracker URL:</strong></div></td>
    <td><input name="bugURL" type="text" id="bugURL" size="50">	
				<xsl:attribute name="value">
				<xsl:value-of select="cdash/project/bugurl"/>
				</xsl:attribute>
				</input></td>
  </tr>
  <tr>
		  <td></td>
    <td><div align="right"><strong>Logo:</strong></div></td>
    <td><input type="file" name="logo" size="40"/></td>
  </tr>
		<xsl:if test="cdash/edit=1">
		<tr>
		  <td></td>
				<td><div align="right"><strong>Current logo:</strong></div></td>
				<td>
				<xsl:if test="cdash/project/imageid=0">
				[none]
				</xsl:if>
				<img border="0">
				<xsl:attribute name="alt"><xsl:value-of select="cdash/dashboard/project/name"/></xsl:attribute>
				<xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/project/imageid"/></xsl:attribute>
				</img>
				</td>
		</tr>
		</xsl:if>
  <tr>
		  <td></td>
    <td><div align="right"><strong>Public Dashboard:</strong></div></td>
    <td><input type="checkbox" name="public" value="1">
				<xsl:if test="cdash/project/public=1">
				<xsl:attribute name="checked"></xsl:attribute>
				</xsl:if>
				</input>
				</td>
  </tr>
  <tr>
		  <td></td>
    <td><div align="right"><strong>Coverage Threshold:</strong></div></td>
    <td><input name="coverageThreshold" type="text" id="coverageThreshold" size="2" value="70">
				<xsl:attribute name="value">
				<xsl:if test="string-length(cdash/project/coveragethreshold)=0">
				  70
				</xsl:if>
				<xsl:value-of select="cdash/project/coveragethreshold"/>
				</xsl:attribute>
				</input>
				</td>
  </tr>
  <tr>
		  <td></td>
    <td><div align="right"><strong>Nightly Start Time:</strong></div></td>
    <td>
				<input name="nightlyTime" type="text" id="nightlyTime" size="20">
				<xsl:attribute name="value">
				<xsl:if test="string-length(cdash/project/nightlytime)=0">
				  00:00:00 EST
				</xsl:if>
				  <xsl:value-of select="cdash/project/nightlytime"/>
				</xsl:attribute>
				</input></td>
  </tr>
  <tr>
		  <td></td>
    <td><div align="right"></div></td>
				<xsl:if test="cdash/edit=0">
      <td><input type="submit" name="Submit" value="Create Project"/></td>
				</xsl:if>
				<xsl:if test="cdash/edit=1">
			  	<td><input type="submit" name="Update" value="Update Project"/><input type="submit" name="Delete" value="Delete Project"/></td>
				</xsl:if>
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
