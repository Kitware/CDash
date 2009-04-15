<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
   <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/header.xsl"/> 
   
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
       <xsl:call-template name="headscripts"/>          
       </head>
       <body bgcolor="#ffffff">
       
<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="header_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="header"/>
</xsl:otherwise>
</xsl:choose>

<br/>

<!-- Main -->
<br/>
<h3>Dynamic analysis started on <xsl:value-of select="cdash/build/buildtime"/></h3>
<table border="0">
<tr><td align="right"><b>Site Name:</b></td><td><xsl:value-of select="cdash/build/site"/></td></tr>
<tr><td align="right"><b>Build Name:</b></td><td><xsl:value-of select="cdash/build/buildname"/></td></tr>
</table>

<table xmlns:lxslt="http://xml.apache.org/xslt" cellspacing="0">
   <tr>
      <th>Name</th>

      <th>Status</th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Memory Leak<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Uninitialized Memory Read<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Potential Memory Leak<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Uninitialized Memory Conditional<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Mismatched Deallocate<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Freeing Invalid Memory<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Invalid Pointer Read<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Invalid Pointer Write<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      
      <th>Labels</th>
  </tr>

   <xsl:for-each select="cdash/dynamicanalysis">
   <tr align="center">
   <xsl:attribute name="bgcolor"><xsl:value-of select="bgcolor"/></xsl:attribute>
      <td align="left"><a>
      <xsl:attribute name="href">viewDynamicAnalysisFile.php?id=<xsl:value-of select="id"/></xsl:attribute>
      <xsl:value-of select="name"/>
      </a></td>
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="status='Passed'">
            normal
          </xsl:when>
          <xsl:otherwise>
            warning
           </xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="status"/></td>
      <td></td>
      <!-- Memory Leak -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(Memory_Leak)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="Memory_Leak"/> 
      </td>
      <!-- UMR -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(Uninitialized_Memory_Read)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="Uninitialized_Memory_Read"/>
      </td>
      <!-- PML -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(Potential_Memory_Leak)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="Potential_Memory_Leak"/>
      </td>
      <!--UMC -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(Uninitialized_Memory_Conditional)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="Uninitialized_Memory_Conditional"/>
      </td>
      <!-- Mismatched deallocation -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(Mismatched_deallocation)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="Mismatched_deallocation"/>
      </td>
      <!--FIM -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(FIM)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="FIM"/>
      </td>
      <!-- IPR -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(IPR)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="IPR"/>
      </td>
      <!-- IPW -->
      <td>
      <xsl:attribute name="class">
       <xsl:choose>
          <xsl:when test="count(IPW)>0">
            warning
          </xsl:when>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="IPW"/>
      </td>
      <!-- Labels -->
      <td>
        <xsl:for-each select="labels/label">
          <xsl:if test="position() > 1">,
          <xsl:text disable-output-escaping="yes"> </xsl:text>
          </xsl:if>
          <nobr><xsl:value-of select="."/></nobr>
        </xsl:for-each>
      </td>

    </tr>
   </xsl:for-each>
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
