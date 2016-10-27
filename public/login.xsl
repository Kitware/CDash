<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="local/footer.xsl"/>
  <xsl:include href="headscripts.xsl"/>
  <xsl:include href="local/headscripts.xsl"/>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
  doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

  <xsl:template match="/">
    <html>
      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href">
            <xsl:value-of select="cdash/cssfile"/>
          </xsl:attribute>
        </link>
        <xsl:call-template name="headscripts"/>

        <xsl:if test="/cdash/oauth2">
          <script>
            var CLIENTID = '<xsl:value-of select="cdash/oauth2/client"/>';
            var CDASH_BASE_URL = '<xsl:value-of select="cdash/oauth2/CDASH_BASE_URL"/>';
          </script>
          <script src="js/cdashOauth2.js"></script>
        </xsl:if>

      </head>
      <body>

        <div id="header">
          <div id="headertop">
            <div id="topmenu">
              <a href="index.php">All Dashboards</a>
              <a href="register.php">Register</a>
            </div>
          </div>

          <div id="headerbottom">
            <div id="headerlogo">
              <a>
                <xsl:attribute name="href">
                  <xsl:value-of select="cdash/dashboard/home"/>
                </xsl:attribute>
                <img id="projectlogo" border="0" height="50px">
                  <xsl:attribute name="alt"></xsl:attribute>
                  <xsl:choose>
                    <xsl:when test="cdash/dashboard/logoid>0">
                      <xsl:attribute name="src">displayImage.php?imgid=<xsl:value-of select="cdash/dashboard/logoid"/></xsl:attribute>
                    </xsl:when>
                    <xsl:otherwise>
                      <xsl:attribute name="src">img/cdash.png</xsl:attribute>
                    </xsl:otherwise>
                  </xsl:choose>
                </img>
              </a>
            </div>
            <div id="headername2">
              CDash
              <span id="subheadername">Login</span>
            </div>
          </div>
        </div>


        <div id="message" style="color: green;"><xsl:value-of select="cdash/message" /></div>
        <br/>
        <!-- Main -->
        <form method="post" action="#" name="loginform">
          <table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
            <tbody>
              <tr class="table-heading">
                <td width="10%" class="nob">
                  <div align="right"> Email: </div>
                </td>
                <td  width="70%" class="nob">
                  <input class="textbox" name="login" size="40"/>
                </td>
                <td width="20%" align="right" class="nob"></td>
              </tr>
              <tr class="table-heading">
                <td width="10%" class="nob" >
                  <div align="right">Password: </div>
                </td>
                <td width="70%" class="nob">
                  <input class="textbox" type="password"  name="passwd" size="20"/>
                  <xsl:if test="/cdash/allowlogincookie=1">
                    <input class="textbox" type="checkbox"  name="rememberme"/>Remember Me
                  </xsl:if>
                </td>
                <td width="20%" align="right" class="nob"></td>
              </tr>
              <tr class="table-heading">
                <td width="10%" class="nob"></td>
                <td width="70%" class="nob">
                  <input type="submit" value="Login &gt;&gt;" name="sent" class="textbox"/>
                  <td width="20%" align="right" class="nob">
                    <a href="recoverPassword.php">forgot your password?</a>
                  </td>
                </td>
              </tr>
              <xsl:if test="/cdash/oauth2">
                <tr class="table-heading">
                  <td width="10%" class="nob"></td>
                  <td width="70%" class="nob">
                    <hr />
                    <a href="" id="oauth2LoginText" onClick='oauth2Login();'>
                      Log in with your Google account
                    </a>
                    <br />
                    <xsl:if test="/cdash/allowlogincookie=1">
                      <input class="textbox" type="checkbox"  name="oauth-rememberme"/>Remember Me
                    </xsl:if>
                  </td>
                  <td width="20%" class="nob"></td>
                </tr>
              </xsl:if>
            </tbody>
          </table>
        </form>

        <!-- FOOTER -->
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
