<?php

if(!defined('CY_ERROR_LOAD'))
{

define("CY_ERROR_LOAD", true);

$_ENV['errors'] = $_ERRORS = array(

OK                        => '',

CYE_USER_NOT_LOGIN        => '未登录',
CYE_USER_NOT_REGISTER     => '未注册',

/**
 * 用户不存在
 */
CYE_USER_NOT_EXIST        => '用户不存在',

/**
 * 系统繁忙
 */
CYE_SYSTEM_ERROR          => '系统繁忙',

CYE_DATA_EMPTY            => '得到空数据返回',

/**
 * 错误的结果(业务错误
 * <pre>
 * 如:用户名错误,含有非法字符等
 * </pre>
 */
CYE_RESULT_ERROR          => '错误的结果(业务错误)',


CYE_TRACE_DENIED         => '拒绝访问',

CYE_NET_ERROR             => '网络繁忙',

CYE_NET_TIMEOUT           => '网络超时',

/**
 * 参数错误
 */                                                                                                                                         
CYE_PARAM_ERROR           => '输入参数错误', 

CYE_METHOD_NOT_EXIST      => '方法不存在'  ,

CYE_REDIS_READ_ERROR      => '存储后端服务忙-RDS',

//CYE_INTEGRATE_SRV_ERROR   => '后端服务忙-INTEGRATE',

CYE_EXPECT_FAIL           => 'Expectation Failed',

CYE_UNKNOWN_EXCEPTION     => 'Uncaught Exception'
);

}
/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
