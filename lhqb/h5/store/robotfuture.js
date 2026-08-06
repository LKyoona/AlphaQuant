import API from '@/constants/api'
import { FUTURE_PLATFORM } from '@/constants/global'
export const state = () => ({
  platform: FUTURE_PLATFORM,
  marketData: { okex: [], huobi: [], binance: [], gateio: [], sinance: [] },
  robotList: [],
  recommendList: ['SFP/USDT','RUNE/USDT','ENJ/USDT'],
  futureList: ['BTC/USDT', 'BNB/USDT', 'ETH/USDT', 'BCH/USDT', 'XRP/USDT', 'EOS/USDT', 'LTC/USDT', 'TRX/USDT', 'ETC/USDT', 'LINK/USDT', 'XLM/USDT', 'ADA/USDT', 'XMR/USDT', 'DASH/USDT', 'ZEC/USDT', 'XTZ/USDT', 'ATOM/USDT', 'ONT/USDT', 'IOTA/USDT', 'BAT/USDT', 'VET/USDT', 'NEO/USDT', 'QTUM/USDT', 'IOST/USDT', 'THETA/USDT', 'ALGO/USDT', 'ZIL/USDT', 'KNC/USDT', 'ZRX/USDT', 'COMP/USDT', 'OMG/USDT', 'DOGE/USDT', 'SXP/USDT', 'KAVA/USDT', 'BAND/USDT', 'RLC/USDT', 'WAVES/USDT', 'MKR/USDT', 'SNX/USDT', 'DOT/USDT', 'DEFI/USDT', 'YFI/USDT', 'BAL/USDT', 'CRV/USDT', 'YFII/USDT', 'RUNE/USDT', 'SUSHI/USDT', 'SRM/USDT', 'BZRX/USDT', 'EGLD/USDT', 'SOL/USDT', 'ICX/USDT', 'STORJ/USDT', 'BLZ/USDT', 'UNI/USDT', 'AVAX/USDT', 'FTM/USDT', 'HNT/USDT', 'ENJ/USDT', 'FLM/USDT', 'TOMO/USDT', 'REN/USDT', 'KSM/USDT', 'NEAR/USDT', 'AAVE/USDT', 'FIL/USDT', 'RSR/USDT', 'LRC/USDT', 'MATIC/USDT', 'OCEAN/USDT', 'CVC/USDT', 'BEL/USDT', 'CTK/USDT', 'AXS/USDT', 'ALPHA/USDT', 'ZEN/USDT', 'SKL/USDT', 'GRT/USDT', '1INCH/USDT', 'AKRO/USDT', 'CHZ/USDT', 'SAND/USDT', 'ANKR/USDT', 'LUNA/USDT', 'BTS/USDT', 'LIT/USDT', 'UNFI/USDT', 'DODO/USDT', 'REEF/USDT', 'RVN/USDT', 'SFP/USDT', 'XEM/USDT', 'BTCST/USDT', 'COTI/USDT', 'CHR/USDT', 'MANA/USDT', 'ALICE/USDT', 'HBAR/USDT', 'ONE/USDT', 'LINA/USDT', 'STMX/USDT', 'DENT/USDT', 'CELR/USDT', 'HOT/USDT', 'MTL/USDT', 'OGN/USDT', 'BTT/USDT', 'NKN/USDT', 'SC/USDT', 'ICP/USDT', 'BAKE/USDT', 'GTC/USDT', 'BTCDOM/USDT', 'KEEP/USDT', 'TLM/USDT']
})

export const getters = {
  markets: state => (platform) => {
    return state.marketData[platform]
  },
  robot: state => (id) => {
    return state.robotList.filter(item => item.market_id === id)[0]
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
    const result = await this.$axios.$post(API.ROBOT_FUTURE_LIST, params)
    const { data } = result
    data.forEach((item) => {
      item.values = {}
      if (item.values_str) { item.values = JSON.parse(item.values_str) }
    })
    commit('SET_ROBOT_LIST', data)
    return result
  },
  async robotCreate({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_FUTURE_CREATE, params)
  },
  async robotEdit({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_FUTURE_EDIT, params)
  },
  async robotDisable({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_FUTURE_DISABLE, params)
  },
  async robotEnable({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_FUTURE_ENABLE, params)
  },
  async robotClean({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_FUTURE_CLEAN, params)
  },
  async robotRevenue({ commit }, params) {
    return await this.$axios.$post(API.ROBOT_FUTURE_REVENUE, params)
  }
}
