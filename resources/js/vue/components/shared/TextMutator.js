import { AnsiUp } from 'ansi_up';

export default {
  ctestNonXmlCharEscape: function (input) {
    const pattern = /\[NON-XML-CHAR-0x1B\]/g;
    return input.replace(pattern, '\x1B');
  },

  terminalColors: function (input) {
    const ansiUp = new AnsiUp;
    return ansiUp.ansi_to_html(input);
  },
};
