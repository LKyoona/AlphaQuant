import API from '@/constants/api'
export const state = () => ({
  list: [],
  tickerAll: []
})

export const mutations = {
  SET_BANNER (state, data) {
    state.banner = data
  },
  SET_TICKER_ALL (state, data) {
    state.tickerAll = data
  },
  SET_TICKER (state, data) {
    state.list = data
  }
}

export const actions = {
  async getTickerAll ({ commit }, params) {
    const result = await this.$axios.$post(API.TICKER_ALL, params)
    const { data } = result
    commit('SET_TICKER_ALL', data.list)
    return result
  },
  async getTickerList ({ commit }, params) {
    const result = await this.$axios.$post(API.TICKER_LIST, params)
    const { data } = result
    commit('SET_TICKER', data.list)
    return result
  },
  async addTicker ({ commit }, params) {
    return await this.$axios.$post(API.TICKER_ADD, params)
  },
  async removeTicker ({ commit }, params) {
    return await this.$axios.$post(API.TICKER_REMOVE, params)
  },
  async sortTicker ({ commit }, params) {
    return await this.$axios.$post(API.TICKER_SORT, params)
  }
}
