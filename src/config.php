<?
@session_start();

//  Токен доступа
if(!isset($_SESSION['access_token']))
  $_SESSION['access_token'] = '';

//  Данные для получения нового токена
if(!isset($_SESSION['refresh_info']))
  $_SESSION['refresh_info'] = array(
    'grant_type'    => 'refresh_token',
    'refresh_token' => '1000.d9ed492d3732aabb7de62dc2aad6ab61.263d2a33c4a6e2b0d1a6227315a3ad42',
    'client_id'     => '1000.0LQTVX35IQNN03177ZWW5CZM4EEHSR',
    'client_secret' => 'b19e8c887f90aee8842ba24987c8f410525c91805d'
  );
