<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
   
   <!-- Include local common files -->
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/>

   
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
        
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
       <body>
 
<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="header_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="header"/>
</xsl:otherwise>
</xsl:choose>

<table border="0" width="100%">
<xsl:if test="cdash/banner">
  <tr bgcolor="#DDDDDD">
  <td align="center" width="100%" colspan="2">
  <b><xsl:value-of select="cdash/banner/text"/></b>
  </td>
  </tr>
  </xsl:if>  
</table>
 
<!-- Main table -->
<br/>

<xsl:if test="string-length(cdash/upgradewarning)>0">
  <p style="color:red"><b><xsl:value-of select="cdash/upgradewarning"/></b></p>
</xsl:if>

<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<tr class="table-heading1">
  <td colspan="5" align="left" class="nob"><h3>SubProjects</h3></td>
</tr>

  <tr class="table-heading">
     <td align="center" width="30%"><b>Project</b></td>
     <td align="center" width="10%"><b>Configure</b></td>
     <td align="center" width="10%"><b>Build</b></td>
     <td align="center" width="10%"><b>Test</b></td>
     <td align="center" width="20%" class="nob"><b>Last submission</b></td>
  </tr>

   <xsl:for-each select="cdash/subproject">
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
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="/cdash/dashboard/projectname"/>&amp;subproject=<xsl:value-of select="name"/>&amp;date=<xsl:value-of select="/cdash/dashboard/date"/></xsl:attribute>
     <xsl:value-of select="name"/>
     </a></td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nconfigurepass &lt; nconfigure">
            error
            </xsl:when>
            <xsl:otherwise>
            <xsl:choose>
            <xsl:when test="nconfigurepass &lt; nconfigure">
            warning
            </xsl:when>
            <xsl:otherwise>
            <xsl:if test="nconfigure > 0">
            normal
            </xsl:if>
            </xsl:otherwise>
            </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>    
    <xsl:value-of select="nconfigurepass"/>/<xsl:value-of select="nconfigure"/>
    </td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="nbuildpass &lt; nbuild">
            error
            </xsl:when>
            <xsl:otherwise>
            <xsl:choose>
            <xsl:when test="nbuildpass &lt; nbuild">
            warning
            </xsl:when>
            <xsl:otherwise>
            <xsl:if test="nbuild > 0">
            normal
            </xsl:if>
            </xsl:otherwise>
            </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:attribute>
    <xsl:value-of select="nbuildpass"/>/<xsl:value-of select="nbuild"/></td>
    <td align="center">
    <xsl:attribute name="class">
        <xsl:choose>
          <xsl:when test="ntestpass &lt; ntest">
            error
            </xsl:when>
            <xsl:otherwise>
            <xsl:choose>
            <xsl:when test="ntestpass &lt; ntest">
            warning
            </xsl:when>
            <xsl:otherwise>
            <xsl:if test="ntest > 0">
            normal
            </xsl:if>
            </xsl:otherwise>
            </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:attribute>
    <xsl:value-of select="ntestpass"/>/<xsl:value-of select="ntest"/></td>
    <td align="center" class="nob"><xsl:value-of select="lastsubmission"/></td>
    </tr>
   </xsl:for-each>

</tbody>
</table>
   
<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td height="1" colspan="14" align="left" bgcolor="#888888"></td>
</tr>
</table>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
<font size="1">Generated in <xsl:value-of select="/cdash/generationtime"/> seconds</font>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
