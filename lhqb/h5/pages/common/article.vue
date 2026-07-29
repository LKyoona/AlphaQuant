<template>
  <div>
    <van-nav-bar fixed placeholder left-arrow :title="info.title" @click-left="$router.back()" />
    <iframe :src="info.url" class="iframe" frameborder="0"></iframe>
  </div>
</template>

<script>
export default {
  data () {
    return {
      info: ''
    }
  },
  created () {
    this.info = this.$route.params
  }
}
</script>

<style scoped>
.iframe {
  width: 100vw;
  height: calc(100vh - 46px);
}
input{
    outline: none;
}

input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button{
  -webkit-appearance: none !important;
  margin: 0;
}
</style>
