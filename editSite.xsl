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
<font color="#ffffff"><h2>CDash - Site Management</h2>
<h3>Managing sites you maintain</h3></font>
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

<br/>

<xsl:if test="string-length(cdash/warning)>0">
<xsl:value-of select="cdash/warning"/>
</xsl:if>


<!-- List of sites -->
<xsl:if test="count(cdash/site)>0">
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%"  border="0">
  <tr>
    <td></td>
    <td></td>
  </tr>
  <tr>
    <td width="98"></td>
    <td bgcolor="#CCCCCC"><strong>List of claimed sites for <xsl:value-of select="cdash/project/name"/></strong></td>
  </tr>
		<xsl:for-each select="cdash/site">
   <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><input type="checkbox" value="1">
				<xsl:attribute name="name">checkedsites[<xsl:value-of select="id"/>]</xsl:attribute>
				<xsl:if test="claimed=1">
				<xsl:attribute name="checked"></xsl:attribute>
				</xsl:if>
				</input>
				<input type="hidden" name="availablesites[]">
				<xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
				</input>
				<xsl:value-of select="name"/>
				</td>
  </tr>
		</xsl:for-each>
		
		 <tr>
    <td></td>
    <td bgcolor="#FFFFFF"></td>
  </tr>	
		<tr>
    <td></td>
				<td bgcolor="#FFFFFF">
				 <input type="submit" name="claimsites" value="Update claimed sites"/>
				</td>
  </tr>	
</table>
</form>
</xsl:if>
<br/>

<!-- User is claiming a specific site -->
<xsl:if test="cdash/user/siteclaimed=0">
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%"  border="0">
  <tr>
    <td></td>
    <td></td>
  </tr>
  <tr>
    <td width="98"></td>
    <td bgcolor="#CCCCCC"><strong>Would you like to claim <xsl:value-of select="cdash/user/site/name"/> as a site you are managing?</strong></td>
  </tr>
		 <tr>
    <td></td>
    <td bgcolor="#FFFFFF"></td>
  </tr>	
		<tr>
    <td></td>
				<td bgcolor="#FFFFFF">
				 <input type="hidden" name="claimsiteid"><xsl:attribute name="value"><xsl:value-of select="cdash/user/site/id"/></xsl:attribute></input>
				 <input type="submit" name="claimsite" value="Claim Site"/>
				</td>
  </tr>	
</table>
</form>
</xsl:if>

<!-- The site is claimed we edit it -->
<xsl:if test="cdash/user/siteclaimed=1">
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%" border="0">
  <tr>
    <td></td>
    <td></td>
  </tr>
  <tr>
    <td width="98"></td>
    <td bgcolor="#CCCCCC"><strong>Site specifications for <xsl:value-of select="cdash/user/site/name"/></strong></td>
  </tr>
		 <tr>
    <td></td>
    <td bgcolor="#FFFFFF"></td>
  </tr>	
		<tr>
    <td></td>
    <td bgcolor="#EEEEEE"><strong>Name:</strong> <input name="site_name" type="text" size="20">
				<xsl:attribute name="value"><xsl:value-of select="cdash/user/site/name"/></xsl:attribute>
				</input>
				<strong> (Make sure the name of the build matches CTest buildname otherwise a new site will be created)</strong>
				</td>
  </tr>
		<tr>
    <td></td>
    <td bgcolor="#EEEEEE"><strong>Description:</strong> <input name="site_description" type="text" size="50">
				<xsl:attribute name="value"><xsl:value-of select="cdash/user/site/description"/></xsl:attribute>
				</input>
				</td>
  </tr>
	 <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><strong>Processor Type:</strong> <input name="site_processor" type="text" size="30">
				<xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processor"/></xsl:attribute>
				</input>
				</td>
  </tr>
		 <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><strong>Number of processors:</strong> <input name="site_nprocessors" type="text" size="2">
				<xsl:attribute name="value"><xsl:value-of select="cdash/user/site/nprocessors"/></xsl:attribute>
				</input>
				</td>
  </tr>
	 <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><strong>IP address:</strong> <input name="site_ip" type="text" size="30">
				<xsl:attribute name="value"><xsl:value-of select="cdash/user/site/ip"/></xsl:attribute>
				<input type="submit" name="geolocation" value="Retrieve geolocation"/>
				</input>
				</td>
  </tr>	
		 <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><strong>Latitude:</strong> <input name="site_latitude" type="text" size="30">
				<xsl:attribute name="value"><xsl:value-of select="cdash/user/site/latitude"/></xsl:attribute>
				</input>
				</td>
  </tr>
			 <tr>
    <td></td>
    <td bgcolor="#EEEEEE"><strong>Longitude:</strong> <input name="site_longitude" type="text" size="30">
				<xsl:attribute name="value"><xsl:value-of select="cdash/user/site/longitude"/></xsl:attribute>
				</input>
				</td>
  </tr>	
		<tr>
    <td></td>
				<td bgcolor="#FFFFFF">
				 <input type="hidden" name="claimsiteid"><xsl:attribute name="value"><xsl:value-of select="cdash/user/site/id"/></xsl:attribute></input>
				 <input type="submit" name="updatesite" value="Update Site"/>
				</td>
  </tr>	
</table>
</form>
</xsl:if>



<br/>

<!-- Rounding script -->
<script type="text/javascript">
  Rounded('rounded', 15, 15,0,0);
</script>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
