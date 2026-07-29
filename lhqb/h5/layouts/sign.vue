<template>
  <div class="sign-layout">
    <div class="sign-layout__scroll">
      <slot />
    </div>
  </div>
</template>

<script>
export default {
  name: 'SignLayout',
  mounted () {
    this.resetPageScroll()
  },
  beforeUnmount () {
    if (document.activeElement && document.activeElement.blur) {
      document.activeElement.blur()
    }
    this.resetPageScroll()
    this.$toast?.clear?.()
  },
  methods: {
    resetPageScroll () {
      window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
      document.documentElement.scrollTop = 0
      document.body.scrollTop = 0
    }
  }
}
</script>

<style scoped lang="less">
.sign-layout {
  position: fixed;
  inset: 0;
  width: 100%;
  height: calc(var(--app-vh, 1vh) * 100);
  overflow: hidden;
  background: #0f1218;
}

.sign-layout__scroll {
  width: 100%;
  height: 100%;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}
</style>
