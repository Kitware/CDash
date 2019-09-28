<template>
    <div>
        <HeaderTop></HeaderTop>
        <HeaderBottom></HeaderBottom>
    </div>
</template>

<script>
    import HeaderTop from "./page-header/HeaderTop";
    import HeaderBottom from "./page-header/HeaderBottom";
    export default {
        name: "Header",
        components: {HeaderBottom, HeaderTop}
    }
</script>

<style scoped>

</style>
