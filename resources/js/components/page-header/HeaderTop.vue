<template>
    <div id="headertop">
        <div id="topmenu">
            <a :class="{ 'hidden': loggedIn}"  href="/login">Login</a>
            <a :class="{ 'hidden': loggedIn}"  href="register.php">Register</a>
            <a :class="{ 'hidden': !loggedIn}" href="user.php">My CDash</a>
            <a :class="{ 'hidden': !loggedIn}" href="/logout">Log out</a>
            <a :class="{ 'hidden': isHome}"   :href="home">All Dashboards</a>
        </div>
    </div>
</template>

<script>
    export default {
        name: "HeaderTop",
        props: ['user', 'location'],
        data () {
            return {
                home: 'viewProjects.php'
            }
        },

        computed: {
            loggedIn () {
                return this.user && this.user.id;
            },

            isHome () {
                return this.location === this.home;
            },
        },
    }
</script>

<style scoped>
 .hidden {
     display: none;
 }
</style>
