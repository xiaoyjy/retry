<?php

if(!defined("CY_ERRNO_LOAD"))
{

define("CY_ERRNO_LOAD"            , true);

define("CYE_ERROR"                , 1);
define("CYE_WARNING"              , 2);
define("CYE_MONITOR"              , 4);
define("CYE_ACCESS"               , 8);
define("CYE_TRACE"                , 16);
define("CYE_NOTICE"               , 16);
define("CYE_DEBUG"                , 32);

define("OK"                       , 0);

/**
 * 用户未登陆
 */
define("CYE_USER_NOT_LOGIN"       , 1);

/**
 * 用户未注册
 */
define("CYE_USER_NOT_REGISTER"    , 2);

/**
 * 用户不存在
 */
define("CYE_USER_NOT_EXIST"       , 3);

/**
 * 系统繁忙
 */
define("CYE_SYSTEM_ERROR"         , 4);

/**
 * 参数错误
 */
define("CYE_PARAM_ERROR"          , 7);

/**
 * 错误的结果(业务错误)
 * <pre>
 * 如:用户名错误,含有非法字符等
 * </pre>
 */
define("CYE_RESULT_ERROR"         , 9);


define("CYE_ACCESS_DENIED"        , 10);

/**
 * 得到空数据返回
 */
define("CYE_DATA_EMPTY"           , 17); // same as CYE_RETURN_VALUE_NULL

/**
 * 访问被拒绝黑名单类
 * 一般为后台返回错误
 */
define("CYE_TRACE_DENIED"        , 111);


define("CYE_METHOD_NOT_EXIST"     , 112);

define("CYE_REDIS_READ_ERROR"     , 113);

define("CYE_TEMPLATE_ERROR"       , 114);


define("CYE_NET_ERROR"            , 115);

define("CYE_NET_TIMEOUT"          , 116);


define("CYE_EXPECT_FAIL"          , 417);

define("CYE_UNKNOWN_EXCEPTION"    , 201);

/* 数据库不允许并行拉取 */
define("CYE_DB_MGET_LIMIT"        , 202);

/*
 * MySQL errno same as MySQL Client Error Codes https://dev.mysql.com/doc/refman/5.5/en/error-messages-client.html
 * MySQL Client Error Codes, always like 20xx
 */
define("CYE_DB_UNKNOWN_ERROR"     , 2000);
define("CYE_DB_CONNECT_ERROR"     , 2003);
define("CYE_DB_CONNECT_ERROR1"    , 2002);
define("CYE_DB_CONNECT_GONE"      , 2006);



} // endif.

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
