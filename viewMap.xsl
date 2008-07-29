<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 
   
   <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headerback.xsl"/> 
  
 <!-- HEADER -->  
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
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
   <body onload="load()" onunload="GUnload()">

<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headerback_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headerback"/>
</xsl:otherwise>
</xsl:choose>

<!--
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td bgcolor="#6699cc" valign="top" width="100%">
<font color="#ffffff"><h2>CDash - Sites Map for <xsl:value-of select="cdash/dashboard/projectname"/></h2>
<h3>Where are the builds located?</h3></font>
</td></tr><tr><td></td><td>
<ul id="Nav" class="nav">
   <li>
        <a><xsl:attribute name="href">index.php</xsl:attribute>Home</a>
      </li>
      <li>
        <a><xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/>&#x26;date=<xsl:value-of select="cdash/dashboard/date"/></xsl:attribute>Project</a>
      </li>  
</ul>
</td>
</tr>
</table>
-->

<br/>

<!-- Display the map --> 
  <script type="text/javascript">
      <xsl:attribute name="src">http://maps.google.com/maps?file=api&amp;v=2&amp;key=<xsl:value-of select="cdash/dashboard/googlemapkey"/></xsl:attribute>
   </script>
  <xsl:text disable-output-escaping="yes">
    &lt;script type="text/javascript"&gt;
      // Creates a marker whose info window displays the letter corresponding
      // to the given index.
      function createMarker(point,title) 
        {     
        var marker = new GMarker(point);
        GEvent.addListener(marker, "click", function() 
          {
          marker.openInfoWindowHtml(title);
          });
        return marker;
      }

    function load() {
      if (GBrowserIsCompatible()) {
        var map = new GMap2(document.getElementById("map"));
        map.setCenter(new GLatLng(37.4419, -30.00), 2);
        map.addControl(new GLargeMapControl());
  </xsl:text>
        <xsl:for-each select="cdash/site">
        <xsl:if test="string-length(latitude)>0">
        var point = new GLatLng(<xsl:value-of select="latitude"/>,<xsl:value-of select="longitude"/>);
        map.addOverlay(createMarker(point,'<xsl:value-of select="name"/>'));
        </xsl:if>
        </xsl:for-each><xsl:text disable-output-escaping="yes">
      }
    }
    &lt;/script&gt;
    </xsl:text>
  <center><div id="map" style="width: 700px; height: 400px"></div></center>

<xsl:text disable-output-escaping="yes">
&lt;script type="text/javascript"&gt;
  Rounded('rounded', 15, 15,0,0);
&lt;/script&gt;
</xsl:text>

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
