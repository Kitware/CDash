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

        <link href='//fonts.googleapis.com/css?family=Open+Sans:400,700|Roboto:400,700' rel='stylesheet' type='text/css'/>
        <link href='css/d3.dependencyedgebundling.css' rel='stylesheet' type='text/css'/>
       <xsl:call-template name="headscripts"/>

        <script type="text/javascript" src="javascript/d3.min.js"></script>
        <script type="text/javascript" src="javascript/d3.dependencyedgebundling.js"></script>

        <script>
          $(function(){
            var chart = d3.chart.dependencyedgebundling();
            var rooturl = location.host;
            var projname = "<xsl:value-of select="cdash/dashboard/projectname"/>";
            var ajaxlink = "ajax/getsubprojectdependencies.php?project=" + projname;
            console.log(ajaxlink);
            d3.json(ajaxlink, function(error, classes) {
              if (error){
                errormsg = "json error " + error + " data: " + classes;
                console.log(errormsg);
                document.write(errormsg);
                return;
              }
              d3.select('#chart_placeholder')
                .datum(classes)
                .call(chart);
            });
          });

        </script>
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

<!-- Main -->
<h3>Subproject Dependencies</h3>
<button onclick="download_svg()">Export as svg file</button>
<div style="font-size:10px">
</div>
<div id="chart_placeholder"></div>

<script>
  function download_svg() {
    var e = document.createElement('script');

    if (window.location.protocol === 'https:') {
      e.setAttribute('src', 'https://rawgit.com/NYTimes/svg-crowbar/gh-pages/svg-crowbar.js');
    } 
    else { 
      e.setAttribute('src', 'http://nytimes.github.com/svg-crowbar/svg-crowbar.js'); 
    } 
    e.setAttribute('class', 'svg-crowbar');
    document.body.appendChild(e);
  }
</script>
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
