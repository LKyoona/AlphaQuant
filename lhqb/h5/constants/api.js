export default {
  UPLOAD_ONE: 'api/user/upload/one', // 文件上传

  INIT_INFO: 'api/home/main/init', // 初始化信息
  CHECK_UPDATE: 'api/home/main/checkUpdate', // 更新信息

  BANNER: 'api/home/main/banner', // 首页Banner
  NOTICE_LISTS: 'api/home/notice/lists', // 公告文章列表

  TICKER_ALL: 'api/home/ticker/all', // 所有行情
  TICKER_LIST: 'api/home/ticker/lists', // 我的行情
  TICKER_ADD: 'api/home/ticker/add', // 添加行情
  TICKER_REMOVE: 'api/home/ticker/delete', // 移除行情
  TICKER_SORT: 'api/home/ticker/sort', // 行情排序

  SEND_VERIFICATION_CODE: 'api/user/verification_code/send', // 验证码
  REGISTER: 'api/user/public/register', // 注册
  LOGIN: 'api/user/public/login', // 登录
  CODE_LOGIN: 'api/user/public/vcode_login', // 验证码登录
  CHANGE_PWD: 'api/user/profile/changePassword', // 修改密码
  RESET_PWD: 'api/user/public/passwordReset', // 重置密码
  BIND_EMAIL: 'api/user/profile/bindingEmail', // 绑定邮箱
  BIND_PHONE: 'api/user/profile/bindingMobile', // 绑定手机
  USER_INFO: 'api/user/profile/userInfo', // 用户信息
  INVITATION_INFO: 'api/user/invitation/info', // 邀请信息
  INVITATION_CODES: 'api/user/invitation/codes', // 我的邀请码列表
  INVITATION_CREATE_CODE: 'api/user/invitation/createCode', // 生成邀请码
  INVITATION_TOGGLE_CODE: 'api/user/invitation/toggleCode', // 启用/停用邀请码
  INVITATION_LIST: 'api/user/invitation/getList', // 已邀请列表
  ADD_PAY_PWD: 'api/user/pay_pwd/addpwd', // 添加支付密码
  CHANGE_PAY_PWD: 'api/user/pay_pwd/uppwd', // 更新支付密码
  FORGET_PAY_PWD: 'api/user/pay_pwd/forgetpwd', // 找回支付密码
  INVITATION_LEVEL: 'api/user/invitation/changeUserLevel',

  USER_AUTH: 'api/user/user_auth/getAuthGrade', // 获取认证等级描述
  ADD_AUTH: 'api/user/user_auth/addauth', // 申请实名认证
  SHOW_AUTH: 'api/user/user_auth/showauth', // 查询实名认证详情

  OPEN_CHECK_GOOGLE: 'api/user/google/opencheck', // 开启前绑定Google二级验证
  CONFIRM_CHECK_GOOGLE: 'api/user/google/confirmCheck', // 确认开启Google二步验证
  CLOSE_CHECK_GOOGLE: 'api/user/google/closecheck', // 关闭Google二步验证

  API_ACCOUNT: 'api/third/account/accountInfo', // 查看对应平台的API账户
  API_ACCOUNT_EDIT: 'api/third/account/addAccount', // 设定(重新填写)API账户
  API_ACCOUNT_FUTURE_ADD: 'api/third/account/addFutrueAccount', // 增加合约账户
  API_ACCOUNT_FUTURE_INFO: 'api/third/account/futureAccountInfo', // 查看对应平台的合约API账户
  API_ACCOUNT_REMOVE: 'api/third/account/removeAccount', // 删除API账户
  API_ACCOUNT_BALANCE: 'api/third/account/accountBalance', // 查看对应平台的API账户币种余额
  API_ACCOUNT_FUTURE_BALANCE: 'api/third/account/accountFutureBalance', //合约余额


  MARKET_LIST: 'api/quant/robot/marketList', // 市场列表
  ROBOT_LIST: 'api/quant/robot/robotList', // 机器人列表
  ROBOT_CREATE: 'api/quant/robot/create', // 创建机一个量化机器人
  ROBOT_EDIT: 'api/quant/robot/edit', // 更新机器人参数
  ROBOT_DISABLE: 'api/quant/robot/disable', // 禁用机器人
  ROBOT_ENABLE: 'api/quant/robot/enable', // 启用机器人
  ROBOT_CLEAN: 'api/quant/robot/clean', // 清仓卖出
  ROBOT_LOG: 'api/quant/robot/log', // 机器人日志
  ROBOT_ORDER: 'api/quant/robot/order', // 机器人订单
  ROBOT_REVENUE: 'api/quant/robot/revenue', // 机器人收益账单

  ////合约合约合约合约合约合约合约合约合约合约合约合约合约合约///
  ROBOT_FUTURE_LIST: 'api/quant/future/robotList', // 合约机器人列表
  ROBOT_FUTURE_CREATE: 'api/quant/future/create', // 合约
  ROBOT_FUTURE_EDIT: 'api/quant/future/edit', //  合约
  ROBOT_FUTURE_DISABLE: 'api/quant/future/disable', // 合约
  ROBOT_FUTURE_ENABLE: 'api/quant/future/enable', // 合约
  ROBOT_FUTURE_CLEAN: 'api/quant/future/clean', // 清仓卖出
  ROBOT_FUTURE_REVENUE: 'api/quant/future/revenue', // 机器人收益账单



  CDKEY_LIST: 'api/user/cdkey/list', // 激活码列表
  CDKEY_INPUT: 'api/user/cdkey/input', // 手工领取激活码
  CDKEY_BUY: 'api/user/cdkey/buy', // 购买激活码

  // 授权登录
  OPEN_AUTH_LOGIN: 'api/user/third/openAuthLogin',

  // 查询指定交易对当前价格行情
  PUBLIC_TICKER: 'api/trade/public/ticker',

  // 购买套餐
  PACKAGE_LIST: 'api/user/package/list',
  PACKAGE_BUY: 'api/user/package/buy'
}
