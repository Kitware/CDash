import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';

import PageHeader from "../../resources/js/components/PageHeader.vue";
import HeaderTop from "../../resources/js/components/page-header/HeaderTop.vue";
import HeaderBottom from "../../resources/js/components/page-header/HeaderBottom.vue";
import expect from 'expect';

let component;

beforeEach(() => {
  component = mount(PageHeader);
});

test('PageHeader has a HeaderTop component', () => {
  const child = component.findComponent(HeaderTop);
  expect(child.is(HeaderTop)).toBe(true);
});

test('PageHeader has a HeaderBottom component', () => {
  const child = component.findComponent(HeaderBottom);
  expect(child.is(HeaderBottom)).toBe(true);
});
