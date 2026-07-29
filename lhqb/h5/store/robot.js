import API from '@/constants/api'
import { PLATFORM } from '@/constants/global'

let pendingRobotListRequest = null

export const state = () => ({
  platform: PLATFORM,
  marketData: { okex: [], huobi: [], binance: [], gateio: [], sinance: [] },
  robotList: []
})

export const getters = {
  markets: state => (platform) => {
    return state.marketData[platform]
  },
  robot: state => (id) => {
    const marketId = String(id ?? '')
    return state.robotList.find(item => String(item.market_id ?? '') === marketId)
  }
}

export const mutations = {
  SET_ROBOT_LIST(state, data) {
    state.robotList = data
  },
  SET_MARKET_LIST(state, data) {
    state.marketData[data[0]] = data[1]
  }
}

export const actions = {
  async marketList({ commit }, params) {
    const result = await this.$axios.$post(API.MARKET_LIST, params)
    const { data } = result
    commit('SET_MARKET_LIST', [params.platform, data])
    return result
  },
  async robotList({ commit }, params) {
    if (pendingRobotListRequest) {
      return pendingRobotListRequest
    }

    pendingRobotListRequest = this.$axios.$post(API.ROBOT_LIST, params)
      .then((result) => {
        const data = Array.isArray(result.data) ? result.data : []
        data.forEach((item) => {
          item.values = {}
          if (item.values_str) {
            try {
              item.values = JSON.parse(item.values_str)
            } catch (error) {
              item.values = {}
            }
          }
        })
        commit('SET_ROBOT_LIST', data)
        return result
      })
      .finally(() => {
        pendingRobotListRequest = null
      })

    return pendingRobotListRequest
  },
  async robotCreate({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_CREATE, params)
  },
  async robotEdit({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_EDIT, params)
  },
  async robotDisable({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_DISABLE, params)
  },
  async robotEnable({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_ENABLE, params)
  },
  async robotClean({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_CLEAN, params)
  },
  async robotLog({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_LOG, params)
  },
  async robotOrder({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_ORDER, params)
  },
  async robotRevenue({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_REVENUE, params)
  },
  async publicTicker({ commit }, params) {
    return await this.$axios.$post(API.PUBLIC_TICKER, params)
  }
}
