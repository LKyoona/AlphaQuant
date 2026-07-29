import API from '@/constants/api'

export const actions = {
  async packageList ({ commit }, params) {
    return await this.$axios.$post(API.PACKAGE_LIST, params)
  },
  async packageBuy ({ commit }, params) {
    return await this.$axios.$post(API.PACKAGE_BUY, params)
  }
}
