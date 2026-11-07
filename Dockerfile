FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# Só public/ é servido pela web; src/ e .env ficam fora do alcance do navegador
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# www-data no grupo dono do docker.sock (Dash lê os logs do ml-jrf).
# Precisa estar em /etc/group: o Apache recarrega os grupos ao trocar de usuário.
ARG DOCKER_GID=988
RUN groupadd -g "${DOCKER_GID}" docker-host && usermod -aG docker-host www-data

# Dados que precisam sobreviver à recriação do container (volume em docker-compose.yml)
RUN install -d -o www-data -g www-data -m 700 /var/lib/ml-app /var/lib/ml-app/sessions /var/lib/ml-app/login-tentativas

WORKDIR /var/www/html
COPY public ./public
COPY src ./src
RUN chown -R www-data:www-data /var/www/html
