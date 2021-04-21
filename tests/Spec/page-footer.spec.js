import VTooltip from 'v-tooltip'
import {createLocalVue, shallowMount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';

import PageFooter from "../../resources/js/components/PageFooter.vue";
import expect from 'expect';
import moment from "moment";

let component;
const currentdate = moment().format('YYYY-MM-DD');
const endpoint = `http://localhost/api/v1/index.php?project=CDash&date=${currentdate}`;
const generationtime = '0.01s';
const nightlytime = '23:00 EDT';

beforeEach(() => {
  const localVue = createLocalVue()
  localVue.use(VTooltip)

  component = shallowMount(PageFooter, {
    localVue,
    data () {
      return {
        currentdate: currentdate,
        endpoint: endpoint,
        generationtime: generationtime,
        nightlytime: nightlytime,
      }
    },
  });
});

test('PageFooter shows API link', () => {
  const selector = `a[href='${endpoint}']`;
  const el = component.find(selector);
  expect(el.element.tagName).toBe('A');
  expect(el.text()).toBe('View as JSON');
});

test('PageFooter shows generation time', () => {
  expect(component.html()).toContain('0.01s');
});

test('PageFooter shows testing day info', () => {
  var html = component.html();
  expect(html).toContain(`Current Testing Day ${currentdate}`);
  expect(html).toContain(`Started at ${nightlytime}`);
});
