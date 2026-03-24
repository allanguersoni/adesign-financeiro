FROM php:8.2-apache

# Instala extensões PDO, PDO_MySQL e MySQLi
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilita o mod_rewrite do Apache
RUN a2enmod rewrite

# Configura AllowOverride para suportar .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Define o diretório de trabalho
WORKDIR /var/www/html
