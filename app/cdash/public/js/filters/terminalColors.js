CDash.filter('ctestNonXmlCharEscape', () => {
  return function(input) {
    const pattern = /\[NON-XML-CHAR-0x1B\]/g;
    return input.replace(pattern, '\x1B');
  };
})
  .filter('terminalColors', () => {
    return function(input, htmlEscape) {
      const ansiUp = new AnsiUp;
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
