<template>
  <div v-if="isLoading">
    <img
      v-if="initialDelayComplete"
      :src="$baseURL + '/img/loading.gif'"
      class="loading-indicator"
      alt="The page is loading."
    >
  </div>
  <!-- eslint-disable-next-line vue/no-multiple-template-root -->
  <slot v-else />
</template>

<script>
export default {
  props: {
    isLoading: {
      type: Boolean,
      default: true,
    },
  },

  data() {
    return {
      initialDelayComplete: false,
    };
  },

  mounted() {
    // Prevent a flicker if the page loads quickly
    setTimeout(() => {
      this.initialDelayComplete = true;
    }, 200);
  },
};
</script>

<style scoped>
.loading-indicator {
  margin: 0 auto;
  display: block;
}
</style>
