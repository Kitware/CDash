<xsl:stylesheet
xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:output method="html" />
    <xsl:template match="/">
      <html>
       <head>
  
       <title><xsl:value-of select="cdash/title"/></title>
         <meta name="viewport" content="width=320; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;"/>
        <style type="text/css" media="screen">@import "iphone.css";</style>
         <script type="application/x-javascript" src="jquery-1.1.4.js"></script>
         <script type="application/x-javascript" src="jquery-iphone.js"></script>
         <script type="application/x-javascript" src="iphone.js"></script>     
         </head><body orient="landscape">

    <h1 id="pageTitle">CDash</h1>
    <a href="http://cdash.org/iphone" class="home"></a>
    <a class="showPage button" href="#loginForm">Login</a>
    <a class="showPage title">CDash by Kitware Inc.</a>
    <xsl:if test="cdash/showlogin=1">
      Wrong login or password
    </xsl:if>
    
    <ul id="projects" title="Project" selection="true">
       <xsl:for-each select="cdash/project"> 
        <li style="background: url(iPhoneArrow.png) no-repeat right center;">
           <a class="link">
           <xsl:attribute name="href">project.php?project=<xsl:value-of select="name_encoded"/></xsl:attribute>
           <xsl:value-of select="name"/>
           </a> 
        </li>
   </xsl:for-each>
    </ul>
       
   <form id="loginForm" class="dialog" method="post" action="user.php">
        <fieldset>
            <h1>Login</h1>
            <label class="inside" id="username-label" for="username">Email...</label> 
            <input id="username" name="login" type="text"/>

            <label class="inside" id="password-label" for="password">Password...</label>
            <input id="password" name="passwd" type="password"/>
            
            <input class="submitButton" name="sent" value="Login" type="submit"/>
        </fieldset>
    </form>
    
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
