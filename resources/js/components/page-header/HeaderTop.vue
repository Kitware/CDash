<template>
  <div id="headertop">
    <div
      id="topmenu"
      class="fill"
    >
      <a
        id="cdash-login-link"
        :class="{ 'hidden': loggedIn }"
        :href="$baseURL + '/login'"
      >Login</a>
      <a
        id="cdash-register-link"
        :class="{ 'hidden': loggedIn }"
        :href="$baseURL + '/register'"
      >Register</a>
      <a
        id="cdash-profile-link"
        :class="{ 'hidden': !loggedIn }"
        :href="$baseURL + '/user.php'"
      >My CDash</a>
      <a
        id="cdash-logout-link"
        :class="{ 'hidden': !loggedIn, 'float-right' : true }"
        :href="$baseURL + '/logout'"
      >Log out</a>
      <a
        id="cdash-home-link"
        :class="{ 'hidden': isHome}"
        :href="$baseURL + '/viewProjects.php'"
      >All Dashboards</a>
    </div>
  </div>
</template>

<script>
export default {
  name: "HeaderTop",

  props: {
    user: {
      type: Object,
      default() {
        return {}
      },
    },
  },

  computed: {
    loggedIn () {
      return this.user.id > 0;
    },

    isHome () {
      return false;
    },
  },
}
</script>

<style scoped>
 .hidden {
     display: none;
 }
 .float-right {
     position: absolute;
     right: 5px;
     top: -3px;
 }

</style>
