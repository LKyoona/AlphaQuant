import en from './en'
import ptBr from './pt'
import zh from './zh'

// This is the only H5 registry to update when adding a language package.
export const languageOptions = {
  en: { label: 'English', apiLanguage: 'en_us', messages: en },
  zh: { label: '中文', apiLanguage: 'zh_cn', messages: zh },
  pt_br: { label: 'Português (Brasil)', apiLanguage: 'pt_br', messages: ptBr }
}

export const defaultLocale = 'en'
export const fallbackLocale = 'en'

export const messages = Object.keys(languageOptions).reduce((result, locale) => {
  result[locale] = languageOptions[locale].messages
  return result
}, {})

export function getApiLanguage(locale) {
  return languageOptions[locale]?.apiLanguage || languageOptions[fallbackLocale].apiLanguage
}

export function getLocaleLabel(locale) {
  return languageOptions[locale]?.label || languageOptions[fallbackLocale].label
}
