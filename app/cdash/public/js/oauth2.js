function oauth2(type) {
  var requestedURI = document.URL;
    // Don't send the user back to the login page if that's where they came from.
    if (requestedURI.indexOf('login') !== -1) {
      requestedURI = 'user';
    }
  requestedURI = encodeURIComponent(requestedURI);

  // Redirect to authentication page.
  var OAUTHURL = 'auth/' + type + '.php';
  window.location = OAUTHURL + "?dest=" + requestedURI;
}
