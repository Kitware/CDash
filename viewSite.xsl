<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
       <xsl:include href="footer.xsl"/>
			 <xsl:include href="headerback.xsl"/> 

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
   		<xsl:call-template name="headerback"/>
<br/>

<!-- Main -->									
<b>OS Name:</b><xsl:if test="string-length(cdash/site/osname)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/osname"/><br/>   
<b>OS Release:</b><xsl:if test="string-length(cdash/site/osrelease)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/osrelease"/><br/>     
<b>OS Version:</b><xsl:if test="string-length(cdash/site/osversion)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/osversion"/><br/>    
<b>OS Platform:</b><xsl:if test="string-length(cdash/site/osplatform)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/osplatform"/><br/>    
<b>Processor Speed:</b><xsl:if test="string-length(cdash/site/processorclockfrequency)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/processorclockfrequency"/><br/>   
<b>64 Bits:</b><xsl:if test="string-length(cdash/site/processoris64bits)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/processoris64bits"/><br/>    
<b>Processor Vendor:</b><xsl:if test="string-length(cdash/site/processorvendor)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/processorvendor"/><br/>    
<b>Processor Vendor ID:</b><xsl:if test="string-length(cdash/site/processorvendorid)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/processorvendorid"/><br/>    
<b>Processor Family ID:</b><xsl:if test="string-length(cdash/site/processorfamilyid)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/processorfamilyid"/><br/>    
<b>Processor Cache Size:</b><xsl:if test="string-length(cdash/site/processorcachesize)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/processorcachesize"/><br/>    
<b>Number of logical CPUs:</b><xsl:if test="string-length(cdash/site/numberlogicalcpus)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/numberlogicalcpus"/><br/>    
<b>Number of physical CPUs:</b><xsl:if test="string-length(cdash/site/numberphysicalcpus)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/numberphysicalcpus"/><br/>    
<b>Number of logical CPU per Physical CPUs:</b><xsl:if test="string-length(cdash/site/logicalprocessorsperphysical)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/logicalprocessorsperphysical"/><br/>   
<b>Total Virtual Memory:</b><xsl:if test="string-length(cdash/site/totalvirtualmemory)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/totalvirtualmemory"/><br/>    
<b>Total Physical Memory:</b><xsl:if test="string-length(cdash/site/totalphysicalmemory)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/totalphysicalmemory"/><br/>    
<b>Description:</b><xsl:if test="string-length(cdash/site/description)=0"> NA</xsl:if> <xsl:value-of select="cdash/site/description"/><br/>    

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
