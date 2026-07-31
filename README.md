# AlphaQuant 项目文档入口

第一次接触这个项目，先看这 4 份文档就够了。

| 文档 | 用途 |
|---|---|
| `README.md` | 先找到源码和各类说明 |
| `本地开发说明.md` | 在 Windows 本地启动 PHP、修改后台和 H5 |
| `正式环境部署与发布.md` | 小白照着执行的正式发布、验收、日志和回滚手册 |
| `项目架构说明.md` | 了解 PHP、H5、后台模板、Python 和数据库的关系 |

## 项目目录

本地项目根目录：

```text
G:\量化生产环境\AlphaQuant
```

主要业务代码目录：

```text
G:\量化生产环境\AlphaQuant\lhqb
```

常用子目录：

| 内容 | 目录 |
|---|---|
| PHP 接口代码 | `lhqb\api` |
| PHP 后台和业务模块 | `lhqb\app` |
| 后台管理页面 | `lhqb\public\themes\admin_h` |
| H5 源码 | `lhqb\h5` |
| H5 发布后的文件 | `lhqb\public\app` |
| Python 程序 | `lhqb\python` |
| 发布脚本 | `deploy` |

## 运行入口

| 场景 | 地址 | 项目目录 |
|---|---|---|
| 本地 PHP | `http://127.0.0.1:8888` | 本地 `lhqb` |
| 正式环境 | 读取 `deploy\config\production.psd1` | `/data/lhqb` |

服务器统一目录如下：

```text
/data/lhqb/current     当前版本
/data/lhqb/releases    历史版本
/data/lhqb/shared      配置、上传文件和运行数据
/data/lhqb/logs        统一日志
/root/lhqb             指向 /data/lhqb 的软链接
```

## 重要规则

1. 新服务器不使用 Docker，也不使用 Apache。
2. H5 源码不能直接发布，必须先构建到 `lhqb\public\app`。
3. 日常发布不要覆盖服务器 `shared` 里的配置、上传文件和运行数据。
4. 发布完成后必须检查页面、接口和日志；如果异常，立即回滚。
5. 现在不再维护测试环境，代码改完以后按正式环境发布文档执行。
