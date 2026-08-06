<template>
  <div class="page">
    <div class="page-content">
      <slot />
    </div>
    <nav-bar />
  </div>
</template>

<script>
import NavBar from '@/components/NavBar'
export default {
  name: 'Navigation',
  transition: 'page',
  components: { NavBar }
}
</script>

<style lang="less" scoped>
.page {
  position: relative;
  width: 100%;
  min-height: 100%;
  display: flex;
  flex-direction: column;
  overflow: visible;
  background:
    radial-gradient(circle at top left, rgba(246, 204, 113, 0.18), transparent 24%),
    radial-gradient(circle at 84% 10%, rgba(161, 118, 39, 0.12), transparent 18%),
    linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
}

.page-content {
	width: 100%;
	min-height: 100dvh;
  position: relative;
  z-index: 1;
	padding-top: var(--page-top-gap-nav, 8px);
	padding-bottom: calc(var(--bottom-nav-height, 82px) + env(safe-area-inset-bottom) + 12px);
  overflow-x: hidden;
  overflow-y: visible;
}

@media (min-width: 768px) {
  .page-content {
    padding-top: 0;
  }
}
</style>
