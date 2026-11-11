// The legacy filters' date range and the header's calendar use jQuery UI's date picker.  Import this
// module dynamically, so jQuery and jQuery UI are only fetched once a date picker is needed.  Its
// styles come from legacy.css.
import $ from 'jquery';
import 'jquery-ui-dist/jquery-ui.js';

export default $;
