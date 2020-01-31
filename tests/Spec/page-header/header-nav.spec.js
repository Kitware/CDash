import {mount} from "@vue/test-utils";
import HeaderNav from "../../../resources/js/components/page-header/HeaderNav.vue";
import expect from 'expect';

describe('HeaderNav', () => {
  let component;

  const testButtonState = async (button) => {
    const selector = `#header-nav-${button}-btn`;
    expect(component.find(selector).classes()).toContain('btn-disabled');

    component.setData({[button]: '/some/endpoint'});
    await component.vm.$nextTick();

    const el = component.find(selector);
    expect(el.classes()).not.toContain('btn-disabled');
    expect(el.classes()).toContain('btn');
  };

  beforeEach(() => {
    component = mount(HeaderNav);
  });

  it('has a "Prev" button that is disabled when no previous href property exists', async () => {
    await testButtonState('previous');
  });

  it('has a "Current" button that is disabled when no current href property exists', async () => {
    testButtonState('current');
  });

  it('has a "Next" button that is disabled when no next url property exists', async () => {
    testButtonState('next');
  });
});
