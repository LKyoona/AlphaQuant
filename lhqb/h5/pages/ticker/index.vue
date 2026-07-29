<template>
  <div>
    <h3 >合约策略说明  <a href="https://docs.qq.com/doc/DT1F6QXBPbVpMd0Rw" target="_blank">查看</a></h3>
    <el-form ref="elForm" :model="formData" :rules="rules" size="medium" label-width="120px">
      <el-form-item label="用户姓名" prop="name">
        <el-input v-model="formData.name" placeholder="请输入用户姓名" :maxlength="11" show-word-limit clearable
                  prefix-icon="el-icon-mobile" :style="{width: '100%'}"
        ></el-input>
      </el-form-item>
      <el-form-item label="初始本金" prop="seed">
        <el-input v-model="formData.seed" placeholder="请输入初始本金" :maxlength="11" show-word-limit clearable
                  prefix-icon="el-icon-mobile" :style="{width: '100%'}"
        ></el-input>
      </el-form-item>
      <el-form-item label="API KEY" prop="key">
        <el-input v-model="formData.key" placeholder="API KEY，请确保是开通合约后申请的API KEY" :maxlength="11"
                  show-word-limit clearable prefix-icon="el-icon-mobile" :style="{width: '100%'}"
        ></el-input>
      </el-form-item>
      <el-form-item label="API SECRET" prop="secret">
        <el-input v-model="formData.secret" placeholder="请输入API  SECRET" :maxlength="11" show-word-limit
                  clearable prefix-icon="el-icon-mobile" :style="{width: '100%'}"
        ></el-input>
      </el-form-item>
      <el-form-item label="策略类型" prop="type">
        <el-radio-group v-model="formData.type" size="medium">
          <el-radio v-for="(item, index) in typeOptions" :key="index" :label="item.value"
                    :disabled="item.disabled"
          >
            {{ item.label }}
          </el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="" prop="btn">
        <el-button type="primary" icon="el-icon-s-promotion" size="medium"> 提交 </el-button>
      </el-form-item>
      <el-form-item size="large">
        <el-button type="primary" @click="submitForm">提交</el-button>
        <el-button @click="resetForm">重置</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>
<script>
export default {
  name: 'FutureForm',
  components: {},
  props: [],
  data() {
    return {
      formData: {
        name: '',
        seed: '',
        key: '',
        secret: '',
        type: 1,
        btn: undefined
      },
      rules: {
        name: [{
          required: true,
          message: '请输入用户姓名',
          trigger: 'blur'
        }, {
          pattern: /^1(3|4|5|7|8|9)\d{9}$/,
          message: '手机号格式错误',
          trigger: 'blur'
        }],
        seed: [{
          required: true,
          message: '请输入初始本金',
          trigger: 'blur'
        }, {
          pattern: /^1(3|4|5|7|8|9)\d{9}$/,
          message: '手机号格式错误',
          trigger: 'blur'
        }],
        key: [{
          required: true,
          message: 'API KEY，请确保是开通合约后申请的API KEY',
          trigger: 'blur'
        }, {
          pattern: /^1(3|4|5|7|8|9)\d{9}$/,
          message: '手机号格式错误',
          trigger: 'blur'
        }],
        secret: [{
          required: true,
          message: '请输入API  SECRET',
          trigger: 'blur'
        }, {
          pattern: /^1(3|4|5|7|8|9)\d{9}$/,
          message: '手机号格式错误',
          trigger: 'blur'
        }],
        type: [{
          required: true,
          message: '策略类型不能为空',
          trigger: 'change'
        }]
      },
      typeOptions: [{
        label: '稳健型',
        value: 1
      }, {
        label: '激进型',
        value: 2
      }]
    }
  },
  computed: {},
  watch: {},
  created() {},
  mounted() {},
  methods: {
    submitForm() {
      this.$refs.elForm.validate((valid) => {
        if (!valid) {}
        // TODO 提交表单
      })
    },
    resetForm() {
      this.$refs.elForm.resetFields()
    }
  }
}

</script>
<style>
</style>
