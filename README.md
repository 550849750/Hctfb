# Hctfb
* XR插件：火车头一键发布接口，实现快速整站采集、仿制功能

## 接口地址:
*	/index.php?s=hctfb&c=home&m=release
## POST请求参数:
>> 验证参数:

*	module
*	auth

>> 文章内容参数:

*	cp1		词频最高
*	cd1		词段最长
*	xgc1	相关词
*	keywords	文章的SEO keyword
*	neirong		文章内容
*	comments	文章评论
*	img			文章缩略图
*	title		文章title
*	have_summary	标记字段  标识已生成摘要
*	is_worked		标记字段  标识内容已经被处理
*	cat_1			文章所属栏目 支持到10级栏目
*	cat_2 ...cat_10
