import { IS_STANDALONE } from '@/config/index'
import binanceLogo from '@/assets/images/binance.png'
export const CNY = 'CNY'
export const USD = 'USD'
export const CURRENCIES = {
  [CNY]: {
    name: CNY,
    label: '人民币',
    symbol: '¥'
  },
  [USD]: {
    name: USD,
    label: '美元',
    symbol: '$'
  }
}

export const THIRD_LOGIN_ENABLED = !IS_STANDALONE
const platform = [
  {
    name: '币安',
    label: 'binance',
    logo: binanceLogo
  }
]
export const PLATFORM = platform
