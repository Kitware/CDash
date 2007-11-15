<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
				
			<xsl:include href="header.xsl"/>
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
			
							<xsl:call-template name="header"/>
<br/>

<h3>Coverage started on <xsl:value-of select="cdash/coverage/starttime"/></h3>
<table cellpadding="30">
   <tr>
      <td>
         <table border="0" cellpadding="3" cellspacing="1" bgcolor="#0000aa" width="300">
            <tr>

               <th colspan="2" class="table-heading">Coverage Summary</th>
            </tr>
            <tr>
               <td bgcolor="#ffffff" align="left" width="60%"> Total Coverage</td>
               <td align="center" bgcolor="#00aa00"><xsl:value-of select="cdash/coverage/percentcoverage"/>
                  
               </td>
            </tr>
            <tr>

               <td bgcolor="#ffffff" align="left"> Tested lines</td>
               <td bgcolor="#ffffff" align="right"><xsl:value-of select="cdash/coverage/loctested"/></td>
            </tr>
            <tr>
               <td bgcolor="#ffffff" align="left">Untested lines</td>
               <td bgcolor="#ffffff" align="right"><xsl:value-of select="cdash/coverage/locuntested"/></td>

            </tr>
            <tr>
               <td bgcolor="#ffffff" align="left">Files Covered</td>
               <td bgcolor="#ffffff" align="center"><xsl:value-of select="cdash/coverage/totalcovered"/> of <xsl:value-of select="cdash/coverage/totalfiles"/></td>
            </tr>
            <tr>
               <td bgcolor="#ffffff" align="left">Files Satisfactorily Covered</td>

               <td bgcolor="#ffffff" align="right"><xsl:value-of select="cdash/coverage/totalsatisfactorilycovered"/></td>
            </tr>
            <tr>
               <td bgcolor="#ffffff" align="left">Files Unsatisfactorily Covered</td>
               <td bgcolor="#ffffff" align="right"><xsl:value-of select="cdash/coverage/totalunsatisfactorilycovered"/></td>
            </tr>
         </table>

      </td>
      <td valign="Top">
         <table border="0" cellpadding="3" cellspacing="1" bgcolor="#0000aa" width="350">
            <tr class="table-heading">
               <th>Coverage Legend</th>
            </tr>
            <tr>
               <td align="center" bgcolor="#00aa00">
                  Satisfactory coverage            
               </td>
            </tr>
            <tr>
               <td align="center" bgcolor="#ffcc66">
                  Unstatisfactory coverage           
               </td>
            </tr>
            <tr>
               <td align="center" bgcolor="#ff6666">
                  Dangerously low coverage
               </td>
            </tr>
         </table>
      </td>
   </tr>
</table>

<br/>

<table>
   <tr>
      <th></th>
      <th colspan="2">Coverage status 	<xsl:choose>
        <xsl:when test="cdash/coverage/sortby='status'"><img border="0" src="images/DownBlack.gif"/></xsl:when>
          <xsl:otherwise>
										( <a><xsl:attribute name="href">viewCoverage.php?sortby=status&#38;buildid=<xsl:value-of select="cdash/coverage/buildid"/></xsl:attribute>sort by</a> )
										</xsl:otherwise>
      </xsl:choose>
      </th>
   </tr>
   <tr>
      <th>Filename
						<xsl:choose>
          <xsl:when test="cdash/coverage/sortby='filename'"><img border="0" src="images/DownBlack.gif"/></xsl:when>
          <xsl:otherwise>
										( <a><xsl:attribute name="href">viewCoverage.php?sortby=filename&#38;buildid=<xsl:value-of select="cdash/coverage/buildid"/></xsl:attribute>sort by</a> )
										</xsl:otherwise>
      </xsl:choose>
						</th>
						
      <th>Percentage 	
						<xsl:choose>
          <xsl:when test="cdash/coverage/sortby='percentage'"><img border="0" src="images/DownBlack.gif"/></xsl:when>
          <xsl:otherwise>
										( <a><xsl:attribute name="href">viewCoverage.php?sortby=percentage&#38;buildid=<xsl:value-of select="cdash/coverage/buildid"/></xsl:attribute>sort by</a> )
										</xsl:otherwise>
      </xsl:choose>
      </th>
      <th>Lines not covered	
						<xsl:choose>
          <xsl:when test="cdash/coverage/sortby='lines'"><img border="0" src="images/DownBlack.gif"/></xsl:when>
          <xsl:otherwise>
										( <a><xsl:attribute name="href">viewCoverage.php?sortby=lines&#38;buildid=<xsl:value-of select="cdash/coverage/buildid"/></xsl:attribute>sort by</a> )
										</xsl:otherwise>
      </xsl:choose>
      </th>
   </tr>
			
			<xsl:for-each select="cdash/coveragefile">
   <tr>
      <td align="left"><a>
						<xsl:attribute name="href">viewCoverageFile.php?buildid=<xsl:value-of select="/cdash/coverage/buildid"/>&#38;fileid=<xsl:value-of select="fileid"/></xsl:attribute>
						<xsl:value-of select="filename"/>
						</a></td>
      <td align="center">
							<xsl:attribute name="class">
						  <xsl:choose>
          <xsl:when test="percentcoverage > 75">
            normal
												</xsl:when>
									<xsl:when test="percentcoverage > 30">
            warning
												</xsl:when>		
          <xsl:otherwise>
            error
											</xsl:otherwise>
        </xsl:choose>
						</xsl:attribute>
						<xsl:value-of select="percentcoverage"/>%</td>
      <td align="center">
						<xsl:attribute name="class">
						  <xsl:choose>
          <xsl:when test="percentcoverage > 75">
            normal
												</xsl:when>
									<xsl:when test="percentcoverage > 30">
            warning
												</xsl:when>		
          <xsl:otherwise>
            error
											</xsl:otherwise>
        </xsl:choose>
						</xsl:attribute>
						<xsl:value-of select="locuntested"/></td>
   </tr>
		</xsl:for-each>
</table>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
					   </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
