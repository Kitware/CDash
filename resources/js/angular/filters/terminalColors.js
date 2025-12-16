import AnsiUp from 'ansi_up';

export function ctestNonXmlCharEscape() {
  return function(input) {
    var pattern = /\[NON-XML-CHAR-0x1B\]/g;
    return input.replace(pattern, '\x1B');
  };
}

export function terminalColors() {
  return function(input, htmlEscape) {
    var ansiUp = new AnsiUp;
    if (htmlEscape !== undefined) {
      ansiUp.escape_for_html = htmlEscape;
    }
    return ansiUp.ansi_to_html(input);
  };
}

export const trustAsHtml = ['$sce', function($sce) {
  return function(input) {
    return $sce.trustAsHtml(input);
  };
}];
