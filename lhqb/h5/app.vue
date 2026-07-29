<template>
  <div class="app-shell">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <div
      v-if="$store.state.routeLoading"
      class="route-loading-pill"
      aria-live="polite"
      role="status"
    >
      <div class="route-loading-spinner"></div>
      <div class="route-loading-text">{{ $t('loading') || 'Loading...' }}</div>
    </div>
  </div>
</template>

<style lang="less">
.app-shell {
  min-height: 100vh;
}

.route-loading-pill {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 3000;
  width: 100%;
  height: 3px;
  overflow: hidden;
  background: rgba(240, 196, 110, 0.12);
  pointer-events: none;
}

.route-loading-spinner {
  width: 42%;
  height: 100%;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent, #f0c46e, #fff0bd, transparent);
  box-shadow: 0 0 12px rgba(240, 196, 110, 0.7);
  animation: route-slide 0.8s ease-in-out infinite;
}

.route-loading-text {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

@keyframes route-slide {
  from {
    transform: translateX(-110%);
  }
  to {
    transform: translateX(340%);
  }
}
</style>
