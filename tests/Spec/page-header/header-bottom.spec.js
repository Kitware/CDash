import {mount, config} from "@vue/test-utils";
config.mocks['$baseURL'] = 'http://localhost';
import HeaderBottom from "../../../resources/js/components/page-header/HeaderBottom.vue";
import expect from 'expect';
import HeaderNav from "../../../resources/js/components/page-header/HeaderNav.vue";
import HeaderMenu from "../../../resources/js/components/page-header/HeaderMenu.vue";

describe('HeaderBottom', () => {
  let component;

  beforeEach(() => {
    component = mount(HeaderBottom);
  });

  it('has an id of #headerbottom', () => {
    const el = component.find('#headerbottom');
    expect(el.is('div')).toBe(true);
  });

  it('has a child of #headerbottom with id #headerlogo', () => {
    const el = component.find('#headerlogo');
    expect(el.contains('#headerlogo')).toBe(true);
  });

  it('displays the CDash logo by default', () => {
    const logo = component.find('#headerlogo a img');
    expect(logo.is('img')).toBe(true);
    expect(logo.attributes().src).toBe('http://localhost/img/cdash.png');
  });

  it('displays CDash as the default project name if none given', () => {
    const el = component.findAll('.projectname');
    expect(el.at(0).text()).toBe('CDash');
  });

  it('does not display a page name by default', () => {
    const el = component.findAll('.pagename');
    expect(el.at(0).text()).toBe('');
  });

  it('displays the image given a logo property', async () => {
    component.setData({logo: '/path/to/some/other/src.png'});
    await component.vm.$nextTick();
    const el = component.find('#projectlogo');
    expect(el.attributes().src).toBe('/path/to/some/other/src.png');
  });

  it('does not display a HeaderNav component', () => {
    expect(component.find(HeaderNav).isVisible()).toBe(false);
  });

  it ('contains a HeaderMenu component', () => {
    expect(component.contains(HeaderMenu)).toBe(true);
  });
});
