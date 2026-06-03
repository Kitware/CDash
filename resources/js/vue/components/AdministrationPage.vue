<template>
  <div class="tw-container tw-mx-auto tw-p-4 tw-max-w-screen-lg">
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-6">
      <AdministrationPageCard
        href="/projects/new"
        title="Create Project"
        :icon="FA.faFolderPlus"
      />
      <AdministrationPageCard
        href="/users"
        title="Manage Users"
        :icon="FA.faUsers"
      />
      <AdministrationPageCard
        href="/authtokens/manage"
        title="Manage Authentication Tokens"
        :icon="FA.faKey"
      />
      <AdministrationPageCard
        href="/monitor"
        title="Submission Status"
        :icon="FA.faListCheck"
      />
    </div>
  </div>
</template>

<script>
import AdministrationPageCard from './shared/AdministrationPageCard.vue';
import {
  faFolderPlus,
  faUsers,
  faKey,
  faChartBar,
  faTrashCan,
  faListCheck,
} from '@fortawesome/free-solid-svg-icons';

export default {
  name: 'AdministrationPage',
  components: {
    AdministrationPageCard,
  },
  computed: {
    FA() {
      return {
        faFolderPlus,
        faUsers,
        faKey,
        faChartBar,
        faTrashCan,
        faListCheck,
      };
    },
  },
};
</script>
