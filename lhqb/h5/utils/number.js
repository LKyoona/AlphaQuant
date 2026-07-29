function getTag (value) {
  if (value == null) {
    return value === undefined ? '[object Undefined]' : '[object Null]'
  }
  return Object.prototype.toString.call(value)
}

function isObjectLike (value) {
  return typeof value === 'object' && value !== null
}

function isNumber (value) {
  return typeof value === 'number' ||
    (isObjectLike(value) && getTag(value) === '[object Number]') || !isNaN(value)
}

/* 科学计数法转换数值 */
export function scientificToNumber (num) {
  const str = num.toString()
  const reg = /^((?:[-]?\d+)(?:\.\d+)?)(e)([-]?\d+)$/
  let arr
  let len
  let zero = ''

  /* 6e7或6e+7 都会自动转换数值 */
  if (!reg.test(str)) {
    return str
  } else {
    /* 6e-7 需要手动转换 */
    arr = reg.exec(str)
    len = Math.abs(arr[3]) - 1
    for (let i = 0; i < len; i++) {
      zero += '0'
    }
    const minus = arr[1].includes('-') ? '-' : ''

    return minus + '0.' + zero + arr[1].replace('.', '').replace('-', '')
  }
}

/**
 * 常规格式化工具
 * abs值大于1时，尽可能少地保留小位数，最多3位小数
 * abs值小于1时，最多保留10位小数
 * @rawNum 数值
 * @decimal 最大精度
 */
export function format1 (rawNum, maxDecimal = 6, defaultLen = 2) {
  if (!isNumber(rawNum)) {
    return rawNum
  }
  const num = scientificToNumber(rawNum)
  const numStr = num.toString()
  const numArr = numStr.split('.')
  const pointLen = numArr.length > 1 ? numArr[1].length : 0
  if (Math.abs(rawNum) >= 100) {
    return pointLen > defaultLen ? Number.parseFloat(rawNum).toFixed(defaultLen) : String(num)
  }
  return pointLen > maxDecimal ? Number.parseFloat(rawNum).toFixed(maxDecimal) : String(num)
}

/**
 * 常规格式化工具（不四舍五入）
 * abs值大于1时，尽可能少地保留小位数，最多3位小数
 * abs值小于1时，最多保留10位小数
 * @rawNum 数值
 * @decimal 最大精度
 */
export function format (rawNum, maxDecimal = 6, defaultLen = 2) {
  if (!isNumber(rawNum)) {
    return rawNum
  }
  const num = scientificToNumber(rawNum)
  const numStr = num.toString()
  const numArr = numStr.split('.')
  const pointLen = numArr.length > 1 ? numArr[1].length : 0
  // if (Math.abs(rawNum) >= 100) {
  //   return pointLen > defaultLen ? numArr[0] + '.' + numArr[1].substring(0, defaultLen) : String(num)
  // }
  return pointLen > maxDecimal ? numArr[0] + '.' + numArr[1].substring(0, maxDecimal) : String(num)
}

// 格式化单价
export function priceFormat (rawNum, maxDecimal = 16, defaultLen = 2) {
  let precision
  if (maxDecimal > 8) {
    precision = 8
  } else {
    precision = Math.ceil(maxDecimal / 1)
  }
  if (!isNumber(rawNum)) {
    return rawNum
  }
  const num = scientificToNumber(rawNum)
  const numStr = num.toString()
  const numArr = numStr.split('.')
  const pointLen = numArr.length > 1 ? numArr[1].length : 0
  // if (Math.abs(rawNum) >= 100) {
  //   return pointLen > defaultLen ? Number.parseFloat(rawNum).toFixed(defaultLen) : String(num)
  // }
  if (pointLen > precision) {
    const tmp = Number.parseFloat(rawNum).toFixed(precision)
    if (1 * tmp === 0) {
      return String(num)
    }
    return tmp
  } else {
    return String(num)
  }
}

export function currency (rawNum) {
  const rawNumAbs = Math.abs(rawNum)
  if (rawNumAbs >= 100000000) {
    return format(rawNum / 100000000) + '亿'
  }
  if (rawNumAbs >= 10000) {
    return format(rawNum / 10000) + '万'
  }
  return format(rawNum)
}

export function filterNumber (str) {
  return (str + '').replace(/[^0-9+-Ee.]/g, '')
}
