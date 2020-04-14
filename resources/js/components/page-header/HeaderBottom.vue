<template>
  <div id="headerbottom">
    <div id="headerlogo">
      <a :href="$baseURL"><img
        id="projectlogo"
        alt="Logo"
        :src="logo"
      ></a>
    </div>
    <div id="headername">
      <span class="projectname">{{ projectname }}</span>
      <span class="pagename">{{ pagename }}</span>
    </div>
    <HeaderNav
      v-if="showNav"
      :previous="previous"
      :current="current"
      :next="next"
    />
    <HeaderMenu
      :date="date"
      :projectname="projectname"
    />
  </div>
</template>

<script>
import HeaderNav from "./HeaderNav";
import HeaderMenu from "./HeaderMenu";
export default {
  name: "HeaderBottom",
  components: {HeaderNav, HeaderMenu},
  props: {
    date: {
      type: String,
      default: '',
    },
    projectname: {
      type: String,
      default: 'CDash',
    },
    pagename: {
      type: String,
      default: '',
    },
    previous: {
      type: String,
      default: '',
    },
    current: {
      type: String,
      default: '',
    },
    next: {
      type: String,
      default: '',
    },
  },

  data () {
    return {
      logo: `${this.$baseURL}/img/cdash.png`,
      showNav: false,
    }
  },

  mounted() {
    this.$root.$on('api-loaded', cdash => {
      if (cdash.logoid > 0) {
        this.logo = `${this.$baseURL}/image/${cdash.logoid}`;
        if (!cdash.hidenav) {
          this.showNav = true;
        }
      }
    });
  },
}
</script>

<style scoped>
#projectlogo {
    height: 50px;
    border: none;
}
</style>
