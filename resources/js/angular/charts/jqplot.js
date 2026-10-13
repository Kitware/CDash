// jqPlot is only needed by the overview page's line charts, so this module is
// imported dynamically and bundled into its own chunk, fetched on first use.
// The plugins register themselves on $.jqplot, so they must come after it.
import 'as-jqplot/dist/jquery.jqplot.js';
import 'as-jqplot/dist/plugins/jqplot.dateAxisRenderer.js';
import 'as-jqplot/dist/plugins/jqplot.highlighter.js';
