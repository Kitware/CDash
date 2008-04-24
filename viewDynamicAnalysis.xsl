<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
    
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
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
   
       <xsl:call-template name="header"/>
<br/>

<!-- Main -->
<p xmlns:lxslt="http://xml.apache.org/xslt"><b>Site:</b><xsl:value-of select="cdash/build/site"/> 
</p>
<p xmlns:lxslt="http://xml.apache.org/xslt"><b>Build Name:</b><xsl:value-of select="cdash/build/buildname"/> 
</p>
<table xmlns:lxslt="http://xml.apache.org/xslt" cellspacing="0">
   <tr>
      <th>Name</th>

      <th>Status</th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Memory Leak<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Uninitialized Memory Read<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Potential Memory Leak<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
      <th><xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>Uninitialized Memory Conditional<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text></th>
  </tr>
   
   <xsl:for-each select="cdash/dynamicanalysis">
   <tr align="center">
   <xsl:attribute name="bgcolor"><xsl:value-of select="bgcolor"/></xsl:attribute>
      <td align="left"><a>
      <xsl:attribute name="href">viewDynamicAnalysisFile.php?id=<xsl:value-of select="id"/></xsl:attribute>
      <xsl:value-of select="filename"/>
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
