import {mount, config} from "@vue/test-utils";
import axios from 'axios'
import AxiosMockAdapter from 'axios-mock-adapter';
import expect from 'expect';
import BuildNotes from "../../resources/js/components/BuildNotes.vue";

config.mocks['$baseURL'] = '';
axios.defaults.baseURL = config.mocks['$baseURL'];

import $ from 'jquery'
$.plot = function() { return null; };
global.$ = $

describe('BuildNotes', function() {
  let component;

  beforeEach(function() {
    this.axios = new AxiosMockAdapter(axios);
    config.mocks['$axios'] = axios;
    this.api_response = {
      build: {
        buildid: 1,
        buildname: 'my build',
        siteid: 1,
        site: 'mysite',
        stamp: 'today',
      },
      notes: [
        {
          name: 'a note',
          text: 'note text',
          time: 'yesterday',
        },
      ],
      user: {
        id: 1,
      },
    };
  });

  afterEach(function() {
    this.axios.restore();
  });

  it('handles API response', async function() {
    this.axios.onGet('/api/v1/viewNotes.php?buildid=undefined').reply(200, this.api_response);
    component = mount(BuildNotes);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    expect(component.vm.loading).toBe(false);
    expect(component.vm.cdash.notes.length).toBe(1);

    // Verify some expected content.
    var html = component.html();
    expect(html).toContain('my build');
    expect(html).toContain('mysite');
    expect(html).toContain('today');

    // Since we only have one note it should be displayed by default.
    expect(component.find('#notetext0').isVisible()).toBe(true);
  });

  it('can toggle notes', async function() {
    this.api_response.notes.push({
      name: 'another note',
      text: 'more note text',
      time: 'later on',
    });
    this.axios.onGet('/api/v1/viewNotes.php?buildid=undefined').reply(200, this.api_response);
    component = mount(BuildNotes);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();

    // Two notes, both hidden by default.
    expect(component.vm.cdash.notes.length).toBe(2);
    expect(component.find('#notetext0').isVisible()).toBe(false);
    expect(component.find('#notetext1').isVisible()).toBe(false);

    // Toggle a note back on.
    var note_button = component.find('#note0')
    note_button.trigger('click');
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    expect(component.find('#notetext0').isVisible()).toBe(true);
  });
});
