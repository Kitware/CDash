<template>
  <div>
    <HeaderTop :user="user" />
    <HeaderBottom
      :date="date"
      :logo="logo"
      :projectname="projectname"
    />
  </div>
</template>

<script>
import HeaderTop from "./page-header/HeaderTop";
import HeaderBottom from "./page-header/HeaderBottom";
export default {
  name: "pageHeader",
  components: {HeaderBottom, HeaderTop},
  props: {
    date: {
      type: String,
      default: '',
    },
    logo: {
      type: String,
      default: '',
    },
    projectname: {
      type: String,
      default: '',
    },
    user: {
      type: Object,
      default() {
        return {}
      },
    },
  },
}
</script>

<style scoped>

</style>
