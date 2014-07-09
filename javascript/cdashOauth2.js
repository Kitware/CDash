// loosely adapted & updated from the example here:
// http://www.gethugames.in/blog/2012/04/authentication-and-authorization-for-google-apis-in-javascript-popup-window-tutorial.html

var OAUTHURL    =   'https://accounts.google.com/o/oauth2/auth?';
var SCOPE       =   'https://www.googleapis.com/auth/userinfo.email';
var TYPE        =   'code';

function oauth2Login() {
  // construct redirect URI
  var REDIRECT = window.location.href;
  REDIRECT = REDIRECT.substring(0, REDIRECT.lastIndexOf("/"));
  REDIRECT += "/googleauth_callback.php";

  // get state (anti-forgery token) from session via CDash API
  $.get('api/getState.php', function(STATE) {

    // construct Google authentication URL with the query string all filled out
    var _url = OAUTHURL + 'scope=' + SCOPE + '&client_id=' + CLIENTID +
      '&redirect_uri=' + REDIRECT + '&response_type=' + TYPE + '&state=' +
      STATE;

    // redirect to the Google authentication page
    window.location = _url;
  });
}

