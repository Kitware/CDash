<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
        
   <!--  <xsl:output method="html"/> -->
    <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
    <xsl:template name="header" match="/">

    <link rel="shortcut icon" href="favicon.ico"/>
    <xsl:comment><![CDATA[[if IE]>
    <script language="javascript" type="text/javascript" src="javascript/excanvas.js">
    </script>
    <![endif]]]></xsl:comment>

    <!-- Include JQuery -->
    <script src="javascript/jquery.js" type="text/javascript" charset="utf-8"></script>  
    <script src="javascript/jquery.flot.js" type="text/javascript" charset="utf-8"></script>   
     
   <!-- Include Menu JavaScript -->
   <script src='javascript/menu.js' type='text/javascript'></script>
      
    <!-- Include Core Datepicker JavaScript -->
    <script src="javascript/ui.datepicker.js" type="text/javascript" charset="utf-8"></script>  

    <!-- Include Calendar JavaScript -->
    <script src="javascript/cdashmenu.js" type="text/javascript" charset="utf-8"></script>
    
      <!-- Include Core Datepicker Stylesheet -->    
    <link rel="stylesheet" href="javascript/ui.datepicker.css" type="text/css" media="screen" title="core css file" charset="utf-8" />

<input type="hidden" id="projectname">
<xsl:attribute name="value"><xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
</input>

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
          <xsl:when test="cdash/user/id>0">
            My CDash  
          </xsl:when>
          <xsl:otherwise>
             Login
           </xsl:otherwise>
        </xsl:choose>  
        </a>
        
        <xsl:if test="cdash/user/id>0">
          <xsl:text>&#160;</xsl:text>|<xsl:text>&#160;</xsl:text><a href="user.php?logout=1">Log Out</a>  
        </xsl:if>
        
        </td>
        <td width="34%" class="topdate">
          <span style="float:right">
          <a> 
            <xsl:attribute name="href">rss/SubmissionRSS<xsl:value-of select="cdash/dashboard/projectname"/>.xml</xsl:attribute><img src="images/feed-icon16x16.png" alt="RSS" width="14" height="14" border="0" />
         </a> 
         <xsl:text>&#160;</xsl:text>
         </span>
         <xsl:value-of select="cdash/dashboard/datetime"/>
      </td>
      </tr>
    </table>    
    </td>
  </tr>
  <tr>
    <td height="22" class="topline"><xsl:text>&#160;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">

    <table width="100%" height="121" border="0" cellpadding="0" cellspacing="0" >
    <tr>
    <td width="195" height="121" class="topbgleft">
     <!-- <img src="images/top_01.jpg" width="195" height="121" alt=""/> -->
     <img  border="0">
    <xsl:attribute name="alt"></xsl:attribute>
    <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
    </img>

    </td>
    <td width="425" valign="top" class="insd">
    <div class="insdd">
      <span class="inn1"><xsl:value-of select="cdash/dashboard/projectname"/></span><br />
      <span class="inn2">Dashboard</span>
      </div>
    </td>
    <td height="121" class="insd2"><xsl:text>&#160;</xsl:text></td>
   </tr>
  </table>

  </td>
    </tr>
  <tr>
    <td align="left" class="topbg2"><table width="100%" height="28" border="0" cellpadding="0" cellspacing="0">
 <tr>
  <td width="631" align="left" class="bgtm"><ul id="Nav" class="nav">
<li id="Dartboard">
<a href="index.php">DASHBOARD</a><ul>
<li><a id="submm">
<xsl:attribute name="href">viewChanges.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>Updates</a></li>
<li><a id="submm">
<xsl:attribute name="href">testOverview.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>
Tests</a></li>
<li><a id="submm">
<xsl:attribute name="href">viewMap.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#38;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>
Map</a></li>
</ul>
</li>
<li><a id="cal" href="#">CALENDAR</a></li>
<li><a>
<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/previousdate"/></xsl:attribute>
  
  PREVIOUS
  </a></li>
<li><a><xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>
    CURRENT 
    </a></li>
<li><a vertical-align="middle">
<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/nextdate"/></xsl:attribute>
      NEXT
      </a></li>
<li>
<a href="#" id="activem">PROJECT</a><ul>
<li><a id="submm"><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/home"/> </xsl:attribute>Home</a></li>
<li><a id="submm"><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/documentation"/> </xsl:attribute>Doxygen</a></li>
<li><a id="submm"><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/svn"/> </xsl:attribute>CVS</a></li>
<li><a id="submm"><xsl:attribute name="href">http://<xsl:value-of select="cdash/dashboard/bugtracker"/> </xsl:attribute>Bugs</a></li>
</ul>
</li>
</ul>
</td>
<span id="calendar" class="cal"></span>
  <td height="28" class="insd3"><xsl:text>&#160;</xsl:text></td>
 </tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>

    </xsl:template>
</xsl:stylesheet>
