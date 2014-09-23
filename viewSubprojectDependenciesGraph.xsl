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
          var projname = "<xsl:value-of select="cdash/dashboard/projectname"/>";
        </script>
        <xsl:text disable-output-escaping="yes">
          &lt;script&gt;
            var chart = d3.chart.dependencyedgebundling();
            chart.mouseOvered(mouseOvered).mouseOuted(mouseOuted);
            var dataroot;
            function sort_by_name (a, b) {
              if (a.name &lt; b.name) {
                return -1;
              }
              if (a.name &gt; b.name) {
                return 1;
              }
              return 0;
            }

            function sort_by_id (a, b) {
              if (a.id &lt; b.id) {
                return -1;
              }
              if (a.id &gt; b.id) {
                return 1;
              }
              return 0;
            }
            
            function mouseOvered(d) {
              $('.curGroup').text(d.group).show();
            }

            function mouseOuted(d) {
              $('.curGroup').text("").hide();
            }

            function resetDepView() {
              d3.select('#chart_placeholder svg').remove();
              d3.select('#chart_placeholder')
                .datum(dataroot)
                .call(chart);
            }
            $(function(){
              $('#selectedsort').on("change", function(e) {
                selected = $(this).val();
                if (parseInt(selected) === 1) {
                  dataroot.sort(sort_by_id);
                } else if (parseInt(selected) === 0) {
                  dataroot.sort(sort_by_name);
                }
                resetDepView(dataroot);
              });
              var rooturl = location.host;
              var ajaxlink = "ajax/getsubprojectdependencies.php?project=" + projname;
              d3.json(ajaxlink, function(error, classes) {
                if (error){
                  errormsg = "json error " + error + " data: " + classes;
                  console.log(errormsg);
                  document.write(errormsg);
                  return;
                }
                dataroot = classes;
                dataroot.sort(sort_by_name);
                resetDepView(dataroot);
              });
            });
          &lt;/script&gt;
        </xsl:text>
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
<div style="position:relative; left:20px; overflow:hidden;">
<h3>Subproject Dependencies Graph</h3>
<label for="selectedsort">Sorted by:</label>
<select id="selectedsort">
  <option value="0" selected="selected">subproject name</option>
  <option value="1">subproject id</option>
</select>
<label class="curGroup" style="margin-left:20px; display:None;"></label>
<button onclick="download_svg()" style="float:right; width:200px; margin-right:30px">Export as svg file</button>
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
