import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';

import PageHeader from "../../resources/js/components/PageHeader.vue";
import HeaderTop from "../../resources/js/components/page-header/HeaderTop.vue";
import HeaderBottom from "../../resources/js/components/page-header/HeaderBottom.vue";
import expect from 'expect';

describe('PageHeader', () => {
  let component;

  beforeEach(() => {
    component = mount(PageHeader);
  });

  it('has a HeaderTop component', () => {
    let child = component.find(HeaderTop);
    expect(child.is(HeaderTop)).toBe(true);
  });

  it('has a HeaderBottom component', () => {
    let child = component.find(HeaderBottom);
    expect(child.is(HeaderBottom)).toBe(true);
  });
});
