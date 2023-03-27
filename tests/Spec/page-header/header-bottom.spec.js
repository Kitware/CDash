import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';
import HeaderBottom from "../../../resources/js/components/page-header/HeaderBottom.vue";
import expect from 'expect';
import HeaderNav from "../../../resources/js/components/page-header/HeaderNav.vue";
import HeaderMenu from "../../../resources/js/components/page-header/HeaderMenu.vue";

let component;

beforeEach(() => {
  component = mount(HeaderBottom);
});

test('HeaderBottom has an id of #headerbottom', () => {
  const el = component.find('#headerbottom');
  expect(el.element.tagName).toBe('DIV');
});

test('HeaderBottom has a child of #headerbottom with id #headerlogo', () => {
  const el = component.find('#headerlogo');
  expect(el.find('#headerlogo').exists()).toBe(true);
});

test('HeaderBottom displays the CDash logo by default', () => {
  const logo = component.find('#headerlogo a img');
  expect(logo.element.tagName).toBe('IMG');
  expect(logo.attributes().src).toBe('http://localhost/img/cdash.png');
});

test('HeaderBottom displays CDash as the default project name if none given', () => {
  const el = component.findAll('.projectname');
  expect(el.at(0).text()).toBe('CDash');
});

test('HeaderBottom does not display a page name by default', () => {
  const el = component.findAll('.pagename');
  expect(el.at(0).text()).toBe('');
});

test('HeaderBottom displays the image given a logo property', async () => {
  component.setData({logo: '/path/to/some/other/src.png'});
  await component.vm.$nextTick();
  const el = component.find('#projectlogo');
  expect(el.attributes().src).toBe('/path/to/some/other/src.png');
});

test('HeaderBottom does not display a HeaderNav component', () => {
  expect(component.findComponent(HeaderNav).isVisible()).toBe(false);
});

test('HeaderBottom contains a HeaderMenu component', () => {
  expect(component.findComponent(HeaderMenu).exists()).toBe(true);
});
