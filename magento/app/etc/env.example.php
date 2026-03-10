<?php
return [
 'backend' => [
  'frontName' => 'admin'
 ],

 'db' => [
  'connection' => [
   'default' => [
    'host' => 'mysql',
    'dbname' => 'magento',
    'username' => 'magento',
    'password' => 'magento_password'
   ]
  ]
 ],

 'crypt' => [
  'key' => 'CHANGE_ME'
 ],

 'session' => [
  'save' => 'files'
 ],

 'MAGE_MODE' => 'developer'
];
