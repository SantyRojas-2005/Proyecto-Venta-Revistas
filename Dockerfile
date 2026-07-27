# ============================================================
# Dockerfile - Despliegue en Render
# PHP 8.2 + Apache, DocumentRoot en public/, extension pdo_mysql
# ============================================================
FROM php:8.2-apache

# Extension PDO para MySQL (la imagen base no la trae activada)
RUN docker-php-ext-install pdo_mysql

# Habilitar mod_rewrite y permitir .htaccess (para los Require all denied)
RUN a2enmod rewrite

# Copiar todo el proyecto al servidor
COPY . /var/www/html/

# DocumentRoot -> public/ : igual que la buena practica local,
# solo esa carpeta queda expuesta al navegador
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Permitir que los .htaccess funcionen
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
    > /etc/apache2/conf-available/htaccess.conf \
 && a2enconf htaccess

# Render asigna el puerto en la variable PORT (por defecto 10000).
# Ajustamos Apache al arrancar para escuchar en ese puerto.
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
 && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
 && apache2-foreground
