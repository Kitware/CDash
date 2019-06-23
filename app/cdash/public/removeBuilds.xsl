<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    <xsl:include href="headerback.xsl"/>

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
       </head>
       <body bgcolor="#ffffff">
            <xsl:call-template name="headerback"/>
<br/>

<xsl:if test="string-length(cdash/alert)>0">
<b><xsl:value-of select="cdash/alert"/></b>
<br/><br/>
</xsl:if>

Project:
<select onchange="location = 'removeBuilds.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option>
        <xsl:attribute name="value">0</xsl:attribute>
        Choose...
        </option>

        <xsl:for-each select="cdash/availableproject">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>

<br/><br/>
Remove builds in this date range.
<br/><br/>

<form name="form1" enctype="multipart/form-data" method="post" action="">
From:
<input>
<xsl:attribute name="name">monthFrom</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/monthFrom"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">dayFrom</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/dayFrom"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">yearFrom</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">4</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/yearFrom"/></xsl:attribute>
</input>
To:
<input>
<xsl:attribute name="name">monthTo</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/monthTo"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">dayTo</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">2</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/dayTo"/></xsl:attribute>
</input>
<input>
<xsl:attribute name="name">yearTo</xsl:attribute>
<xsl:attribute name="type">text</xsl:attribute>
<xsl:attribute name="size">4</xsl:attribute>
<xsl:attribute name="value"><xsl:value-of select="/cdash/yearTo"/></xsl:attribute>
</input>
<br/>
<br/>
<input type="submit" name="Submit" value="Remove Builds >>"/>
</form>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
             </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
