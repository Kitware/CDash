<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
       <xsl:include href="footer.xsl"/>
 <!-- HEADER -->   
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
<font color="#ffffff"><h2>CDash - Sites Map for <xsl:value-of select="cdash/dashboard/projectname"/></h2>
<h3>Where are the builds located?</h3></font>
</td></tr><tr><td></td><td>
<div id="navigator">
<table border="0" cellpadding="0" cellspacing="0">
<tr>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">index.php</xsl:attribute>Home</a>
</p>
</td>

<td align="center">
<p class="hoverbutton">
<a><xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/dashboard/projectname"/></xsl:attribute>Back</a>
</p>
</td>

<td align="center" width="5">
<p></p>
</td>

</tr>
</table>
</div>
</td>
</tr>
</table>
 
<br/>

<!-- Display the map --> 
  <script type="text/javascript">
      <xsl:attribute name="src">http://maps.google.com/maps?file=api&amp;v=2&amp;key=<xsl:value-of select="cdash/site/googlemapkey"/></xsl:attribute>
   </script>
    <script type="text/javascript">
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
        map.setCenter(new GLatLng(37.4419, -122.1419), 3);
        map.addControl(new GLargeMapControl());
        <xsl:for-each select="cdash/site">
        var point = new GLatLng(<xsl:value-of select="latitude"/>,<xsl:value-of select="longitude"/>);
        map.addOverlay(createMarker(point,'<xsl:value-of select="name"/>'));
        </xsl:for-each>
      }
    }
    </script>
   <body onload="load()" onunload="GUnload()">
  <center><div id="map" style="width: 700px; height: 400px"></div></center>
  </body>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
