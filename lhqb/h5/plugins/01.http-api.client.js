import axios from 'axios'
import qs from 'qs'
import { tokenStorage } from '~/utils/storage'

export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const router = useRouter()
  const api = axios.create({
    baseURL: config.public.apiBase,
    headers: {
      'XX-Device-Type': 'web',
      'Content-Type': 'application/x-www-form-urlencoded'
    }
  })

  api.interceptors.request.use((request) => {
    const store = nuxtApp.$store
    const params = {
      language: ['zh', 'tw'].includes(store?.state?.locale) ? 'zh_cn' : 'en_us',
      ...request.data
    }

    if (!request.headers['XX-Token']) {
      const accessToken = tokenStorage.get() || ''
      store?.commit('user/SET_USER_TOKEN', accessToken)
      request.headers['XX-Token'] = accessToken
    }

    if (request.headers['Content-Type'] !== 'multipart/form-data') {
      request.data = qs.stringify(params)
    }

    return request
  })

  api.interceptors.response.use(
    (response) => {
      const { data } = response
      const requestFailed = data?.code !== 1
      const authExpired = data?.code === 401 || data?.code === 10001 || (
        requestFailed && /login|token|unauthor/i.test(data?.msg || data?.message || '')
      )
      if (authExpired) {
        nuxtApp.$store.dispatch('user/forceLogout').finally(() => {
          if (router.currentRoute.value.path !== '/sign/login') {
            router.replace('/sign/login')
          }
        })
        return Promise.reject(data)
      }
      if (data?.code !== 1) {
        return Promise.reject(data)
      }
      return response
    },
    (error) => {
      const payload = error?.response?.data || error
      const authExpired = payload?.code === 401 || payload?.code === 10001 || /login|token|unauthor/i.test(payload?.msg || payload?.message || '')
      if (authExpired) {
        nuxtApp.$store.dispatch('user/forceLogout').finally(() => {
          if (router.currentRoute.value.path !== '/sign/login') {
            router.replace('/sign/login')
          }
        })
      }
      return Promise.reject(payload)
    }
  )

  const legacyAxios = {
    ...api,
    $get: (url, config) => api.get(url, config).then(response => response.data),
    $post: (url, data, config) => api.post(url, data, config).then(response => response.data)
  }

  nuxtApp.provide('axios', legacyAxios)
})
