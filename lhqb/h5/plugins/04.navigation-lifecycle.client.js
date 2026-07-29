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

    const accessToken = tokenStorage.get() || ''
    const isEntryRoute = to.path === '/'
    const isLoginRoute = to.path === '/sign/login'
    const isTokenLogin = isEntryRoute && Boolean(to.query.token)

    // The app entry must never mount the home page or request init before auth is known.
    if (!accessToken && isEntryRoute && !isTokenLogin) {
      if (store.state.user.logged) {
        await store.dispatch('user/forceLogout')
      }
      stopRouteLoading()
      return { path: '/sign/login', replace: true }
    }

    if (accessToken) {
      if (!store.state.user.logged) {
		try {
		  await store.dispatch('user/getUserInfo')
		} catch (error) {
		  await store.dispatch('user/forceLogout')
		  if (isEntryRoute) {
			stopRouteLoading()
			return { path: '/sign/login', replace: true }
		  }
		}
      }
    } else if (store.state.user.logged) {
	  await store.dispatch('user/forceLogout')
	}

	// The login page does not depend on platform initialization.
	if (!isLoginRoute && !isTokenLogin && (!store.state.initInfo || !Object.keys(store.state.initInfo).length)) {
	  store.dispatch('getInitInfo').catch(() => {})
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
