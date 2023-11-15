<template>
  <img
    id="projectlogo"
    alt="Logo"
    :src="logoUrl"
  >
</template>

<script>
import ApiLoader from '../shared/ApiLoader';
export default {
  name: "HeaderLogo",

  data () {
    return {
      logoUrl: `${this.$baseURL}/img/cdash.svg`,
      showNav: false,
    }
  },

  mounted() {
    ApiLoader.$on('api-loaded', cdash => {
      if (cdash.logoid > 0) {
        this.logoUrl = `${this.$baseURL}/image/${cdash.logoid}`;
      }
    });
  },
}
</script>
