<template>
  <div
    class="tw-flex tw-flex-row tw-gap-8 tw-items-start 2xl:tw-w-3/4 xl:tw-w-4/5 tw-w-full"
    data-test="project-settings-page"
  >
    <ul class="tw-menu tw-bg-base-200 tw-rounded-box tw-min-w-56">
      <li>
        <a
          :class="{'tw-active': currentSection === 'general'}"
          data-test="general-tab-link"
          @click="currentSection = 'general'"
        >General</a>
      </li>

      <li>
        <a
          :class="{'tw-active': currentSection === 'integrations'}"
          data-test="integrations-tab-link"
          @click="currentSection = 'integrations'"
        >Integrations</a>
      </li>

      <li>
        <a
          :href="`${$baseURL}/manageBuildGroup.php?projectid=${projectId}`"
        >Build Groups</a>
      </li>

      <li>
        <a
          :href="`${$baseURL}/projects/${projectId}/testmeasurements`"
        >Test Measurements</a>
      </li>

      <li>
        <a
          :href="`${$baseURL}/manageSubProject.php?projectid=${projectId}`"
        >SubProject Groups</a>
      </li>

      <li>
        <a
          :href="`${$baseURL}/manageOverview.php?projectid=${projectId}`"
        >Overview Configuration</a>
      </li>

      <li>
        <a
          :href="`${$baseURL}/projects/${projectId}/ctest_configuration`"
        >CTest Configuration</a>
      </li>
    </ul>

    <GeneralTab
      v-if="currentSection === 'general'"
      :ldap-enabled="ldapEnabled"
      :project-id="projectId"
    />

    <IntegrationsTab
      v-if="currentSection === 'integrations'"
      :project-id="projectId"
    />
  </div>
</template>

<script>
import GeneralTab from './ProjectSettings/GeneralTab.vue';
import IntegrationsTab from './ProjectSettings/IntegrationsTab.vue';

export default {
  components: {GeneralTab,IntegrationsTab},
  props: {
    projectId: {
      type: Number,
      required: true,
    },

    ldapEnabled: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      currentSection: 'general',
    };
  },
};
</script>
