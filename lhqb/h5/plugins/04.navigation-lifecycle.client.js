import { tokenStorage } from '~/utils/storage'

export default defineNuxtPlugin((nuxtApp) => {
  const router = useRouter()
  const store = nuxtApp.$store
  let pendingRouteTimer = null

  const startRouteLoading = () => {
    if (pendingRouteTimer) {
      clearTimeout(pendingRouteTimer)
      pendingRouteTimer = null
    }
    store.dispatch('setRouteLoading', true)
  }

  const stopRouteLoading = () => {
    if (pendingRouteTimer) {
      clearTimeout(pendingRouteTimer)
    }
    pendingRouteTimer = setTimeout(() => {
      store.dispatch('setRouteLoading', false)
      pendingRouteTimer = null
    }, 120)
  }

  router.beforeEach(async (to, from) => {
    startRouteLoading()
    store.dispatch('setTransitionName', '')

    if (!store.state.initInfo || !Object.keys(store.state.initInfo).length) {
      store.dispatch('getInitInfo')
    }

    const accessToken = tokenStorage.get() || ''
    if (accessToken) {
      if (!store.state.user.logged) {
        store.commit('user/SET_LOGGED', true)
        store.dispatch('user/getUserInfo').catch(() => {})
      }
    } else if (store.state.user.logged) {
      store.dispatch('user/forceLogout')
    }
    stopRouteLoading()
    return true
  })

  const resetViewportScroll = () => {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
    document.documentElement.scrollTop = 0
    document.body.scrollTop = 0
    window.dispatchEvent(new Event('resize'))
  }

  router.afterEach(() => {
    requestAnimationFrame(() => {
      resetViewportScroll()
      setTimeout(resetViewportScroll, 80)
      setTimeout(resetViewportScroll, 240)
    })
    stopRouteLoading()
  })

  router.onError(() => {
    stopRouteLoading()
  })
})
