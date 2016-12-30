# HarvestHand

## PHP
min v5.4.30 / max v5.6

## PHP Modules
apc
apcu
bcmath
bz2
calendar
Core
ctype
curl
date
dom
ereg
exif
fileinfo
filter
ftp
gd
gettext
hash
iconv
imagick
imap
intl
json
libxml
mbstring
mcrypt
memcache
mysql
mysqli
mysqlnd
openssl
pcre
PDO
pdo_mysql
pdo_sqlite
Phar
posix
Reflection
session
SimpleXML
soap
sockets
SPL
sqlite3
standard
tidy
timezonedb
tokenizer
wddx
xml
xmlreader
xmlrpc
xmlwriter
xsl
zip
zlib

## Apache vhost
```
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName hhint.com
    ServerAlias *.hhint.com
    ServerAlias taproot.ca *.taproot.ca
    DocumentRoot /home/michael/Documents/personal/NetBeansProjects/farmnik/trunk/public/
    ErrorLog /var/log/apache2/error.harvesthand.log
    CustomLog /var/log/apache2/access.harvesthand.log combined

    SetEnv APPLICATION_ENV development

    php_flag session.auto-start off

    RewriteEngine on
    RedirectMatch 404 /\.svn(/|$)
    RewriteCond %{REQUEST_METHOD} ^TRACE
    RewriteRule .* - [F]
    RewriteCond /home/michael/Documents/personal/NetBeansProjects/farmnik/trunk/public%{SCRIPT_FILENAME} !-f
    RewriteCond /home/michael/Documents/personal/NetBeansProjects/farmnik/trunk/public%{SCRIPT_FILENAME} !-d
    RewriteRule ^(.*)$ /index.php/$1

    <Directory /home/michael/Documents/personal/NetBeansProjects/farmnik/trunk/public>
        AllowOverride None
        Options FollowSymLinks
        #Order Allow,Deny
        #Allow from all
        Require all granted
        
        RedirectMatch 404 /\.svn(/|$)

        AddDefaultCharset utf-8
        AddCharset utf-8 .html .css .js .xml .json .rss

        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css application/x-javascript text/javascript application/javascript application/json
        <FilesMatch "\.(ttf|otfs|eot|svg)$" >
            SetOutputFilter DEFLATE
        </FilesMatch>

        BrowserMatch MSIE ie
        Header set X-UA-Compatible "IE=Edge,chrome=1" env=ie

        AddType video/ogg                     ogg ogv
        AddType video/mp4                     mp4
        AddType video/webm                    webm
        AddType image/svg+xml                 svg svgz
        AddType application/vnd.ms-fontobject eot
        AddType font/ttf                      ttf
        AddType font/otf                      otf
        AddType font/x-woff                   woff

        ExpiresActive on
        ExpiresDefault                          "access plus 1 month"
        ExpiresByType text/cache-manifest       "access plus 0 seconds"
        ExpiresByType text/html                 "access"
        ExpiresByType application/rss+xml       "access plus 1 hour"
        ExpiresByType image/vnd.microsoft.icon  "access plus 1 week"
        ExpiresByType video/ogg                 "access plus 1 month"
        ExpiresByType audio/ogg                 "access plus 1 month"
        ExpiresByType video/mp4                 "access plus 1 month"
        ExpiresByType font/ttf                  "access plus 1 month"
        ExpiresByType font/woff                 "access plus 1 month"
        ExpiresByType image/svg+xml             "access plus 1 month"
        ExpiresByType text/css                  "access plus 1 month"
        ExpiresByType application/javascript    "access plus 1 month"
        ExpiresByType text/javascript           "access plus 1 month"
        ExpiresByType image/png                 "access plus 1 month"
        ExpiresByType image/jpg                 "access plus 1 month"
        ExpiresByType image/jpeg                "access plus 1 month"
        FileETag None

        Header merge cache-control: public
    </Directory>
    <Location />
        Header set P3P "policyref=\"/w3c/p3p.xml\", CP=\"IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT\""
    </Location>
</VirtualHost>
```

## Other
 - MySQL
 - Memcached
