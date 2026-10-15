<template>
  <SidebarSection
    title="Administrators"
    data-test="administrators-section"
  >
    <ul class="tw-flex tw-flex-col tw-gap-2">
      <li
        v-for="administrator in administrators"
        :key="administrator.id"
        class="tw-flex tw-items-center tw-gap-2 tw-text-sm"
        data-test="administrator"
      >
        <FontAwesomeIcon
          :icon="FA.faUser"
          class="tw-w-4 tw-shrink-0 tw-text-neutral-500"
        />
        <span class="tw-truncate">{{ `${administrator.firstname ?? ''} ${administrator.lastname ?? ''}`.trim() }}</span>
      </li>
    </ul>
  </SidebarSection>
</template>

<script>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faUser } from '@fortawesome/free-solid-svg-icons';
import SidebarSection from './SidebarSection.vue';

export default {
  name: 'AdministratorsSection',

  components: {
    FontAwesomeIcon,
    SidebarSection,
  },

  props: {
    // A list of objects with id, firstname, and lastname keys.
    administrators: {
      type: Array,
      required: true,
    },
  },

  computed: {
    FA() {
      return {
        faUser,
      };
    },
  },
};
</script>
