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
       </head>
       <body bgcolor="#ffffff">
   
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td bgcolor="#6699cc" valign="top" width="100%">
<font color="#ffffff"><h2>CDash on <xsl:value-of select="cdash/hostname"/></h2>
<h3><xsl:value-of select="cdash/date"/></h3></font>
</td></tr><tr><td></td><td>
<div id="navigator">
</div>
</td>
</tr>
</table>

<br/>

<table class="dart">
<tbody>
<tr class="table-heading">
  <th colspan="4" align="left">Available Dashboards</th>
</tr>

  <tr class="table-heading">
     <th align="center">Project</th>
     <th align="center">Submissions</th>
    <!-- <th align="center">Tests</th> -->
     <th align="center">Last activity</th>
  </tr>

   <xsl:for-each select="cdash/project">
   <tr>
     <xsl:choose>
          <xsl:when test="row=0">
            <xsl:attribute name="class">tr-odd</xsl:attribute>
           </xsl:when>
          <xsl:otherwise>
           <xsl:attribute name="class">tr-even</xsl:attribute>
           </xsl:otherwise>
        </xsl:choose>
   <td>
     <a>
     <xsl:attribute name="href">index.php?project=<xsl:value-of select="name"/></xsl:attribute>
     <xsl:value-of select="name"/>
     </a></td>
    <td align="right"><xsl:value-of select="nbuilds"/></td>
    <!-- <th align="center">Tests</th> <td align="right"><xsl:value-of select="ntests"/></td>-->
    <td align="right"><xsl:value-of select="lastbuild"/></td>
    </tr>
   </xsl:for-each>
  
</tbody>
</table>  

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
