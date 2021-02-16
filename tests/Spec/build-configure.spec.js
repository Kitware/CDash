import {mount, config} from "@vue/test-utils";
import axios from 'axios'
import AxiosMockAdapter from 'axios-mock-adapter';
import expect from 'expect';
import BuildConfigure from "../../resources/js/components/BuildConfigure.vue";

config.mocks['$baseURL'] = 'http://localhost';
axios.defaults.baseURL = config.mocks['$baseURL'];

describe('BuildConfigure', function() {
  let component;

  beforeEach(function() {
    this.axios = new AxiosMockAdapter(axios);
    config.mocks['$axios'] = axios;
    this.api_response = {
      build: {
        site: "mysite",
        siteid: 1,
        buildname: "my build",
        buildid: 2,
        hassubprojects: 0
      },
      configures: [{
        status: 0,
        command: "cmake",
        output: "-- Configuring done\n-- Generating done\n-- Build files have been written to: \/tmp\/bin\/\n",
        configureerrors: 0,
        configurewarnings: 0
      }],
    };
  });

  afterEach(function() {
    this.axios.restore();
  });

  it('handles API response', async function() {
    this.axios.onGet('/api/v1/viewConfigure.php?buildid=').reply(200, this.api_response);
    component = mount(BuildConfigure);
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    await component.vm.$nextTick();
    expect(component.vm.loading).toBe(false);
    var html = component.html();
    expect(html).toContain('mysite');
    expect(html).toContain('my build');
    expect(html).toContain('Configuring done');
    expect(html).toContain('written to: /tmp/bin/');
  });
});
