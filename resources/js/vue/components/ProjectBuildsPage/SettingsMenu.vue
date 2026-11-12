<template>
  <div
    id="settings"
    data-test="settings-menu-toggle"
    @click="open = !open"
  >
    <span
      class="glyphicon glyphicon-filter"
      title="More display options"
    />
    <ul
      v-show="open"
      data-test="settings-menu"
    >
      <li>
        <a
          id="label_advancedview"
          class="cdash-link"
          data-test="advanced-view-toggle"
          @click="$emit('toggle-advanced-view')"
        >
          {{ advancedView ? 'Simple View' : 'Advanced View' }}
        </a>
      </li>
      <li>
        <a
          class="autorefresh cdash-link"
          data-test="auto-refresh-toggle"
          @click="$emit('toggle-auto-refresh')"
        >
          {{ autoRefresh ? 'Stop auto-refresh' : 'Auto-refresh' }}
        </a>
      </li>
      <li>
        <a
          id="label_colorblind"
          class="cdash-link"
          data-test="colorblind-toggle"
          @click="$emit('toggle-colorblind')"
        >
          {{ colorblind ? 'Classic palette' : 'Colorblind palette' }}
        </a>
      </li>
      <li>
        <a
          id="label_showfilters"
          class="cdash-link"
          data-test="show-filters-toggle"
          @click="$emit('toggle-filters')"
        >
          {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
        </a>
      </li>
      <li>
        <a
          href="http://public.kitware.com/Wiki/CDash:Documentation"
          class="helptrigger cdash-link"
          target="_blank"
          rel="noopener noreferrer"
        >
          Help
        </a>
      </li>
    </ul>
  </div>
</template>

<script>
/**
 * The menu of display settings.  Clicking anywhere in it, including on a setting, opens or closes it.
 */
export default {
  name: 'SettingsMenu',

  props: {
    advancedView: {
      type: Boolean,
      required: true,
    },

    autoRefresh: {
      type: Boolean,
      required: true,
    },

    colorblind: {
      type: Boolean,
      required: true,
    },

    showFilters: {
      type: Boolean,
      required: true,
    },
  },

  emits: {
    'toggle-advanced-view': () => true,
    'toggle-auto-refresh': () => true,
    'toggle-colorblind': () => true,
    'toggle-filters': () => true,
  },

  data() {
    return {
      open: false,
    };
  },
};
</script>
