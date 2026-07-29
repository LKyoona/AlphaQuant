# AlphaQuant 项目文档入口

根目录只保留下面 4 份主文档。第一次接触项目时，按顺序阅读即可。

| 文档 | 什么时候看 |
|---|---|
| `README.md` | 快速找到源码、环境和其他说明 |
| `本地开发说明.md` | 在 Windows 本地启动 PHP、修改后台或 H5 |
| `正式环境部署与发布.md` | 日常发布到正式环境、验收和回滚 |
| `项目架构说明.md` | 理解 PHP、H5、后台模板、Python、数据库之间的关系 |

## 源码位置

本地项目根目录：

```text
G:\量化生产环境\AlphaQuant
```

主要业务源码：

```text
G:\量化生产环境\AlphaQuant\lhqb
```

常用目录：

| 内容 | 目录 |
|---|---|
| PHP 接口代码 | `lhqb\api` |
| PHP 后台及业务模块 | `lhqb\app` |
| 后台管理页面 | `lhqb\public\themes\admin_h` |
| H5 源码 | `lhqb\h5` |
| H5 发布文件 | `lhqb\public\app` |
| Python 程序 | `lhqb\python` |
| 部署脚本 | `deploy` |

## 环境入口

| 环境 | 地址 | 服务器项目目录 |
|---|---|---|
| 本地 PHP | `http://127.0.0.1:8888` | 本地 `lhqb` |
| 正式环境 | 读取 `deploy\config\production.psd1` | `/data/lhqb` |

服务器统一使用：

```text
/data/lhqb/current     当前版本
/data/lhqb/releases    历史版本
/data/lhqb/shared      配置、上传和运行数据
/data/lhqb/logs        统一日志
/root/lhqb             指向 /data/lhqb 的快捷软链接
```

## 重要规则

1. 新服务器不使用 Docker，不使用 Apache。
2. H5 源码不能直接发布，必须先构建到 `lhqb\public\app`。
3. 日常发布不要覆盖服务器的 `shared` 配置、上传文件和运行数据。
4. 发布完成必须检查页面、接口和日志；异常时立即回滚。
5. 当前不再维护测试环境，代码修改后按正式环境发布文档执行。
