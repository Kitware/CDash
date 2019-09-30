<template>
    <div id="headertop">
        <div id="topmenu" class="fill">
            <a :class="{ 'hidden': loggedIn }"  href="/login">Login</a>
            <a :class="{ 'hidden': loggedIn }"  href="register.php">Register</a>
            <a :class="{ 'hidden': !loggedIn }" href="user.php">My CDash</a>
            <a :class="{ 'hidden': !loggedIn, 'float-right' : true }" href="/logout">Log out</a>
            <a :class="{ 'hidden': isHome}"   :href="menu.home">All Dashboards</a>
        </div>
    </div>
</template>

<script>
    export default {
        name: "HeaderTop",
        props: ['user', 'pathname', 'menu'],

        computed: {
            loggedIn () {
                return this.user && this.user.id;
            },

            isHome () {
                return this.menu.home === this.pathname;
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
