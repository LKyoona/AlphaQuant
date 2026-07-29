import { SET_TRANSITION_NAME, SET_LANG } from '@/constants/mutation-types'
import { CURRENCIES, THIRD_LOGIN_ENABLED } from '@/constants/global'
import { currencyStorage, langStorage } from '@/utils/storage'
import API from '@/constants/api'

const currency = CURRENCIES[Object.keys(CURRENCIES)[1]]

export const state = () => ({
  transitionName: '',
  routeLoading: false,
  initInfo: {},
  banner: [],
  currency, // 货币设置，
  locale: langStorage.get(),
  thirdLoginEnabled: THIRD_LOGIN_ENABLED
})

export const mutations = {
  [SET_LANG] (state, locale) {
    state.locale = locale
  },
  [SET_TRANSITION_NAME] (state, value) {
    state.transitionName = value
  },
  SET_ROUTE_LOADING (state, value) {
    state.routeLoading = value
  },
  SET_INIT_INFO (state, data) {
    state.initInfo = data
  },
  SET_BANNER (state, data) {
    state.banner = data
  }
}

export const actions = {
  setLang ({ commit }, locale) {
    langStorage.set(locale)
    commit(SET_LANG, locale)
  },
  setTransitionName ({ commit }, value) {
    commit(SET_TRANSITION_NAME, value)
  },
  setRouteLoading ({ commit }, value) {
    commit('SET_ROUTE_LOADING', value)
  },
  async upload (content, file) {
    const formData = new FormData()
    formData.append('file', file)
    return await this.$axios.$post(API.UPLOAD_ONE, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
  async getInitInfo ({ commit }) {
    const { data } = await this.$axios.$post(API.INIT_INFO)
    commit('SET_INIT_INFO', data)
  },
  async getBanner ({ commit }) {
    const { data } = await this.$axios.$post(API.BANNER)
    commit('SET_BANNER', data)
  },
  async getNotice ({ commit }, params) {
    return await this.$axios.$post(API.NOTICE_LISTS, params)
  }
}
