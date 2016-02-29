<xsl:stylesheet
xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:output method="html" />
    <xsl:template match="/">

    <xsl:if test="cdash/showlogin=1">
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
    <a href="http://cdash.org/mobile" class="home"></a>
    <a class="showPage button" href="#loginForm">Login</a>
    <a class="showPage title">CDash by Kitware Inc.</a>


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
    Use the login button in the menu bar to login into CDash.

        </body>
      </html>
      </xsl:if>
    </xsl:template>
</xsl:stylesheet>
