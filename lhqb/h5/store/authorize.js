import API from '@/constants/api'
import { PLATFORM } from '@/constants/global'

export const state = () => ({
  platform: PLATFORM
})

export const mutations = {
  SET_API_INFO(state, data) {
    state.platform[data[0]] = Object.assign(state.platform[data[0]], data[1])
  }
}

export const actions = {
  async getApiAccount({ commit }, params) {
    return await this.$axios.$post(API.API_ACCOUNT, params)
  },
  async editApiAccount({ commit }, params) {
    return await this.$axios.$post(API.API_ACCOUNT_EDIT, params)
  },
  async addFutureAccount({ commit }, params) {
    return await this.$axios.$post(API.API_ACCOUNT_FUTURE_ADD, params)
  },
  async getFutureApiAccount({ commit }, params) {
    return await this.$axios.$post(API.API_ACCOUNT_FUTURE_INFO, params)
  },
  async removeApiAccount({ commit }, params) {
    return await this.$axios.$post(API.API_ACCOUNT_REMOVE, params)
  },
  setApiInfo({ commit }, data) {
    commit('SET_API_INFO', data)
  },
  async apiAccountBalance({ commit }, params) {
    return await this.$axios.$post(API.API_ACCOUNT_BALANCE, params)
  },
  async apiAccountFutureBalance({ commit }, params) {
    return await this.$axios.$post(API.API_ACCOUNT_FUTURE_BALANCE, params)
  }
}
