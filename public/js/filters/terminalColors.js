CDash.filter('ctestNonXmlCharEscape', function() {
  return function(input) {
    var pattern = /\[NON-XML-CHAR-0x1B\]/g;
    return input.replace(pattern, '\x1B');
  };
})
.filter('terminalColors', function() {
  return function(input, htmlEscape) {
    var ansiUp = new AnsiUp;
    if (htmlEscape !== undefined) {
      ansiUp.escape_for_html = htmlEscape;
    }
    return ansiUp.ansi_to_html(input);
  };
})
.filter('trustAsHtml', ['$sce', function($sce) {
  return function(input) {
    return $sce.trustAsHtml(input);
  };
}]);
