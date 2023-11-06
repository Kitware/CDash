import {mount, config} from "@vue/test-utils";
import axios from 'axios'
import AxiosMockAdapter from 'axios-mock-adapter';
import expect from 'expect';
import BuildSummary from "../../resources/js/components/BuildSummary.vue";

config.mocks['$baseURL'] = 'http://localhost';
axios.defaults.baseURL = config.mocks['$baseURL'];

import $ from 'jquery'
$.plot = function() { return null; };
global.$ = $

let apiResponse;
let axiosMockAdapter;

beforeEach(function() {
  axiosMockAdapter = new AxiosMockAdapter(axios);
  config.mocks['$axios'] = axios;
  apiResponse = {
    build: {
      command: 'make',
      compilername: 'gcc',
      compilerversion: 4.4,
      endtime: 'later',
      errors: [],
      buildhistory: [],
      id: 1,
      lastsubmitbuild: 0,
      lastsubmitdate: null,
      name: 'my build',
      nerrors: 0,
      note: null,
      nwarnings: 5,
      osname: 'Linux',
      osplatform: 'unknown',
      osrelease: 'unknown',
      osversion: 'unknown',
      site: 'mysite',
      siteid: 1,
      stamp: 'today',
      starttime: 'earlier',
      time: 'earlier',
      type: 'Experimental',
      warnings: [],
      labels: [
        'label1',
        'label2',
      ],
    },
    configure: {
      command: 'cmake',
      endtime: 'later',
      nerrors: 0,
      nwarnings: 0,
      output: '',
      starttime: 'today',
      status: 0,
    },
    coverage: 98,
    hasconfigure: true,
    hascoverage: true,
    hasupdate: true,
    newissueurl: null,
    notes: [],
    projectname_encoded: 'MyProject',
    relationships_from: [],
    relationships_to: [],
    test: {
      nfailed: 0,
      nnotrun: 0,
      npassed: 5,
    },
    update: {
      command: 'git pull',
      endtime: 'later',
      nerrors: 0,
      nupdates: 0,
      nwarnings: 0,
      starttime: 'today',
      status: 0,
      type: 'Revision',
    },
    user: {
      id: 1,
    },
  };
});

afterEach(function() {
  axiosMockAdapter.restore();
});

test('BuildSummary handles API response', async () => {
  axiosMockAdapter.onGet('/api/v1/buildSummary.php?buildid=').reply(200, apiResponse);
  const component = mount(BuildSummary);
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  expect(component.vm.loading).toBe(false);
  expect(component.vm.cdash.coverage).toBe(98);
  var html = component.html();
  expect(html).toContain('MyProject');
  expect(html).toContain('mysite');
  expect(html).toContain('Linux');
  expect(html).toContain('label1, label2');
  const site_link = component.find('#site_link');
  expect(site_link.attributes('href')).toMatch('/viewSite.php?siteid=1');
  expect(site_link.text()).toBe('mysite');

  const configure_link = component.find('#configure_link');
  expect(configure_link.attributes('href')).toMatch('/build/1/configure');
  expect(configure_link.text()).toBe('View Configure Summary');
});

test('BuildSummary can toggle the graphs', async () => {
  axiosMockAdapter.onGet('/api/v1/buildSummary.php?buildid=').reply(200, apiResponse);
  const component = mount(BuildSummary);
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();

  expect(component.vm.showHistoryGraph).toBe(false);
  expect(component.find('#historyGraph').isVisible()).toBe(false);

  expect(component.vm.showTimeGraph).toBe(false);
  expect(component.find('#buildtimegrapholder').isVisible()).toBe(false);

  expect(component.vm.showErrorGraph).toBe(false);
  expect(component.find('#builderrorsgrapholder').isVisible()).toBe(false);

  expect(component.vm.showWarningGraph).toBe(false);
  expect(component.find('#buildwarningsgrapholder').isVisible()).toBe(false);

  expect(component.vm.showTestGraph).toBe(false);
  expect(component.find('#buildtestsfailedgrapholder').isVisible()).toBe(false);

  let graph_data = {
    builds: [{
      id: 1,
      nfiles: 0,
      configurewarnings: 0,
      configureerrors: 0,
      buildwarnings: 0,
      builderrors: 0,
      starttime: 'today',
      timestamp: 'today',
      testfailed: 0,
      time: 0,
    }]
  };
  axiosMockAdapter.onGet('/api/v1/getPreviousBuilds.php?buildid=').reply(200, graph_data);
  const history_button = component.find('#toggle_history_graph')
  history_button.trigger('click');
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  expect(component.vm.showHistoryGraph).toBe(true);
  expect(component.find('#historyGraph').isVisible()).toBe(true);

  history_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showHistoryGraph).toBe(false);
  expect(component.find('#historyGraph').isVisible()).toBe(false);

  const time_button = component.find('#toggle_time_graph')
  time_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showTimeGraph).toBe(true);
  expect(component.find('#buildtimegrapholder').isVisible()).toBe(true);
  time_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showTimeGraph).toBe(false);
  expect(component.find('#buildtimegrapholder').isVisible()).toBe(false);

  const error_button = component.find('#toggle_error_graph')
  error_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showErrorGraph).toBe(true);
  expect(component.find('#builderrorsgrapholder').isVisible()).toBe(true);
  error_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showErrorGraph).toBe(false);
  expect(component.find('#builderrorsgrapholder').isVisible()).toBe(false);

  const warning_button = component.find('#toggle_warning_graph')
  warning_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showWarningGraph).toBe(true);
  expect(component.find('#buildwarningsgrapholder').isVisible()).toBe(true);
  warning_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showWarningGraph).toBe(false);
  expect(component.find('#buildwarningsgrapholder').isVisible()).toBe(false);

  const test_button = component.find('#toggle_test_graph');
  test_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showTestGraph).toBe(true);
  expect(component.find('#buildtestsfailedgrapholder').isVisible()).toBe(true);
  test_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.vm.showTestGraph).toBe(false);
  expect(component.find('#buildtestsfailedgrapholder').isVisible()).toBe(false);
});

test('BuildSummary can add a build note', async () => {
  axiosMockAdapter.onGet('/api/v1/buildSummary.php?buildid=').reply(200, apiResponse);
  const component = mount(BuildSummary);
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  await component.vm.$nextTick();

  expect(component.find('#new_note_div').isVisible()).toBe(false);
  expect(component.vm.cdash.notes.length).toBe(0);

  const toggle_button = component.find('#toggle_note');
  toggle_button.trigger('click');
  await component.vm.$nextTick();
  expect(component.find('#new_note_div').isVisible()).toBe(true);

  var api_response = {
    note: {
      user: 'administrator',
      date: 'now',
      status: '[note]',
      text: 'This is a note',
    }
  };
  axiosMockAdapter.onPost('/api/v1/addUserNote.php').reply(200, api_response);

  const note_textarea = component.find('#note_text');
  note_textarea.element.value = 'This is a note';
  note_textarea.trigger('input');
  await component.vm.$nextTick();
  expect(component.vm.cdash.noteText).toBe('This is a note');

  const add_note_button = component.find('#add_note');
  add_note_button.trigger('click');
  await component.vm.$nextTick();
  await component.vm.$nextTick();
  expect(component.vm.cdash.notes.length).toBe(1);
  expect(component.vm.cdash.notes[0].text).toBe('This is a note');
});
