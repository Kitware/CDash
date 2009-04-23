<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    <xsl:include href="headscripts.xsl"/>
  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

<!-- Group footer -->
<xsl:template name="groupfooter" match="/">
</xsl:template>

<!-- Main Header -->
<xsl:template name="header" match="/">
<table width="100%" class="toptable" cellpadding="1" cellspacing="0">
  <tr>
    <td>
  <table width="100%" align="center" cellpadding="0" cellspacing="0" >
  <tr>
    <td height="30" valign="middle">
    <table width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td width="66%" class="paddl">
        <a><xsl:attribute name="href">user.php</xsl:attribute>
        <xsl:choose>
          <xsl:when test="cdash/user/id>0">My CDash</xsl:when>
          <xsl:otherwise>Login</xsl:otherwise>
        </xsl:choose>  
        </a>
        
        <xsl:if test="cdash/user/id>0">
          <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>|<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text><a href="user.php?logout=1">Log Out</a>  
        </xsl:if>
        | <a href="index.php">Dashboards</a>
                  
        </td>
        <td width="34%" class="topdate">
          <span style="float:right">
         <xsl:if test="cdash/dashboard/projectpublic=1">
         <a> 
            <xsl:attribute name="href">rss/SubmissionRSS<xsl:value-of select="cdash/dashboard/projectname"/>.xml</xsl:attribute><img src="images/feed-icon16x16.png" alt="RSS" width="14" height="14" border="0" />
         </a> 
         </xsl:if>
         <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
         </span>
         <xsl:value-of select="cdash/dashboard/datetime"/>
      </td>
      </tr>
    </table>    
    </td>
  </tr>
  <tr>
    <td height="22" class="topline"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">

    <table width="100%" border="0" cellpadding="0" cellspacing="0" >
    <tr>
    <td width="195" height="121" class="topbgleft">
    <xsl:text disable-output-escaping="yes">&amp;nbsp;&amp;nbsp;</xsl:text>
    <a>
    <xsl:attribute name="href">
    <xsl:value-of select="cdash/dashboard/home"/></xsl:attribute>
    <img id="projectlogo" border="0">
    <xsl:attribute name="alt"></xsl:attribute>
    <xsl:choose>
    <xsl:when test="cdash/dashboard/logoid>0">
      <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
     </xsl:when>
    <xsl:otherwise>
     <xsl:attribute name="src">images/cdash.gif</xsl:attribute>
    </xsl:otherwise>
    </xsl:choose>
    </img>
    </a>
    
    </td>
    <td width="425" valign="top" class="insd">
    <div class="insdd">
      <span class="inn1"><xsl:value-of select="cdash/dashboard/projectname"/></span><br />
      <span class="inn2">
      <xsl:choose>
      <xsl:when test="string-length(cdash/subprojectname)>0">
      <xsl:value-of select="cdash/subprojectname"/>
      </xsl:when>
      <xsl:otherwise>
      Dashboard
      </xsl:otherwise>
      </xsl:choose>
      </span>
      </div>
    </td>
    <td height="121" class="insd2"><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>
   </tr>
  </table>

  </td>
    </tr>
  <tr>
    <td align="left" class="topbg2"><table width="100%" border="0" cellpadding="0" cellspacing="0">
 <tr>
  <td align="left" class="bgtm"><ul id="Nav" class="nav">


<xsl:choose>
<xsl:when test="string-length(cdash/menu/back)>0">
<li id="Dartboard">
<a>
<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>
DASHBOARD</a>
</li><li id="Back">
<a>
<xsl:attribute name="href"><xsl:value-of select="cdash/menu/back"/></xsl:attribute>
BACK</a><ul></ul>
</li>
</xsl:when>
<xsl:otherwise>
<li id="Dartboard">
<!-- Back to the main page if not a subpackage otherwise goes back to the list of subprojects -->

<a>
<xsl:attribute name="href">
index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/>
</xsl:attribute>
DASHBOARD</a>
<ul>
<li><a class="submm">
<xsl:attribute name="href">viewChanges.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>Updates</a></li>
<li><a class="submm">
<xsl:attribute name="href">buildOverview.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>
Builds</a></li>
<li><a class="submm">
<xsl:attribute name="href">testOverview.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>
Tests</a></li>
<li><a class="submm">
<xsl:attribute name="href">viewMap.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/><xsl:value-of select="cdash/extraurl"/></xsl:attribute>
Map</a></li>
</ul>
</li>
</xsl:otherwise>
</xsl:choose>
<li><a id="cal" href="#">CALENDAR</a>
<span id="date_now" style="display:none;">
<xsl:value-of select="cdash/dashboard/date"/>
</span></li>

<xsl:if test="string-length(cdash/menu/noprevious)=0">    
<li>
<a>
<xsl:attribute name="href">
<xsl:choose>
  <xsl:when test="string-length(cdash/menu/previous)>0">
    <xsl:value-of select="cdash/menu/previous"/>
  </xsl:when>
  <xsl:otherwise>
  index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/previousdate"/><xsl:value-of select="cdash/extraurl"/>
  </xsl:otherwise>
</xsl:choose>
</xsl:attribute>
  PREVIOUS
  </a></li>
</xsl:if>
  
<li><a><xsl:attribute name="href">
<xsl:choose>
  <xsl:when test="string-length(cdash/menu/current)>0">
    <xsl:value-of select="cdash/menu/current"/>
  </xsl:when>
  <xsl:otherwise>
  index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/><xsl:value-of select="cdash/extraurl"/>
  </xsl:otherwise>
</xsl:choose>
</xsl:attribute>
    CURRENT 
    </a></li>

<xsl:if test="string-length(cdash/menu/nonext)=0">    
<li><a>
<xsl:attribute name="href">
<xsl:choose>
  <xsl:when test="string-length(cdash/menu/next)>0">
    <xsl:value-of select="cdash/menu/next"/>
  </xsl:when>
  <xsl:otherwise>
  index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/nextdate"/><xsl:value-of select="cdash/extraurl"/>
  </xsl:otherwise>
</xsl:choose>
</xsl:attribute>
      NEXT
      </a></li>
</xsl:if>

<li>
<a href="#" id="activem">PROJECT</a><ul>
<li><a class="submm"><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/home"/> </xsl:attribute>Home</a></li>
<li><a class="submm"><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/documentation"/> </xsl:attribute>Doxygen</a></li>
<li><a class="submm"><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/svn"/> </xsl:attribute>CVS</a></li>
<li><a class="submm"><xsl:attribute name="href"><xsl:value-of select="cdash/dashboard/bugtracker"/> </xsl:attribute>Bugs</a></li>
</ul>
</li>
</ul>
</td>
  <td height="28" class="insd3">
<span id="calendar" class="cal"></span>
<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></td>
 </tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>

<input type="hidden" id="projectname">
 <xsl:attribute name="value"><xsl:value-of select="cdash/dashboard/projectname"/>
 </xsl:attribute>
 </input>
</xsl:template>


</xsl:stylesheet>
