<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    
   <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
  <xsl:output method="html" encoding="iso-8859-1"/>
 
    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
          <link rel="shortcut icon" href="favicon.ico"/> 
     <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
       </head>
       <body bgcolor="#ffffff">
 
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
    <xsl:text>&#160;</xsl:text> <img  border="0" alt="" src="images/cdash.gif"/>
    </td>
    <td width="425" valign="top" class="insd">
    <div class="insdd">
      <span class="inn1">CDash</span><br />
      <span class="inn2">Projects</span>
      </div>
    </td>
    <td height="121" class="insd2"><xsl:text>&#160;</xsl:text></td>
   </tr>
  </table>
  </td>
    </tr>
  <tr>
   
  </tr>
</table></td>
  </tr>
</table>

<!-- Main table -->
<br/>

<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<tr class="table-heading1">
  <td colspan="4" align="left" id="nob"><h3>Available Dashboards</h3></td>
</tr>

  <tr class="table-heading">
     <td align="center"><b>Project</b></td>
     <td align="center"><b>Submissions</b></td>
    <!-- <td align="center">Tests</td> -->
  <td align="center"><b>First build</b></td>
     <td align="center" id="nob"><b>Last activity</b></td>
  </tr>

   <xsl:for-each select="cdash/project">
   <tr>
     <xsl:choose>
          <xsl:when test="row=0">
            <xsl:attribute name="class">trodd</xsl:attribute>
           </xsl:when>
          <xsl:otherwise>
           <xsl:attribute name="class">treven</xsl:attribute>
           </xsl:otherwise>
        </xsl:choose>
   <td align="center" >
     <a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="name"/></xsl:attribute>
     <xsl:value-of select="name"/>
     </a></td>
    <td align="center"><xsl:value-of select="nbuilds"/></td>
  <td align="center"><xsl:value-of select="firstbuild"/></td>
    <!-- <th align="center">Tests</th> <td align="right"><xsl:value-of select="ntests"/></td>-->
    <td align="center" id="nob"><xsl:value-of select="lastbuild"/></td>
    </tr>
   </xsl:for-each>
   
   <table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="14" align="left" bgcolor="#888888"></td>
</tr>
</table>

</tbody>
</table>

<br/>
Database size: <b><xsl:value-of select="cdash/database/size"/></b>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
