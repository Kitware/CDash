<template>
  <SidebarSection
    title="Contact"
    data-test="contact-section"
  >
    <template
      v-if="canUpdate"
      #actions
    >
      <button
        type="button"
        class="tw-btn tw-btn-ghost tw-btn-xs tw-text-neutral-500"
        title="Edit contact information"
        aria-label="Edit contact information"
        data-test="edit-contact-button"
        @click="$refs.editDialog.open()"
      >
        <FontAwesomeIcon :icon="FA.faPen" />
      </button>
    </template>

    <div
      v-if="contactInfo"
      class="tw-flex tw-items-baseline tw-gap-2 tw-text-sm"
    >
      <FontAwesomeIcon
        :icon="FA.faAddressCard"
        class="tw-w-4 tw-shrink-0 tw-text-neutral-500"
      />
      <span
        class="tw-min-w-0 tw-break-words"
        data-test="contact-info"
      >{{ contactInfo }}</span>
    </div>
    <div
      v-else
      class="tw-flex tw-flex-col tw-items-start tw-gap-2 tw-text-sm tw-text-neutral-500"
      data-test="no-contact-message"
    >
      No contact information has been added to this project.
      <button
        v-if="canUpdate"
        type="button"
        class="tw-btn tw-btn-sm tw-btn-outline tw-border-base-300 tw-font-normal"
        data-test="add-contact-button"
        @click="$refs.editDialog.open()"
      >
        <FontAwesomeIcon :icon="FA.faPlus" />
        Add contact information
      </button>
    </div>

    <EditContactDialog
      v-if="canUpdate"
      ref="editDialog"
      :project-id="projectId"
      :contact-info="contactInfo"
    />
  </SidebarSection>
</template>

<script>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faAddressCard,
  faPen,
  faPlus,
} from '@fortawesome/free-solid-svg-icons';
import EditContactDialog from './EditContactDialog.vue';
import SidebarSection from './SidebarSection.vue';

export default {
  name: 'ContactSection',

  components: {
    EditContactDialog,
    FontAwesomeIcon,
    SidebarSection,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    canUpdate: {
      type: Boolean,
      default: false,
    },

    contactInfo: {
      type: [String, null],
      default: null,
    },
  },

  computed: {
    FA() {
      return {
        faAddressCard,
        faPen,
        faPlus,
      };
    },
  },
};
</script>
