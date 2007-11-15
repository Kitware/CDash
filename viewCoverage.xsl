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
						
						<!-- gcov -->
      <xsl:if test="cdash/coverage/coveragetype='gcov'">
        <th>Lines not covered	
						  <xsl:choose>
          <xsl:when test="cdash/coverage/sortby='lines'"><img border="0" src="images/DownBlack.gif"/></xsl:when>
          <xsl:otherwise>
										( <a><xsl:attribute name="href">viewCoverage.php?sortby=lines&#38;buildid=<xsl:value-of select="cdash/coverage/buildid"/></xsl:attribute>sort by</a> )
										</xsl:otherwise>
        </xsl:choose>
        </th>
					 </xsl:if>	
						
						<!-- bullseye -->
      <xsl:if test="cdash/coverage/coveragetype='bullseye'">
        <th>Branch Points not covered	
						  <xsl:choose>
          <xsl:when test="cdash/coverage/sortby='branches'"><img border="0" src="images/DownBlack.gif"/></xsl:when>
          <xsl:otherwise>
										( <a><xsl:attribute name="href">viewCoverage.php?sortby=branches&#38;buildid=<xsl:value-of select="cdash/coverage/buildid"/></xsl:attribute>sort by</a> )
										</xsl:otherwise>
        </xsl:choose>
        </th>
								<th>Functions not covered	
						  <xsl:choose>
          <xsl:when test="cdash/coverage/sortby='functions'"><img border="0" src="images/DownBlack.gif"/></xsl:when>
          <xsl:otherwise>
										( <a><xsl:attribute name="href">viewCoverage.php?sortby=functions&#38;buildid=<xsl:value-of select="cdash/coverage/buildid"/></xsl:attribute>sort by</a> )
										</xsl:otherwise>
        </xsl:choose>
        </th>
					 </xsl:if>	
						
   </tr>
			
			<xsl:for-each select="cdash/coveragefile">
   <tr>
			<xsl:attribute name="bgcolor"><xsl:value-of select="bgcolor"/></xsl:attribute>
			
      <td align="left">
						<xsl:if test="covered=0">
						<xsl:value-of select="fullpath"/>
						</xsl:if>
						<xsl:if test="covered=1">
						<a>
						<xsl:attribute name="href">viewCoverageFile.php?buildid=<xsl:value-of select="/cdash/coverage/buildid"/>&#38;fileid=<xsl:value-of select="fileid"/></xsl:attribute>
						<xsl:value-of select="fullpath"/>
						</a>
						</xsl:if>
						</td>
      <td align="center">
						<xsl:if test="covered>0">
							<xsl:attribute name="class">
						  <xsl:choose>
          <xsl:when test="coveragemetric &lt; 0.5">
            error
												</xsl:when>
									<xsl:when test="coveragemetric >= 0.7">
            normal
												</xsl:when>		
          <xsl:otherwise>
            warning
											</xsl:otherwise>
        </xsl:choose>
						</xsl:attribute>
						<xsl:value-of select="percentcoverage"/>%
						</xsl:if>
						<xsl:if test="covered=0">
						UNTESTED
						</xsl:if>
						</td>
						
							<!-- gcov -->
      <xsl:if test="/cdash/coverage/coveragetype='gcov'">
							<td align="center">
								<xsl:if test="covered>0">
							<xsl:attribute name="class">
									<xsl:choose>
											<xsl:when test="coveragemetric &lt;  0.5">
													error
													</xsl:when>
										<xsl:when test="coveragemetric >= 0.7">
													normal
													</xsl:when>		
											<xsl:otherwise>
													warning
												</xsl:otherwise>
									</xsl:choose>
							</xsl:attribute>
							<xsl:value-of select="locuntested"/>
						</xsl:if>
							<xsl:if test="covered=0">
							UNTESTED
							</xsl:if>
					</td>
					</xsl:if>
					 
					<!-- bullseye -->
					<xsl:if test="/cdash/coverage/coveragetype='bullseye'">
					<!-- branches -->
					<td align="center">
								<xsl:if test="covered>0">
							<xsl:attribute name="class">
									<xsl:choose>
											<xsl:when test="coveragemetric &lt;  0.5">
													error
													</xsl:when>
										<xsl:when test="coveragemetric >= 0.7">
													normal
													</xsl:when>		
											<xsl:otherwise>
													warning
												</xsl:otherwise>
									</xsl:choose>
							</xsl:attribute>
							<xsl:value-of select="branchesuntested"/>
						</xsl:if>
							<xsl:if test="covered=0">
							UNTESTED
							</xsl:if>
					</td>

      <!-- functions -->
							<td align="center">
								<xsl:if test="covered>0">
							<xsl:attribute name="class">
									<xsl:choose>
											<xsl:when test="coveragemetric &lt;  0.5">
													error
													</xsl:when>
										<xsl:when test="coveragemetric >= 0.7">
													normal
													</xsl:when>		
											<xsl:otherwise>
													warning
												</xsl:otherwise>
									</xsl:choose>
							</xsl:attribute>
							<xsl:value-of select="functionsuntested"/>
						</xsl:if>
							<xsl:if test="covered=0">
							UNTESTED
							</xsl:if>
					</td>
					</xsl:if>

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
