<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

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

<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headerback_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headerback"/>
</xsl:otherwise>
</xsl:choose>

<br/>

<xsl:if test="string-length(cdash/warning)>0">
<b><xsl:value-of select="cdash/warning"/></b><br/>
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
    <td bgcolor="#CCCCCC"><strong>List of current sites for <xsl:value-of select="cdash/project/name"/></strong></td>
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
    <td bgcolor="#CCCCCC"><strong>Site specifications for <xsl:value-of select="cdash/user/site/name"/></strong></td>
  </tr>
   <tr>
    <td></td>
    <td bgcolor="#FFFFFF"></td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Name:</strong> <input name="site_name" type="text" size="20">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/name"/></xsl:attribute>
    </input>
    <strong> (Make sure the name of the build matches CTest buildname otherwise a new site will be created)</strong>
    </td>
  </tr>
 <tr>
    <td bgcolor="#EEEEEE"><strong>64 bits:</strong> <input name="site_processoris64bits" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processoris64bits"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Processor vendor:</strong> <input name="site_processorvendor" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processorvendor"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Processor vendor ID:</strong> <input name="site_processorvendorid" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processorvendorid"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Processor family ID:</strong> <input name="site_processorfamilyid" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processorfamilyid"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Processor model ID:</strong> <input name="site_processormodelid" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processormodelid"/></xsl:attribute>
    </input>
    </td>
  </tr>

  <tr>
    <td bgcolor="#EEEEEE"><strong>Processor cache size:</strong> <input name="site_processorcachesize" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processorcachesize"/></xsl:attribute>
    </input>
    </td>
  </tr>
    <tr>
    <td bgcolor="#EEEEEE"><strong>CPU Speed (MHz):</strong> <input name="site_processorclockfrequency" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/processorclockfrequency"/></xsl:attribute>
    </input>
    </td>
   </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Number of logical CPUs:</strong> <input name="site_numberlogicalcpus" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/numberlogicalcpus"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Number of physical CPUs:</strong> <input name="site_numberphysicalcpus" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/numberphysicalcpus"/></xsl:attribute>
    </input>
    </td>
  </tr>
 <tr>
    <td bgcolor="#EEEEEE"><strong>Logical Processor per Physical:</strong> <input name="site_logicalprocessorsperphysical" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/logicalprocessorsperphysical"/></xsl:attribute>
    </input>
    </td>
   </tr>
   <tr>
    <td bgcolor="#EEEEEE"><strong>Total virtual memory (MB):</strong> <input name="site_totalvirtualmemory" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/totalvirtualmemory"/></xsl:attribute>
    </input>
    </td>
  </tr>
    <tr>
    <td bgcolor="#EEEEEE"><strong>Total physical memory (MB):</strong> <input name="site_totalphysicalmemory" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/totalphysicalmemory"/></xsl:attribute>
    </input>
    </td>
   </tr>
 <tr>
    <td bgcolor="#EEEEEE"><strong>Description:</strong> <input name="site_description" type="text" size="50">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/description"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>IP address:</strong> <input name="site_ip" type="text" size="30">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/ip"/></xsl:attribute>
    </input>
    <input type="submit" name="geolocation" value="Retrieve geolocation"/>
    </td>
  </tr>
   <tr>
    <td bgcolor="#EEEEEE"><strong>Latitude:</strong> <input name="site_latitude" type="text" size="30">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/latitude"/></xsl:attribute>
    </input>
    </td>
  </tr>
    <tr>
    <td bgcolor="#EEEEEE"><strong>Longitude:</strong> <input name="site_longitude" type="text" size="30">
    <xsl:attribute name="value"><xsl:value-of select="cdash/user/site/longitude"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td bgcolor="#EEEEEE"><strong>Force new description revision:</strong>
  <input name="newdescription_revision" type="checkbox"/>
     (check this box only if the system has been upgraded, i.e memory upgrade,...)
    </td>
  </tr>
 <tr>
    <td bgcolor="#EEEEEE"><strong>Mark site as temporary out of order:</strong>
    <input name="outoforder" value="1" type="checkbox">
    <xsl:if test="cdash/user/site/outoforder=1">
      <xsl:attribute name="checked"></xsl:attribute>
    </xsl:if>
    </input>
    </td>
  </tr>


  <tr>
    <td bgcolor="#FFFFFF">
     <input type="hidden" name="claimsiteid"><xsl:attribute name="value"><xsl:value-of select="cdash/user/site/id"/></xsl:attribute></input>
     <input type="submit" name="updatesite" value="Update Site >>"/>
     <input type="submit" name="unclaimsite" value="Unclaim Site >>"/>
    </td>
  </tr>
</table>
</form>
</xsl:if>

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
