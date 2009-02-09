<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
     
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
   
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/> 
      
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

<p><b>Site:</b><xsl:value-of select="cdash/build/site"/></p>
<p><b>Build Name:</b><xsl:value-of select="cdash/build/buildname"/></p>  
 <p><b>Build Time:</b><xsl:value-of select="cdash/build/starttime"/></p>    
Found <xsl:value-of select="count(cdash/errors/error)"/><xsl:text>&#x20;</xsl:text><xsl:value-of select="cdash/errortypename"/>s<br/>
<p><a>
<xsl:attribute name="href">viewBuildError.php?type=<xsl:value-of select="cdash/nonerrortype"/>&#38;buildid=<xsl:value-of select="cdash/build/buildid"/></xsl:attribute>
<xsl:value-of select="cdash/nonerrortypename"/>s</a> are here.</p>
<xsl:for-each select="cdash/errors/error">
<hr/>
<xsl:if test="logline">
<h3><a>Build Log line <xsl:value-of select="logline"/></a></h3>
</xsl:if>
<xsl:if test="sourceline>0">
  <br/>
  File: <b><xsl:value-of select="sourcefile"/></b>
  Line: <b><xsl:value-of select="sourceline"/></b><xsl:text>&#x20;</xsl:text>
  <a>
 <xsl:attribute name="href">
  <xsl:value-of select="cvsurl"/>
 </xsl:attribute>
 CVS/SVN</a>
</xsl:if>
<pre><xsl:value-of select="precontext"/></pre>
<pre><xsl:value-of select="text"/></pre>
<pre><xsl:value-of select="postcontext"/></pre>

<xsl:if test="string-length(workingdirectory)>0">
<b>Directory: </b> <xsl:value-of select="workingdirectory"/><br/>
</xsl:if>
<xsl:if test="string-length(arguments)>0">
<b>Arguments: </b> <xsl:value-of select="arguments"/><br/>
</xsl:if>
<xsl:if test="string-length(language)>0">
<b>Language: </b> <xsl:value-of select="language"/><br/>
</xsl:if>
<xsl:if test="string-length(targetname)>0">
<b>Target Name: </b> <xsl:value-of select="targetname"/><br/>
</xsl:if>
<xsl:if test="string-length(outputtype)>0">
<b>Output Type: </b> <xsl:value-of select="outputtype"/><br/>
</xsl:if>
<xsl:if test="string-length(outputfile)>0">
<b>Output File: </b> <xsl:value-of select="outputfile"/><br/>
</xsl:if>
<xsl:if test="string-length(sourcefile)>0">
<b>Source File: </b> <xsl:value-of select="sourcefile"/><br/>
</xsl:if>
<xsl:if test="string-length(stderror)>0">
<b>Standard Error: </b>
<pre><xsl:value-of select="stderror"/></pre>
</xsl:if>
<xsl:if test="string-length(stdoutput)>0">
<b>Standard Output: </b>
<pre><xsl:value-of select="stdoutput"/></pre>
</xsl:if>
<xsl:if test="exitcondition">
<b>Exit Condition: </b><xsl:value-of select="exitcondition"/>
</xsl:if>
</xsl:for-each>
<hr/>
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
