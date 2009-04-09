<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>

    <xsl:template name="headscripts" match="/">

    <xsl:comment><![CDATA[[if IE]>
    <script language="javascript" type="text/javascript" src="javascript/excanvas.js">
    </script>
    <![endif]]]></xsl:comment>
    
    <link rel="shortcut icon" href="favicon.ico"/>

    <!-- Include JQuery -->
    <script src="javascript/jquery.js" type="text/javascript" charset="utf-8"></script>  
    <script src="javascript/jquery.flot.js" type="text/javascript" charset="utf-8"></script>   
    <script src="javascript/tooltip.js" type="text/javascript" charset="utf-8"></script>   
     
    <!-- Include Menu JavaScript -->
    <script src='javascript/menu.js' type='text/javascript'></script>
      
    <!-- Include Core Datepicker JavaScript -->
    <script src="javascript/ui.datepicker.js" type="text/javascript" charset="utf-8"></script>  

    <!-- Include Calendar JavaScript -->
    <script src="javascript/cdashmenu.js" type="text/javascript" charset="utf-8"></script>
    
    <!-- Include Core Datepicker Stylesheet -->    
    <link rel="stylesheet" href="javascript/ui.datepicker.css" type="text/css" media="screen" title="core css file" charset="utf-8" />

    <!-- Include the sorting -->
    <script src="javascript/jquery.cookie.js" type="text/javascript" charset="utf-8"></script>  
    <script src="javascript/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/cdashTableSorter.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/jquery.metadata.js" type="text/javascript" charset="utf-8"></script>
    
    <!-- Include jtooltip -->
    <script src="javascript/jtip.js" type="text/javascript" charset="utf-8"></script>

   <!-- include jqModal --> 
  <script src="javascript/jqModal.js" type="text/javascript" charset="utf-8"></script>  
  <link type="text/css" rel="stylesheet" media="all" href="javascript/jqModal.css" />

<!-- include sticky table  
  <script src="javascript/tableheader.js" type="text/javascript" charset="utf-8"></script> -->

    </xsl:template>
</xsl:stylesheet>
