import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';

import PageFooter from "../../resources/js/components/PageFooter.vue";
import expect from 'expect';
import moment from "moment";

describe('PageFooter', () => {
  let component;
  const currentdate = moment().format('YYYY-MM-DD');
  const endpoint = `http://localhost/api/v1/index.php?project=CDash&date=${currentdate}`;
  const generationtime = 0.01;
  const nightlytime = '23:00 EDT';

  beforeEach(() => {
    component = mount(PageFooter, {
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

  it('shows API link', () => {
    const selector = `a[href='${endpoint}']`;
    const el = component.find(selector);
    expect(el.is('a')).toBe(true);
    expect(el.text()).toBe('View as JSON');
  });

  it('shows generation time', () => {
    expect(component.html()).toContain('0.01s');
  });

  it('shows testing day info', () => {
    var html = component.html();
    expect(html).toContain(`Current Testing Day ${currentdate}`);
    expect(html).toContain(`Started at ${nightlytime}`);
  });
});
