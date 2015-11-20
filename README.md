INTRO
=====================
访问增加访问某个网站时，增加成功率的代理

INSTALL
=====================



配置HTTP访问接口示例
=====================
* Apache 配置方法
```
<VirtualHost *:80>

        ServerName cy.example.com
        DocumentRoot /cy path/www

        RewriteEngine On

        RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-f
        RewriteCond %{DOCUMENT_ROOT}%{REQUEST_FILENAME} !-d
        RewriteRule . /index.php [L]

        ErrorLog logs/cy-error.log
        CustomLog logs/cy-access.log combined

</VirtualHost>
```



