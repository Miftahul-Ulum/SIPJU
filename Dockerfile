FROM php:8.2-apache

# Ekstensi yang dibutuhkan SIPJU
RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/

# Inisialisasi skema DB otomatis (idempotent) lalu jalankan Apache
CMD ["sh", "-c", "php /var/www/html/deploy/init_cli.php && apache2-foreground"]
