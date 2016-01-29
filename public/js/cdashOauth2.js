// loosely adapted & updated from the example here:
// http://www.gethugames.in/blog/2012/04/authentication-and-authorization-for-google-apis-in-javascript-popup-window-tutorial.html

var OAUTHURL    =   'https://accounts.google.com/o/oauth2/auth?';
var SCOPE       =   'https://www.googleapis.com/auth/userinfo.email';
var TYPE        =   'code';

function oauth2Login() {
  // construct redirect URI
  var REDIRECT = window.location.href;
  REDIRECT = REDIRECT.substring(0, REDIRECT.lastIndexOf(window.location.pathname));
  REDIRECT += "/googleauth_callback.php";

  // get state (anti-forgery token) from session via CDash API
  $.get('api/v1/getState.php', function(securityToken) {
    // overload state to contain both the URL that the user is attempting to
    // access, as well as the anti-forgery token.
    var STATE = encodeURIComponent(document.URL) + "_AND_STATE_IS_" + securityToken;

    // construct Google authentication URL with the query string all filled out
    var _url = OAUTHURL + 'scope=' + SCOPE + '&client_id=' + CLIENTID +
      '&redirect_uri=' + REDIRECT + '&state=' + STATE + '&response_type=' + TYPE;

    // redirect to the Google authentication page
    window.location = _url;
  });
}

