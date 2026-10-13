// d3 and nvd3 are only needed by the pages that draw charts, so this module is
// imported dynamically and bundled into its own chunk, fetched on first use.
import d3 from 'd3';
import nv from 'nvd3';

export { d3, nv };
