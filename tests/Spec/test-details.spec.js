import {mount, config} from "@vue/test-utils";
import axios from 'axios'
import AxiosMockAdapter from 'axios-mock-adapter';
import expect from 'expect';
import TestDetails from "../../resources/js/components/TestDetails.vue";

config.mocks['$baseURL'] = '';
axios.defaults.baseURL = config.mocks['$baseURL'];

import $ from 'jquery'
$.plot = function() { return null; };
$.fn.je_compare = function() { return null; };
global.$ = $

import AnsiUp from 'ansi_up';
global.AnsiUp = AnsiUp

import d3 from 'd3';

describe('TestDetails', function() {
  let component;

  beforeEach(function() {
    this.axios = new AxiosMockAdapter(axios);
    config.mocks['$axios'] = axios;
    this.api_response = {
      project: {
        showtesttime: 0,
      },
      test: {
        id: 1,
        buildid: 1,
        build: 'my build',
        buildstarttime: 'yesterday',
        siteid: 1,
        site: 'my site',
        test: 'my-test',
        time: '100ms',
        command: '/usr/bin/false',
        details: 'Completed (OTHER_FAULT)',
        environment: 'foo=bar',
        output: "\u001b[32mHello world!\n\u001b[91m<script type=\"text\/javascript\">console.log(\"MALICIOUS JAVASCRIPT!!!\");<\/script>\n\u001b[0mGood bye world!\n",
        summaryLink: 'testSummary.php?project=1&name=my-test',
        status: 'Failed',
        statusColor: 'error-text',
        update: {
          revision: 'asdf',
          revisionurl: 'https://github.com/asdf',
        },
        timemean: 0.00,
        timestd: 0.00,
        timestatus: 'Passed',
        timeStatusColor: 'normal-text',
        measurements: [
          {
            name: 'Exit Value',
            type: 'text/string',
            value: 5,
          },
          {
            name: 'results.txt',
            type: 'file',
            value: '',
          },
        ],
      },
      user: {
        id: 1,
      },
    };
  });

  afterEach(function() {
    this.axios.restore();
  });

  it('handles API response', async function() {
    this.axios.onGet('/api/v1/testDetails.php?buildtestid=blank').reply(200, this.api_response);
    component = mount(TestDetails);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    expect(component.vm.loading).toBe(false);

    // Verify some expected content.
    var html = component.html();
    expect(html).toContain('my build');
    expect(html).toContain('my-test');
    expect(html).toContain('Completed (OTHER_FAULT)');

    // Verify colorized/escaped output.
    const test_output = component.find("#test_output");
    const spans = test_output.findAll("span");
    expect(spans.length).toBe(2);
    expect(spans.at(0).attributes('style')).toBe('color:rgb(0,187,0)');
    expect(spans.at(0).text()).toBe('Hello world!');
    expect(spans.at(1).attributes('style')).toBe('color:rgb(255,85,85)');
    expect(spans.at(1).text()).toBe('<script type="text/javascript">console.log("MALICIOUS JAVASCRIPT!!!");</script>');

    // Verify links.
    const summary_link = component.find('#summary_link');
    expect(summary_link.text()).toBe('my-test');
    expect(summary_link.attributes('href')).toBe('/testSummary.php?project=1&name=my-test');

    const build_link = component.find('#build_link');
    expect(build_link.text()).toBe('my build');
    expect(build_link.attributes('href')).toBe('/build/1');

    const site_link = component.find('#site_link');
    expect(site_link.text()).toBe('(my site)');
    expect(site_link.attributes('href')).toBe('/viewSite.php?siteid=1');

    const revision_link = component.find('#revision_link');
    expect(revision_link.text()).toBe('asdf');
    expect(revision_link.attributes('href')).toBe('https://github.com/asdf');

    const file_link = component.find('a[href*="fileid="]');
    expect(file_link.isVisible()).toBe(true);
    expect(file_link.attributes('href')).toBe('/api/v1/testDetails.php?buildtestid=blank&fileid=undefined');
  });

  it('can toggle command line', async function() {
    this.axios.onGet('/api/v1/testDetails.php?buildtestid=blank').reply(200, this.api_response);
    component = mount(TestDetails);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();

    // Command line hidden by default.
    expect(component.find('#commandline').isVisible()).toBe(false);

    // Toggle it on.
    var commandlinelink = component.find('#commandlinelink')
    commandlinelink.trigger('click');
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    expect(component.find('#commandline').isVisible()).toBe(true);
  });

  it('can toggle the graphs', async function() {
    this.axios.onGet('/api/v1/testDetails.php?buildtestid=blank').reply(200, this.api_response);
    component = mount(TestDetails);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();

    expect(component.vm.showgraph).toBe(false);
    expect(component.find('#graph_holder').isVisible()).toBe(false);

    let graph_data = [
      {
        label: 'Execution Time (seconds)',
        data: [{
          buildtestid: 57,
          x:           1235391038000,
          y:           0.16,
        }],
      },
      {
        label: 'Acceptable Range',
        data: [{
          x: 1235391038000,
          y: 0,
        }],
      },
    ];

    this.axios.onGet('/api/v1/testGraph.php?testid=1&buildid=1&type=time').reply(200, graph_data);
    const graph_selector = component.find('#GraphSelection');
    graph_selector.findAll('option').at(1).setSelected();
    graph_selector.trigger('change');

    const options = graph_selector.findAll('option');
    expect(options.length).toBe(3);
    expect(options.at(0).text()).toBe('Select...');
    expect(options.at(1).text()).toBe('Test Time');
    expect(options.at(2).text()).toBe('Failing/Passing');

    options.at(1).setSelected();

    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();

    expect(component.vm.showgraph).toBe(true);
    expect(component.find('#graph_holder').isVisible()).toBe(true);

    const json_link = component.find('a[href*="testGraph.php"]');
    expect(json_link.isVisible()).toBe(true);
    expect(json_link.attributes('href')).toBe('/api/v1/testGraph.php?testid=1&buildid=1&type=time');
  });

  it('can toggle environment', async function() {
    this.axios.onGet('/api/v1/testDetails.php?buildtestid=blank').reply(200, this.api_response);
    component = mount(TestDetails);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();

    // We have an environment but it's hidden by default.
    expect(component.vm.hasenvironment).toBe(true);
    expect(component.vm.showenvironment).toBe(false);
    expect(component.find('#environment').isVisible()).toBe(false);

    // Toggle it on.
    var environmentlink = component.find('#environmentlink')
    environmentlink.trigger('click');
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    expect(component.find('#environment').isVisible()).toBe(true);
  });

  it('"Show Environment" toggle is conditionally rendered', async function() {
    this.api_response.test.environment = '';
    this.axios.onGet('/api/v1/testDetails.php?buildtestid=blank').reply(200, this.api_response);
    component = mount(TestDetails);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    expect(component.contains('#environmentlink')).toBe(false);
  });

});
