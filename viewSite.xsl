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
<font color="#ffffff"><h2>CDash - Site Description </h2>
<h3><xsl:value-of select="cdash/site/name"/></h3></font><br/>
</div>
</td></tr><tr><td></td><td>

<!-- Menu -->
<ul id="Nav" class="nav">
  <li>
     <a href="index.php">Home</a>
   </li>
</ul>

</td>
</tr>
</table>

<script type="text/javascript">
  Rounded('rounded', 15, 15,0,0);
</script>

<br/>

<!-- Main -->
<b>Description:</b><xsl:if test="string-length(cdash/site/description)=0"> NA</xsl:if><xsl:value-of select="cdash/site/description"/><br/>       
<b>Processor type:</b><xsl:if test="string-length(cdash/site/processor)=0"> NA</xsl:if><xsl:value-of select="cdash/site/processor"/><br/>    
<b>Number of processor:</b><xsl:if test="string-length(cdash/site/numprocessors)=0"> NA</xsl:if><xsl:value-of select="cdash/site/numprocessors"/><br/>    
<!-- Display the map -->
<xsl:if test="string-length(cdash/site/ip)>0">  
  <b>IP address:</b><xsl:value-of select="cdash/site/ip"/><br/>
  <b>Map:</b><br/>
  <script type="text/javascript">
      <xsl:attribute name="src">http://maps.google.com/maps?file=api&amp;v=2&amp;key=<xsl:value-of select="cdash/dashboard/googlemapkey"/></xsl:attribute>
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
        map.setCenter(new GLatLng(<xsl:value-of select="cdash/site/latitude"/>,<xsl:value-of select="cdash/site/longitude"/>),5);
        map.addControl(new GLargeMapControl());
        var point = new GLatLng(<xsl:value-of select="cdash/site/latitude"/>,<xsl:value-of select="cdash/site/longitude"/>);
        map.addOverlay(createMarker(point,'<xsl:value-of select="cdash/site/name"/>'));
      }
    }
    </script>
   <body onload="load()" onunload="GUnload()">
  <center><div id="map" style="width: 700px; height: 400px"></div></center>
  </body>
</xsl:if>
<br/>

<!-- Site manager -->
<xsl:if test="cdash/user/sitemanager=1">
<a><xsl:attribute name="href">editSite.php?siteid=<xsl:value-of select="cdash/site/id"/></xsl:attribute>
<xsl:if test="cdash/user/siteclaimed=0">[claim this site]</xsl:if><xsl:if test="cdash/user/siteclaimed=1">[edit site description]</xsl:if></a>
<br/>
<br/>
</xsl:if>

<!-- Projects -->
<b>This site belongs to the following projects:</b><br/>
<xsl:for-each select="cdash/project">
<a>
<xsl:attribute name="href">index.php?project=<xsl:value-of select="name"/></xsl:attribute>
<xsl:value-of select="name"/>
</a>
(<xsl:value-of select="submittime"/>)<br/>
</xsl:for-each>
<br/>

<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
