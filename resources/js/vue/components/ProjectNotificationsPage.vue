<template>
  <div
    class="tw-flex tw-flex-col tw-w-full tw-max-w-4xl tw-mx-auto tw-gap-8 tw-p-4"
    data-test="project-notifications-page"
  >
    <div
      v-if="message"
      class="tw-alert tw-alert-success"
      data-test="message"
    >
      {{ message }}
    </div>
    <div
      v-if="!projectEmailsEnabled"
      class="tw-alert tw-alert-warning"
      data-test="project-emails-disabled-warning"
    >
      <span>
        This project has not been configured to send emails.
        <a
          v-if="canEditProject"
          :href="`${$baseURL}/projects/${projectId}/settings`"
          class="tw-link"
        >Change the project settings.</a>
        <template v-else>
          Contact the project administrator.
        </template>
      </span>
    </div>

    <form
      method="post"
      action=""
      class="tw-flex tw-flex-col tw-gap-8"
    >
      <input
        type="hidden"
        name="_token"
        :value="csrfToken"
      >

      <FormSection title="Email Notifications">
        <span class="tw-label tw-label-text tw-font-bold">
          Email me:
        </span>
        <label
          v-for="option in emailTypeOptions"
          :key="option.value"
          class="tw-flex tw-items-center tw-gap-2"
        >
          <input
            v-model="form.emailType"
            type="radio"
            name="emailtype"
            class="tw-radio"
            :value="option.value"
            :data-test="`email-type-${option.value}`"
          >
          <span class="tw-label-text">{{ option.text }}</span>
        </label>
        <div class="tw-divider tw-my-1" />
        <label class="tw-flex tw-items-center tw-gap-2">
          <input
            v-model="form.emailSuccess"
            type="checkbox"
            name="emailsuccess"
            value="1"
            class="tw-checkbox"
            data-test="email-success"
          >
          <span class="tw-label-text">When my checkins are fixing build errors, warnings or tests</span>
        </label>
        <label class="tw-flex tw-items-center tw-gap-2">
          <input
            v-model="form.emailMissingSites"
            type="checkbox"
            name="emailmissingsites"
            value="1"
            class="tw-checkbox"
            data-test="email-missing-sites"
          >
          <span class="tw-label-text">When expected sites are not submitting</span>
        </label>
      </FormSection>

      <FormSection title="Email Categories">
        <label
          v-for="category in emailCategoryOptions"
          :key="category.value"
          class="tw-flex tw-items-center tw-gap-2"
        >
          <input
            v-model="form.emailCategories"
            type="checkbox"
            name="emailcategories[]"
            class="tw-checkbox"
            :value="category.value"
            :data-test="`email-category-${category.value}`"
          >
          <span class="tw-label-text">{{ category.text }}</span>
        </label>
      </FormSection>

      <div class="tw-flex tw-items-center tw-justify-end tw-gap-4">
        <span
          v-if="hasUnsavedChanges"
          class="tw-text-error"
          data-test="unsaved-changes"
        >
          You have unsaved changes.
        </span>
        <button
          type="submit"
          class="tw-btn tw-btn-sm tw-btn-primary"
          data-test="save-button"
        >
          Save
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import FormSection from './shared/FormSection.vue';

export default {
  name: 'ProjectNotificationsPage',

  components: {
    FormSection,
  },

  props: {
    projectId: {
      type: Number,
      required: true,
    },

    projectEmailsEnabled: {
      type: Boolean,
      required: true,
    },

    canEditProject: {
      type: Boolean,
      required: true,
    },

    emailType: {
      type: Number,
      required: true,
    },

    emailSuccess: {
      type: Boolean,
      required: true,
    },

    emailMissingSites: {
      type: Boolean,
      required: true,
    },

    emailCategories: {
      type: Array,
      required: true,
    },

    message: {
      type: String,
      default: '',
    },
  },

  data() {
    return {
      form: {
        emailType: this.emailType,
        emailSuccess: this.emailSuccess,
        emailMissingSites: this.emailMissingSites,
        emailCategories: [...this.emailCategories],
      },
    };
  },

  computed: {
    csrfToken() {
      return document.head.querySelector('meta[name="csrf-token"]')?.content;
    },

    emailTypeOptions() {
      return [
        { value: 0, text: 'Never (this is not recommended)' },
        { value: 1, text: 'When my checkins are causing problems in any section of the dashboard' },
        { value: 2, text: 'When any checkins are causing problems in the Nightly section of the dashboard' },
        { value: 3, text: 'When any checkins are causing problems in any section of the dashboard' },
      ];
    },

    emailCategoryOptions() {
      return [
        { value: 'update', text: 'Update' },
        { value: 'configure', text: 'Configure' },
        { value: 'warning', text: 'Warning' },
        { value: 'error', text: 'Error' },
        { value: 'test', text: 'Test' },
        { value: 'dynamicanalysis', text: 'Dynamic Analysis' },
      ];
    },

    hasUnsavedChanges() {
      return this.form.emailType !== this.emailType
        || this.form.emailSuccess !== this.emailSuccess
        || this.form.emailMissingSites !== this.emailMissingSites
        || this.form.emailCategories.length !== this.emailCategories.length
        || this.form.emailCategories.some((category) => !this.emailCategories.includes(category));
    },
  },
};
</script>
