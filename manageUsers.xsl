<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 
     
   <!-- Include local common files -->
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
   
        <!-- Include project roles -->
        <script src="javascript/jquery.js"></script>
        <script src="javascript/cdashManageUsers.js"></script>
        <!-- Functions to confirm the email -->
        <xsl:text disable-output-escaping="yes">
              &lt;script language="JavaScript"&gt;
              function confirmRemove() {
                 if (window.confirm("Are you sure you want to remove this user from the database?")){
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

<br/>

<xsl:if test="string-length(cdash/warning)>0">
<div style="color: green;"><xsl:value-of select="cdash/warning"/></div><br/>
</xsl:if>

<div style="color: red;"><xsl:value-of select="cdash/error" /></div> 


  <table width="100%"  border="0">  
  <tr>
    <td><div align="right"></div></td>
    <td  bgcolor="#DDDDDD"><strong>Search for already registered users</strong></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td>
    <xsl:choose>
    <xsl:when test="/cdash/fullemail">
      <i>type the full email address of the user to add</i>
    </xsl:when>
    <xsl:otherwise>
      <i>start typing a name or email address (% to display all users)</i>
    </xsl:otherwise>
    </xsl:choose>
    </td>
  </tr>
  <tr>
    <td><div align="right">Search:</div></td>
    <td><input name="search" type="text" id="search" size="40">
    <xsl:attribute name="value"><xsl:value-of select="cdash/search"/></xsl:attribute>
    </input>
    </td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><div name="newuser" id="newuser"></div></td>
  </tr>
</table>  
  
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
