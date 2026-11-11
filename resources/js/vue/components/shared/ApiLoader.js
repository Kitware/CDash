import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

export default {
  /**
   * Show the API endpoint, the testing day and the time taken to generate the page in the page's footer.
   *
   * @param {Object} cdash The API response, with its endpoint's URL added as `endpoint`.
   * @param {number} start When the page started loading the response, in milliseconds.
   */
  showInFooter: function (cdash, start) {
    // Add vuejs render time to page load time in footer.
    const renderTime = +((new Date().getTime() - start) / 1000);
    if (cdash.generationtime === undefined) {
      cdash.generationtime = 0;
    }
    let generationTimeStr = (renderTime + cdash.generationtime).toFixed(2);
    generationTimeStr += `s (${cdash.generationtime}s)`;
    cdash.generationtime = generationTimeStr;

    // A brutal hack to populate the footer with content
    // TODO: (williamjallen) Clean this up
    if (document.getElementById('api-endpoint')) {
      document.getElementById('api-endpoint')?.setAttribute('href', cdash.endpoint);
    }
    if (document.getElementById('api-endpoint-container')) {
      document.getElementById('api-endpoint-container').style.display = 'inline';
    }
    if (document.getElementById('generation-time')) {
      document.getElementById('generation-time').textContent = cdash.generationtime;
    }
    if (document.getElementById('testing-day') && cdash.nightlytime !== undefined) {
      document.getElementById('testing-day').textContent = `| Testing day ${cdash.currentdate} started at ${cdash.nightlytime}`;
    }
  },

  loadPageData: function (vm, endpoint_path) {
    vm.start = new Date().getTime();
    axios
      .get(endpoint_path)
      .then((response) => {
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

        this.showInFooter(vm.cdash, vm.start);
      })
      .catch((error) => {
        console.log(error);
        vm.errored = true;
      })
      .finally(() => vm.loading = false);
  },
};
