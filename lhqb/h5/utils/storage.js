const storage = {
  get: (key) => {
    return localStorage.getItem(key) || ''
  },
  set: (key, value) => {
    localStorage.setItem(key, value)
    return value
  },
  remove: (key) => {
    localStorage.removeItem(key)
  },
  use: (key) => {
    return {
      get: () => {
        let value = localStorage.getItem(key) || ''
        try {
          value = JSON.parse(value)
        } catch (error) {}
        return value
      },
      set: (value) => {
        if (typeof value === 'object') {
          localStorage.setItem(key, JSON.stringify(value))
        } else {
          localStorage.setItem(key, value)
        }
        return value
      },
      remove: () => {
        localStorage.removeItem(key)
      }
    }
  }
}

export const tokenStorage = storage.use('USER_TOKEN')
export const robotStorage = storage.use('ROBOT_INFO')
export const currencyStorage = storage.use('CURRENCY')
export const langStorage = storage.use('LANG')
export default storage
