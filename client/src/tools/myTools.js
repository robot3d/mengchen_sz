import axios from 'axios'

let myTools = {
  //axios默认实例
  axiosInstance: axios.create({
    timeout: 5000,                          //超时时间
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    validateStatus: function (status) {
      return status === 200 || status === 422
    },
  }),

  //处理laravel返回的消息，并用toastr提示
  msgResolver: function (res, toastr) {
    if (res.status === 422) {
      return toastr.message(JSON.stringify(res.data), 'error')
    }
    if (res.data.error) {
      return toastr.message(res.data.error, 'error')
    }
    if (res.data.info) {
      return toastr.message(res.data.info, 'info')
    }
    return toastr.message(res.data.message)
  },
}

export { myTools }
export default myTools