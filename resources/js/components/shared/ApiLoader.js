import emitter from 'tiny-emitter/instance';

export default {
  // Replacement emitters for the Vue 2 -> 3 conversion.  TODO: Remove these eventually.
  // See: https://v3-migration.vuejs.org/breaking-changes/events-api.html#event-bus
  $on: (...args) => emitter.on(...args),
  $emit: (...args) => emitter.emit(...args),

  loadPageData: function (vm, endpoint_path) {
    vm.start = new Date().getTime();
    vm.$axios
      .get(endpoint_path)
      .then(response => {
        // Pre-assigment hook for components.
        if (typeof vm.preSetup === 'function') {
          vm.preSetup(response);
        }

        vm.cdash = response.data;
        vm.cdash.endpoint = vm.$baseURL + endpoint_path;

        // Post-assigment hook for components.
        if (typeof vm.postSetup === 'function') {
          vm.postSetup(response);
        }

        // Add vuejs render time to page load time in footer.
        const renderTime = +((new Date().getTime() - vm.start) / 1000);
        if (vm.cdash.generationtime === undefined) {
          vm.cdash.generationtime = 0;
        }
        let generationTimeStr = (renderTime + vm.cdash.generationtime).toFixed(2);
        generationTimeStr += `s (${vm.cdash.generationtime}s)`;
        vm.cdash.generationtime = generationTimeStr;


        // A brutal hack to populate the footer with content
        // TODO: (williamjallen) Clean this up
        if (document.getElementById('api-endpoint')) {
          document.getElementById('api-endpoint')?.setAttribute('href', vm.cdash.endpoint);
        }
        if (document.getElementById('generation-time')) {
          document.getElementById('generation-time').textContent = vm.cdash.generationtime;
        }
        if (document.getElementById('testing-day') && vm.cdash.nightlytime !== undefined) {
          document.getElementById('testing-day').textContent = `Current Testing Day ${vm.cdash.currentdate} | Started at ${vm.cdash.nightlytime}`;
        }

        // Let other components know that data has been loaded from the API.
        this.$emit('api-loaded', vm.cdash);
      })
      .catch(error => {
        console.log(error);
        vm.errored = true;
      })
      .finally(() => vm.loading = false);
  },
};
