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
<font color="#ffffff"><h2>CDash on <xsl:value-of select="cdash/hostname"/></h2>
<h3><xsl:value-of select="cdash/date"/></h3></font><br/>
</div>
</td></tr><tr><td></td><td>
<!-- Menu -->
<ul id="Nav" class="nav">
   <li>
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
      </li>
</ul>
</td>
</tr>
</table>

<br/>

<table>
<tr>
<td width="95"></td>
<td>
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
</td>
</tr>  
</table>


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
