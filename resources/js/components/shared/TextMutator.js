export default {
  ctestNonXmlCharEscape: function (input) {
    const pattern = /\[NON-XML-CHAR-0x1B\]/g;
    return input.replace(pattern, '\x1B');
  },

  terminalColors: function (input, htmlEscape) {
    const ansiUp = new AnsiUp;
    if (htmlEscape !== undefined) {
      ansiUp.escape_for_html = htmlEscape;
    }
    return ansiUp.ansi_to_html(input);
  },
};
