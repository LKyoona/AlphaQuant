export const isEmail = email => /^[\w.-]+@\w+([.-]\w+)*\.\w+$/.test(email)
export const isPhone = phone => /^1[3-9]\d{9}$/.test(phone)
export const isEmailOrPhone = account => isEmail(account) || isPhone(account)
