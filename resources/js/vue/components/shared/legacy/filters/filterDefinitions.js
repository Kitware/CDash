/**
 * The fields which the legacy filters can filter on, and how each type of field can be compared.  The
 * legacy APIs identify comparisons by number.
 *
 * Remove this once the pages which use it use the GraphQL filters.
 */

export const FILTER_DEFINITIONS = {
  buildduration: {
    text: 'Build Duration',
    type: 'number',
    defaultvalue: 0,
  },
  builderrors: {
    text: 'Build Errors',
    type: 'number',
    defaultvalue: 0,
  },
  buildgenerator: {
    text: 'Submission Client',
    type: 'string',
    defaultvalue: '2.8',
  },
  buildname: {
    text: 'Build Name',
    type: 'string',
    defaultvalue: '',
  },
  buildstamp: {
    text: 'Build Stamp',
    type: 'string',
    defaultvalue: '',
  },
  buildstarttime: {
    text: 'Build Start Time',
    type: 'date',
    defaultvalue: '',
  },
  buildtype: {
    text: 'Build Type',
    type: 'string',
    defaultvalue: 'Nightly',
  },
  buildwarnings: {
    text: 'Build Warnings',
    type: 'number',
    defaultvalue: 0,
  },
  configureduration: {
    text: 'Configure Duration',
    type: 'number',
    defaultvalue: 0,
  },
  configureerrors: {
    text: 'Configure Errors',
    type: 'number',
    defaultvalue: 0,
  },
  configurewarnings: {
    text: 'Configure Warnings',
    type: 'number',
    defaultvalue: 0,
  },
  coveredlines: {
    text: 'Covered Lines',
    type: 'number',
    defaultvalue: 0,
  },
  ctestnotetext: {
    text: 'CTest Note Text',
    type: 'string',
    defaultvalue: '',
  },
  details: {
    text: 'Details',
    type: 'string',
    defaultvalue: '',
  },
  expected: {
    text: 'Expected',
    type: 'bool',
    defaultvalue: '',
  },
  filename: {
    text: 'Filename',
    type: 'string',
    defaultvalue: '',
  },
  groupname: {
    text: 'Group',
    type: 'string',
    defaultvalue: 'Nightly',
  },
  hascoverage: {
    text: 'Has Coverage',
    type: 'bool',
    defaultvalue: '',
  },
  hasctestnotes: {
    text: 'Has CTest Notes',
    type: 'bool',
    defaultvalue: '',
  },
  hasdynamicanalysis: {
    text: 'Has Dynamic Analysis',
    type: 'bool',
    defaultvalue: '',
  },
  hasusernotes: {
    text: 'Has User Notes',
    type: 'bool',
    defaultvalue: '',
  },
  label: {
    text: 'Label',
    type: 'string',
    defaultvalue: '',
  },
  revision: {
    text: 'Revision',
    type: 'string',
    defaultvalue: '',
  },
  site: {
    text: 'Site',
    type: 'string',
    defaultvalue: '',
  },
  status: {
    text: 'Status',
    type: 'string',
    defaultvalue: '',
  },
  subproject: {
    text: 'SubProject',
    type: 'string',
    defaultvalue: '',
  },
  subprojects: {
    text: 'SubProjects',
    type: 'list',
    defaultvalue: '',
    content: true,
  },
  testname: {
    text: 'Test Name',
    type: 'string',
    defaultvalue: '',
  },
  testoutput: {
    text: 'Test Output',
    type: 'search',
    defaultvalue: '',
    content: true,
  },
  testsduration: {
    text: 'Tests Duration',
    type: 'number',
    defaultvalue: 0,
  },
  testsfailed: {
    text: 'Tests Failed',
    type: 'number',
    defaultvalue: 0,
  },
  testsnotrun: {
    text: 'Tests Not Run',
    type: 'number',
    defaultvalue: 0,
  },
  testspassed: {
    text: 'Tests Passed',
    type: 'number',
    defaultvalue: 0,
  },
  testtimestatus: {
    text: 'Tests Timing Failed',
    type: 'number',
    defaultvalue: 0,
  },
  time: {
    text: 'Time',
    type: 'number',
    defaultvalue: '',
  },
  timestatus: {
    text: 'Time Status',
    type: 'string',
    defaultvalue: '',
  },
  totallines: {
    text: 'Total Lines',
    type: 'number',
    defaultvalue: 0,
  },
  uncoveredlines: {
    text: 'Uncovered Lines',
    type: 'number',
    defaultvalue: 0,
  },
  updateduration: {
    text: 'Update Duration',
    type: 'number',
    defaultvalue: 0,
  },
  updatedfiles: {
    text: 'Updated Files',
    type: 'number',
    defaultvalue: 0,
  },
};

export const COMPARISONS = {
  bool: [
    { value: 1, text: 'is true' },
    { value: 2, text: 'is false' },
  ],
  date: [
    { value: 81, text: 'is' },
    { value: 82, text: 'is not' },
    { value: 83, text: 'is after' },
    { value: 84, text: 'is before' },
  ],
  number: [
    { value: 41, text: 'is' },
    { value: 42, text: 'is not' },
    { value: 43, text: 'is greater than' },
    { value: 44, text: 'is less than' },
  ],
  search: [
    { value: 94, text: 'exclude' },
    { value: 95, text: 'include' },
    { value: 96, text: 'exclude regex' },
    { value: 97, text: 'include regex' },
  ],
  string: [
    { value: 63, text: 'contains' },
    { value: 64, text: 'does not contain' },
    { value: 61, text: 'is' },
    { value: 62, text: 'is not' },
    { value: 65, text: 'starts with' },
    { value: 66, text: 'ends with' },
  ],
  list: [
    { value: 92, text: 'exclude' },
    { value: 93, text: 'include' },
  ],
};
