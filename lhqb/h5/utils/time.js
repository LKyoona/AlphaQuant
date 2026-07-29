export function format (time, cFormat) {
  if (arguments.length === 0) {
    return null
  }
  const format = cFormat || '{y}-{m}-{d} {h}:{i}:{s}'
  let date
  if (typeof time === 'object') {
    date = time
  } else {
    if (('' + parseInt(time)).length <= 10) { time *= 1000 }
    date = new Date(time)
  }
  const easternDate = new Date(date.toLocaleString('en-US', { timeZone: 'America/New_York' }))
  const formatObj = {
    y: easternDate.getFullYear(),
    m: easternDate.getMonth() + 1,
    d: easternDate.getDate(),
    h: easternDate.getHours(),
    i: easternDate.getMinutes(),
    s: easternDate.getSeconds(),
    a: easternDate.getDay()
  }
  const timeStr = format.replace(/{(y|m|d|h|i|s|a)+}/g, (result, key) => {
    let value = formatObj[key]
    // Note: getDay() returns 0 on Sunday
    if (key === 'a') { return ['日', '一', '二', '三', '四', '五', '六'][value] }
    if (result.length > 0 && value < 10) {
      value = '0' + value
    }
    return value || 0
  })
  return timeStr
}
