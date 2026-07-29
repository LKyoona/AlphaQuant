export const WEB_URL = process.env.NUXT_PUBLIC_API_BASE || '/'
export const IMG_BASE_URL = process.env.NUXT_PUBLIC_IMG_BASE || `${WEB_URL}upload/`

// 部署路径
export const DEPLOY_BASE = '/app'
// true: 独立账号登录, false: 第三方授权登录
export const IS_STANDALONE = true

// 代理功能
export const CHEKC_HEHUO = false
export const CHEKC_PROXY = false
export const CHEKC_PROXY_UPDATE = false

// 激活码功能
export const CHEKC_CDKEY = false

// 转盘抽奖
export const INTEGR_ALDRAW = false

// ERC20
export const CHEKC_ERC = false

// 购买套餐
export const BUY_PACKAGE = false
